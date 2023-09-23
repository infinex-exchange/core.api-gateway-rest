<?php

use Infinex\Exceptions\Error;
use React\Http\Message\Response;

class HttpServer {
    private $loop;
    private $log;
    private $amqp;
    private $server;
    private $socket;
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
    
    function __construct($loop, $log, $amqp) {
        $th = $this;
        
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> amqp = $amqp;
        
        $this -> server = new React\Http\HttpServer(function($request) use ($th) {
            return $th -> request($request);
        });
        
        $this -> log -> debug('Initialized HTTP server');
    }
    
    public function start() {
        $th = $this;
        
        if($this -> socket === null) {
            try {
                $this -> socket = new React\Socket\SocketServer(
                    HTTP_BIND_ADDR.':'.HTTP_BIND_PORT,
                    [],
                    $this -> loop
                );
            
                $this -> server -> listen($this -> socket);
                
                $this -> startTimer = null;
            } catch(Exception $e) {
                $this -> log -> error('Cannot start HTTP server: '.((string) $e);
                
                $this -> startTimer = $this -> loop -> addTimer(1, function() use($th) {
                    $th -> start();
                });
                
                return;
            }
        } else {
            $this -> socket -> resume();
        }
        
        $this -> log -> info('Started HTTP server');
    }
    
    public function stop() {
        if($this -> startTimer !== null) {
            $this -> loop -> cancelTimer($this -> startTimer);
            $this -> startTimer = null;
        } else {
            $this -> socket -> pause();
        }
        
        $this -> log -> warning('Stopped HTTP server');
    }
    
    private function request($request) {
        $method = $request -> getMethod();
        
        if($method == 'OPTIONS')
            return new Response(
                200,
                HttpServer::PREFLIGHT_HEADERS,
                ''
            );
        
        $path = $request -> getUri() -> getPath();
        $query = $request -> getQueryParams();
        $body = json_decode($request -> getBody(), true);
        
        $apiKey = null;
        $auth = $request -> getHeaderLine('Authorization');
        $auth = explode(' ', $auth);
        if(count($auth) == 2 && strtolower($auth[0]) == 'bearer')
            $apiKey = $auth[1];
                
        return $th -> amqp -> call(
            'auth.rest-api-auth',
            'rest',
            [
                'method' => $method,
                'path' => $path,
                'query' => $query,
                'body' => $body,
                'apiKey' => $apiKey,
                'userAgent' => $request -> getHeaderLine('User-Agent'),
                'ip' => $request -> getHeaderLine('X-Forwarded-For')
            ]
        ) -> then(
            function($resp) use($th) {
                return new Response(
                    $resp['status'],
                    HttpServer::RESPONSE_HEADERS,
                    json_encode($resp['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }
        ) -> catch(
            function($e) use($th, $method, $path) {
                $status = 500;
                $code = 'INTERNAL_SERVER_ERROR';
                $msg = 'Internal server error';
                
                if($e instanceof Error) {
                    if($e -> getCode()))
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