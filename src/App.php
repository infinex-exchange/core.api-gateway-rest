<?php

class App extends Infinex\Daemon {
    function __construct() {
        parent::__construct('api-gateway-rest');
        
        //
        
        $th = $this;
        $this -> amqp -> on('connect', function() use($th) {
        });
        
        $this -> amqp -> on('disconnect', function() use($th) {
        });
    }
    
    public function run() {
        $this -> loop -> run();
    }
}

?>