<?php

require __DIR__.'/HttpServer.php';
require __DIR__.'/Authenticator.php';
require __DIR__.'/Router.php';

class App extends Infinex\App\App {
    private $pdo;
    private $http;
    private $cs;
    private $auth;
    private $router;
    
    function __construct() {
        $th = $this;
        
        parent::__construct('core.api-gateway-rest');
        
        $this -> pdo = new Infinex\Database\PDO(
            $this -> loop,
            $this -> log,
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );
        
        $this -> auth = new Authenticator($this -> log, $this -> amqp);
        
        $this -> router = new Router($this -> loop, $this -> log, $this -> pdo);
        
        $this -> http = new HttpServer(
            $this -> loop,
            $this -> log,
            $this -> auth,
            $this -> router,
            HTTP_BIND_ADDR,
            HTTP_BIND_PORT
        );
        
        $this -> cs = new Infinex\App\ConditionalStart(
            $this -> loop,
            $this -> log,
            [
                $this -> amqp,
                $this -> pdo
            ],
            [
                $this -> http,
                $this -> router
            ]
        );
    }
    
    public function start() {
        parent::start();
        $this -> pdo -> start();
        $this -> cs -> start();
    }
    
    public function stop() {
        $th = $this;
        
        $this -> cs -> stop() -> then(
            function() use($th) {
                return $this -> pdo -> stop();
            }
        ) -> then(
            function() use($th) {
                $th -> parentStop();
            }
        );
    }
    
    private function parentStop() {
        parent::stop();
    }
}

?>