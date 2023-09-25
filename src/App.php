<?php

require __DIR__.'/HttpServer.php';
require __DIR__.'/Authenticator.php';
require __DIR__.'/Router.php';

use React\Promise;

class App extends Infinex\App\App {
    private $pdo;
    private $http;
    private $auth;
    private $router;
    
    function __construct() {
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
            $this -> amqp,
            $this -> auth,
            $this -> router,
            HTTP_BIND_ADDR,
            HTTP_BIND_PORT
        );
    }
    
    public function start() {
        $th = $this;
        
        parent::start() -> then(
            function() use($th) {
                return $th -> pdo -> start();
            }
        ) -> then(
            function() use($th) {
                return Promise\all([
                    $th -> auth -> start(),
                    $th -> router -> start()
                ]);
            }
        ) -> then(
            function() use($th) {
                return $th -> http -> start();
            }
        ) -> catch(
            function($e) {
                $th -> log -> error('Failed start app: '.((string) $e));
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        $this -> http -> stop() -> then(
            function() use($th) {
                return Promise\all([
                    $th -> auth -> stop(),
                    $th -> router -> stop()
                ]);
            }
        ) -> then(
            function() use($th) {
                return $th -> pdo -> stop();
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