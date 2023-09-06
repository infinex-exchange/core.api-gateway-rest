<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Infinex\AMQP\RPCException;

class HttpServer {
    private $loop;
    private $logger;
    private $amqp;
    private $server;
    private $socket;
    private $startTimer;
    
    function __construct($loop, $logger, $amqp) {
        $this -> loop = $loop;
        $this -> logger = $logger;
        $this -> amqp = $amqp;
        
        $th = $this;
        $this -> server = new React\Http\HttpServer(function (ServerRequestInterface $request) use ($th) {
            $method = request -> getMethod();
            $path = $request -> getUri() -> getPath();
            $query = $request -> getQueryParams();
            $body = json_decode($request -> getBody(), true);
                    
            return $th -> amqp -> call(
                'api',
                [
                    'method' => $method,
                    'path' => $path,
                    'query' => $query,
                    'body' => $body,
                ]
            ) -> then(
                function($resp) use($th) {
                    return new Response(
                        $resp -> status,
                        [
                            'Content-Type' => 'application/json',
                            'Access-Control-Allow-Origin' => '*'
                        ],
                        json_encode($resp -> body, JSON_PRETTY_PRINT)
                    );
                }
            ) -> catch(
                function(RPCException $e) use($th) {
                    $th -> logger -> warn('Error during processing request: '.((string) $e));
                    
                    if(DEBUG_MODE)
                        return new Response(
                            500,
                            [
                                'Content-Type' => 'application/json',
                                'Access-Control-Allow-Origin' => '*'
                            ],
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
                        [
                            'Content-Type' => 'application/json',
                            'Access-Control-Allow-Origin' => '*'
                        ],
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
        
        $this -> logger -> debug('Initialized HTTP server');
    }
    
    public function start() {
        if($this -> socket === null) {
            try {
                $this -> socket = new React\Socket\SocketServer(
                    HTTP_BIND_ADDR.':'.HTTP_BIND_PORT,
                    [],
                    $this -> loop
                );
            
                $this -> server -> listen($this -> server);
                
                $this -> startTimer = null;
            } catch(Exception $e) {
                $this -> logger -> error('Cannot start HTTP server: '.$e -> getMesssage());
                
                $th = $this;
                $this -> startTimer = $this -> loop -> addTimer(1000, function() use($th) {
                    $th -> start();
                });
            }
        } else {
            $this -> socket -> resume();
        }
        
        $this -> logger -> info('Started HTTP server');
    }
    
    public function stop() {
        if($this -> startTimer !== null) {
            $this -> loop -> cancelTimer($this -> startTimer);
            $this -> startTimer = null;
        } else {
            $this -> socket -> pause();
        }
        
        $this -> logger -> warning('Stopped HTTP server');
    }
}

?>