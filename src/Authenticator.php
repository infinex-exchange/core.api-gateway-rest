<?php

use Infinex\Exceptions\Error;
use React\Promise;

class Authenticator {
    private $log;
    private $amqp;
    
    function __construct($log, $amqp) {
        $this -> log = $log;
        $this -> amqp = $amqp;
        
        $this -> log -> debug('Initialized API authenticator');
    }
    
    public function start() {
        $this -> log -> info('Started API authenticator');
        return Promise\resolve(null);
    }
    
    public function stop() {
        $this -> log -> info('Stopped API authenticator');
        return Promise\resolve(null);
    }
    
    public function authenticate($request) {
        $header = $request -> getHeaderLine('Authorization');
        $exploded = explode(' ', $header);
        if(count($exploded) != 2 || strtolower($exploded[0]) != 'bearer')
            return Promise\resolve(null);
        $apiKey = $exploded[1];
        
        return $this -> amqp -> call(
            'account.account',
            'checkApiKey',
            [ 'apiKey' => $apiKey ]
        );
    }
}

?>