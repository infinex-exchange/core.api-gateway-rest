<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Infinex\AMQP\RPCException;

class HttpServer {
    private $loop;
    private $log;
    private $amqp;
    private $server;
    private $socket;
    private $startTimer;
    
    const RESPONSE_HEADERS = [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Headers' => '*',
        'Access-Control-Max-Age' => 86400
    ];
    
    function __construct($loop, $log, $amqp) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> amqp = $amqp;
        
        $th = $this;
        $this -> server = new React\Http\HttpServer(function (ServerRequestInterface $request) use ($th) {
            $method = $request -> getMethod();
            $path = $request -> getUri() -> getPath();
            $query = $request -> getQueryParams();
            $body = json_decode($request -> getBody(), true);
            
            $apiKey = null;
            $auth = $request -> getHeaderLine('Authorization');
            $auth = explode(' ', $auth);
            if(count($auth) == 2 && strtolower($auth[0]) == 'bearer')
                $apiKey = $auth[1];
                    
            return $th -> amqp -> call(
                'api_auth',
                [
                    'method' => $method,
                    'path' => $path,
                    'query' => $query,
                    'body' => $body,
                    'apiKey' => $apiKey
                ]
            ) -> then(
                function($resp) use($th) {
                    return new Response(
                        $resp['status'],
                        HttpServer::RESPONSE_HEADERS,
                        json_encode($resp['body'], JSON_PRETTY_PRINT)
                    );
                }
            ) -> catch(
                function(RPCException $e) use($th) {
                    $th -> log -> warn('Error during processing request: '.((string) $e));
                    
                    if(DEBUG_MODE)
                        return new Response(
                            500,
                            HttpServer::RESPONSE_HEADERS,
                            json_encode(
                                [
                                    'error' => [
                                        'code' => $e -> getStrCode(),
                                        'msg' => $e -> getMessage(),
                                    ]
                                ],
                                JSON_PRETTY_PRINT
                            )
                        );
                        
                    return new Response(
                        500,
                        HttpServer::RESPONSE_HEADERS,
                        json_encode(
                            [
                                'error' => [
                                    'code' => 'INTERNAL_SERVER_ERROR',
                                    'msg' => 'Internal server error',
                                ]
                            ],
                            JSON_PRETTY_PRINT
                        )
                    );
                }
            );
        });
        
        $this -> log -> debug('Initialized HTTP server');
    }
    
    public function start() {
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
                $this -> log -> error('Cannot start HTTP server: '.$e -> getMessage());
                
                $th = $this;
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
}

?>