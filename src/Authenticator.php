<?php

use Infinex\Exceptions\Error;

class Authenticator {
    private $log;
    private $amqp;
    
    function __construct($log, $amqp) {
        $this -> log = $log;
        $this -> amqp = $amqp;
        
        $this -> log -> debug('Initialized API authenticator');
    }
    
    
    public function authenticate($request) {
        $header = $request -> getHeaderLine('Authorization');
        $exploded = explode(' ', $header);
        if(count($exploded) != 2 || strtolower($exploded[0]) != 'bearer')
            return React\Promise\resolve(null);
        $apiKey = $exploded[1];
        
        if(!$this -> validateApiKey($apiKey))
            return React\Promise\reject(
                new Error('VALIDATION_ERROR', 'Invalid API key format', 400)
            );
        
        return $this -> amqp -> call(
            'auth',
            'checkApiKey',
            [ 'apiKey' => $apiKey ]
        );
    }
    
    private function validateApiKey($apiKey) {
        return preg_match('/^[a-f0-9]{64}$/', $apiKey);
    }
}

?>