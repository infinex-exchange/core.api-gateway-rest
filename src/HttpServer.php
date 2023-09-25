<?php

use Infinex\Exceptions\Error;
use React\Http\Message\Response;
use React\Promise;

class HttpServer {
    private $loop;
    private $log;
    private $amqp;
    private $auth;
    private $router;
    
    private $bindAddr;
    private $bindPort;
    
    private $server;
    private $socket;
    private $startDeferred;
    private $startTimer;
    
    const RESPONSE_HEADERS = [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*'
    ];
    const PREFLIGHT_HEADERS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => '*',
        'Access-Control-Allow-Headers' => '*',
        'Access-Control-Max-Age' => 86400
    ];
    
    function __construct(
        $loop,
        $log,
        $amqp,
        $auth,
        $router,
        $bindAddr,
        $bindPort
    ) {
        $th = $this;
        
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> amqp = $amqp;
        $this -> auth = $auth;
        $this -> router = $router;
        
        $this -> bindAddr = $bindAddr;
        $this -> bindPort = $bindPort;
        
        $this -> server = new React\Http\HttpServer(function($request) use ($th) {
            return $th -> request($request);
        });
        
        $this -> log -> debug('Initialized HTTP server');
    }
    
    public function start() {
        if($this -> startDeferred !== null)
            return $this -> startDeferred -> promise();
        
        $this -> startDeferred = new Promise\Deferred();
        $this -> listen();
        return $this -> startDeferred -> promise();
    }
    
    public function stop() {
        $th = $this;
        
        if($this -> startTimer !== null) {
            $this -> loop -> cancelTimer($this -> startTimer);
            return Promise\resolve(null);
        }
        
        $this -> socket -> pause();
        $this -> log -> info('Stopped HTTP server');
        return Promise\resolve(null);
    }
    
    private function listen() {
        $this -> startTimer = null;
        
        try {
            $this -> socket = new React\Socket\SocketServer(
                $this -> bindAddr.':'.$this -> bindPort,
                [],
                $this -> loop
            );
            
            $this -> server -> listen($this -> socket);
            
            $this -> log -> info('Started HTTP server');
            
            $this -> startDeferred -> resolve(null);
        } catch(\Exception $e) {
            $this -> log -> error('Cannot start HTTP server: '.((string) $e));
                
            $this -> startTimer = $this -> loop -> addTimer(1, function() use($th) {
                $th -> listen();
            });
        }
    }
    
    private function request($request) {
        $th = $this;
        
        $method = $request -> getMethod();
        
        if($method == 'OPTIONS')
            return new Response(
                200,
                HttpServer::PREFLIGHT_HEADERS,
                ''
            );
        
        return $this -> auth -> authenticate(
            $request
        ) -> then(
            function($auth) use($th, $request, $method) {
                $origPath = $request -> getUri() -> getPath();
                $route = $th -> router -> route($origPath);
                
                return $th -> amqp -> call(
                    $route['service'],
                    'rest',
                    [
                        'method' => $method,
                        'path' => $route['path'],
                        'query' => $request -> getQueryParams(),
                        'body' => json_decode($request -> getBody(), true),
                        'auth' => $auth,
                        'userAgent' => $request -> getHeaderLine('User-Agent'),
                        'ip' => $request -> getHeaderLine('X-Forwarded-For'),
                        'origPath' => $origPath
                    ]
                );
            }
        ) -> then(
            function($resp) use($th) {
                return new Response(
                    $resp['status'],
                    HttpServer::RESPONSE_HEADERS,
                    json_encode($resp['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }
        ) -> catch(
            function($e) {
                $status = 500;
                $code = 'INTERNAL_SERVER_ERROR';
                $msg = 'Internal server error';
                
                if($e instanceof Error) {
                    if($e -> getCode())
                        $status = $e -> getCode();
                    if($e -> getCode() || DEBUG_MODE) {
                        $code = $e -> getStrCode();
                        $msg = $e -> getMessage();
                    }
                }
                else if(DEBUG_MODE) {
                    $code = 'UNKNOWN';
                    $msg = (string) $e;
                }
                
                return new Response(
                    $status,
                    HttpServer::RESPONSE_HEADERS,
                    json_encode(
                        [
                            'error' => [
                                'code' => $code,
                                'msg' => $msg,
                            ]
                        ],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    )
                );
            }
        );
    }
}

?>