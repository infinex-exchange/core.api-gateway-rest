<?php

require __DIR__.'/HttpServer.php';

class App extends Infinex\Daemon {
    private $http;
    
    function __construct() {
        parent::__construct('api-gateway-rest');
        
        $this -> http = new HttpServer($this -> loop);
        
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