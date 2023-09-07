<?php

require __DIR__.'/HttpServer.php';

class App extends Infinex\App\Daemon {
    private $http;
    
    function __construct() {
        parent::__construct('core.api-gateway');
        
        $this -> http = new HttpServer($this -> loop, $this -> log, $this -> amqp);
        
        $th = $this;
        $this -> amqp -> on('connect', function() use($th) {
            $th -> http -> start();
        });
        
        $this -> amqp -> on('disconnect', function() use($th) {
            $th -> http -> stop();
        });
    }
}

?>