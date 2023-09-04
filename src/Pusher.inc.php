<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

// ----- Pusher exception class ------
class PusherException extends Exception {}

// ----- WebSockets server class ------
class Pusher implements MessageComponentInterface {   
    public function __construct() {
        $this -> clients = array();
    }
    
    public function onOpen(ConnectionInterface $conn) {
        global $debug;
        if($debug) echo '['. $conn -> resourceId ."] Client connected\n";
        
        $this -> clients[$conn -> resourceId] = [
            'conn' => $conn,
            'ping' => time()
        ];
    }

    public function onMessage(ConnectionInterface $conn, $msg) {
        global $debug, $subDb, $channel, $STREAMS, $pdo, $authDb;
        
        $msg = json_decode($msg, true);
        $clientId = $conn -> resourceId;
        
        $this -> clients[$clientId]['ping'] = time();
        
        if(!isset($msg['id']))
            throw new PusherException('Request ID not specified', -1);
        
        if(!is_int($msg['id']) || $msg['id'] < 0 || $msg['id'] > 10000)
            throw new PusherException('Invalid request ID', -1);
           
        $id = intval($msg['id']); 
        
        if(!isset($msg['op']))
            throw new PusherException('Operation not specified', $id);
        
        if($msg['op'] != 'sub' && $msg['op'] != 'unsub' && $msg['op'] != 'ping' && $msg['op'] != 'auth')
            throw new PusherException('Unknown operation', $id);
        
        if($debug) echo "[$clientId] Operation: ".$msg['op']."\n";
        
        if($msg['op'] == 'ping') {
            if($debug) echo "[$clientId] Ping\n";
            
            $this -> sendToClient($conn, [
                'class' => 'resp',
                'success' => true,
                'id' => $id
            ]);
            
            return;
        }
        
        if($msg['op'] == 'auth') {
            if($debug) echo "[$clientId] Auth\n";
            
            if(!isset($msg['api_key']) || empty($msg['api_key']))
                throw new PusherException('API key not specified', $id);
            
            if(isset($authDb[$clientId]))
                throw new PusherException('Already logged in', $id);
                    
            $uid = apiLogin($msg['api_key'], $id);
            
            if(!$uid)
	            throw new PusherException('API key rejected', $id);
	            
            $authDb[$clientId] = $uid;
            
            $this -> sendToClient($conn, [
                'class' => 'resp',
                'success' => true,
                'id' => $id
            ]);
            
            return;
        }
        
        if(!isset($msg['streams']) || empty($msg['streams']))
            throw new PusherException('Streams not specified', $id);
        
        if(!is_array($msg['streams'])) {
            $msg['streams'] = array($msg['streams']);
            if($debug) echo "[$clientId] Single stream specified\n";
        }
        else if($debug) echo "[$clientId] Multiple streams specified\n"; 
        
        foreach($msg['streams'] as $stream) {
            if(!is_string($stream))
                throw new PusherException('Stream name is not a string', $id);
            
            $expStreamName = explode('@', $stream);
            // Handle name without @ or without first part
            if(count($expStreamName) == 1) {
                $expStreamName[1] = $expStreamName[0];
                $expStreamName[0] = '';
            }
            if(count($expStreamName) != 2)
                throw new PusherException('Invalid stream name format', $id);
            
            $argument = null;
            $withArgument = explode('/', $expStreamName[1]);
            if(count($withArgument) == 1)
                $argument = 'default';
            else if(count($withArgument) == 2)
                $argument = $withArgument[1];
            else
                throw new PusherException('Too much stream arguments', $id);
                
            $baseStreamName = $withArgument[0];
            $res = $expStreamName[0];
            
            if(!array_key_exists($baseStreamName, $STREAMS))
                throw new PusherException('Invalid stream base name', $id);
            
            if($debug) echo "[$clientId] Exploded stream name:\n    Stream: $baseStreamName\n    Argument: $argument\n    Resource: $res\n";
            
            if($STREAMS[$baseStreamName]['arguments'] !== NULL) {
                if($argument == 'default')
                    throw new PusherException('This stream requires argument but not specified', $id);
                
                if(!array_key_exists($argument, $STREAMS[$baseStreamName]['arguments']))
                    throw new PusherException('Passed argument is invalid', $id);
            }
            
            // This is public stream for trading pair
            if($STREAMS[$baseStreamName]['public']) {
                // $res is pairid
                if($res == '') throw new PusherException('Pair not specified', $id);
                
                $res = strtoupper($res);
                
                if(!validatePairName($res)) throw new PusherException('Pair name not validated', $id);
                
                // Check pair exists
                $task = array(
                    ':pairid' => $res
                );
                
                $sql = 'SELECT pairid
                        FROM spot_markets
                        WHERE pairid = :pairid';
            
                $q = $pdo -> prepare($sql);
                $q -> execute($task);
                $row = $q -> fetch(PDO::FETCH_ASSOC);
                
                if(!$row) throw new PusherException('Pair not exists', $id);
            }
            
            // This is private stream for UID
            else {
                if(isset($authDb[$clientId]))
                    $res = $authDb[$clientId];
                else
                    throw new PusherException('Unauthorized for private stream', $id);
            }
                    
            // Add subscription
            // $res is pairid for public stream
            // $res is uid for private stream
            if($msg['op'] == 'sub' &&
               (
                    !isset($subDb[$baseStreamName][$res][$argument]) ||
                    !in_array($clientId, $subDb[$baseStreamName][$res][$argument])
               )
            ) {
                sub($baseStreamName, $res, $argument, $clientId);
            }
                
            // Remove subscription
            else if($msg['op'] == 'unsub' &&
                    isset($subDb[$baseStreamName][$res][$argument]) &&
                    in_array($clientId, $subDb[$baseStreamName][$res][$argument])
            ) {
                unsub($baseStreamName, $res, $argument, $clientId);
            }
                
            // No action
            else throw new PusherException('No action', $id);
        }
            
        // Success response
            
        $this -> sendToClient($conn, [
            'class' => 'resp',
            'success' => true,
            'id' => $id
        ]);
    }

