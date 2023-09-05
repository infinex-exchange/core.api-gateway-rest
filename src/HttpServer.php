<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\Promise;

class HttpServer {
    private $http;
    private $server;
    
    function __construct($app) {
        $this -> app = $app;
        
        $this -> http = new React\Http\HttpServer(function (ServerRequestInterface $request) use ($th, $dispatcher) {
            $request->getMethod()
            $request -> getUri() -> getPath()
            
                    $get = $request -> getQueryParams();
                    $post = json_decode($request -> getBody(), true);
                    
                    $promise = new Promise(
                        function($resolve, $reject) use($routeInfo, $get, $post) {
                            $routeInfo[1]($resolve, $routeInfo[2], $get, $post);
                        }
                    );
                    
                    return $promise -> then(
                        function($resp) use($th) {
                            if($resp instanceof Response) return $resp;
                            return $th -> respJson($resp);
                        }
                    ) -> catch(
                        function(EHttpNotFound $e) use($th) {
                            return $th -> respNotFound();
                        }
                    ) -> catch(
                        function(Exception $e) use($th) {
                            return $th -> respError($e -> getMessage());
                        }
                    );
        });
        
        $url = 'tls://'.$app -> config -> get($app -> module.'.http.bind_addr').':'.$app -> config -> get($app -> module.'.http.bind_port');
        $this -> server = new React\Socket\SocketServer(
            $url,
            [
                'tls' => [
                    'local_cert' => $this -> getPem()
                ]
            ],
            $app -> loop
        );
        
        $this -> http -> listen($this -> server);
    }
    
    private function respJson($obj) {
        return new Response(
            200,
            [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*'
            ],
            json_encode($obj, JSON_PRETTY_PRINT)
        );
    }
    
    private function respNotFound() {
        return new Response(
            404,
            ['Content-Type' => 'text/plain'],
            'Not found'
        );
    }
    
    private function respError($text) {
        return new Response(
            500,
            ['Content-Type' => 'text/plain'],
            $text
        );
    }
}

?>