<?php

require __DIR__.'/HttpServer.php';

class App extends Infinex\App\App {
    private $http;
    
    function __construct() {
        $th = $this;
        
        parent::__construct('core.rest-api-gw');
        
        $this -> http = new HttpServer($this -> loop, $this -> log, $this -> amqp);
        $this -> amqp -> on('connect', function() use($th) {
            $th -> http -> start();
        });
        
        $this -> amqp -> on('disconnect', function() use($th) {
            $th -> http -> stop();
        });
    }
}

?>