    public function onClose(ConnectionInterface $conn) {
        global $debug, $subDb, $authDb;
        if($debug) echo '['. $conn -> resourceId ."] Client disconnected\n";
        
        unset($this -> clients[$conn -> resourceId]);
        
        // Remove client subscriptions
        foreach($subDb as $baseStreamName => $resources)
            foreach($resources as $res => $arguments)
                foreach($arguments as $argument => $clients)
                    foreach($clients as $clientId)
                        if($clientId == $conn->resourceId)
                            unsub($baseStreamName, $res, $argument, $clientId);
        
        // Remove authdb entry if exists
        if(isset($authDb[$conn->resourceId]))
            unset($authDb[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, Exception $e) {
        global $debug;
        if($debug) echo '['. $conn -> resourceId .'] '.$e -> getMessage()."\n";
        
        if(get_class($e) == 'PusherException') {
            $this -> sendToClient($conn, [
                'class' => 'resp',
                'success' => false,
                'error' => $e -> getMessage(),
                'id' => $e -> getCode()
            ]);
        }
        
        else {
            $conn -> close();
            throw $e;
        }
    }
    
    public function sendToClient($conn, $msg) {
        $json = json_encode($msg, JSON_PRETTY_PRINT);
        $conn -> send($json);
    }
    
    public function sendToClients($clientIds, $msg) {
        foreach($clientIds as $cid)
            $this -> sendToClient($this -> clients[$cid]['conn'], $msg);
    }
    
    public function killIdleConnections() {
        global $debug;
        
        if($debug) echo "Killing idle connections\n";
        $count = 0;
        
        $now = time();
        
        foreach($this -> clients as $client) {
            if($now - $client['ping'] > 15) {
                $client['conn'] -> close();
                $count++;
            }
        }
        
        if($debug) echo "Total $count killed\n";
    }
}

?>