<?php

use PhpAmqpLib\Wire;

function sub($baseStreamName, $res, $argument, $clientId) {
    global $subDb, $debug, $channel, $STREAMS;
    
    // If this res is not already subscribed by any client, bind queue
    if(!isset($subDb[$baseStreamName][$res])) {              
        $queueName = $baseStreamName.'_'.gethostname().'_'.getmypid();
        if($debug) echo "[$clientId] This is first subscription of this res, binding RMQ queue: $queueName\n";
        
        foreach($STREAMS[$baseStreamName]['bind'] as $bind) {
            $headers = array();
            
            $headers['event'] = $bind['event'];
            $headers[ $bind['res'] ] = $res;
            if(isset($bind['extra']))
                foreach($bind['extra'] as $extraKey => $extraVal)
                    $headers[$extraKey] = $extraVal;
            
            $channel -> queue_bind($queueName, 'outEvents', '', false, new Wire\AMQPTable($headers));
        }                
    }
                    
    // Just add subscription
    $subDb[$baseStreamName][$res][$argument][] = $clientId; 
    if($debug) echo "[$clientId] Added subscription\n";
}

function unsub($baseStreamName, $res, $argument, $clientId) {
    global $subDb, $debug, $channel, $STREAMS;
    
    // Just remove subscription
    $subDb[$baseStreamName][$res][$argument] = array_diff($subDb[$baseStreamName][$res][$argument], [$clientId]);
    if($debug) echo "[$clientId] Removed subscription\n";
                    
    // Cleanup empty stream -> res -> ARGUMENT
    if(empty($subDb[$baseStreamName][$res][$argument])) {
        unset($subDb[$baseStreamName][$res][$argument]);
        if($debug) echo "[$clientId] Cleanup empty stream -> res -> ARGUMENT\n";
    }
                        
    // Cleanup empty stream -> RES with queue unbind
    if(empty($subDb[$baseStreamName][$res])) {
        if($debug) echo "[$clientId] Cleanup empty stream -> RES\n";
        
        $queueName = $baseStreamName.'_'.gethostname().'_'.getmypid();
        
        foreach($STREAMS[$baseStreamName]['bind'] as $bind) {
            $headers = array();
            
            $headers['event'] = $bind['event'];
            $headers[ $bind['res'] ] = $res;
            if(isset($bind['extra']))
                foreach($bind['extra'] as $extraKey => $extraVal)
                    $headers[$extraKey] = $extraVal;
            
            $channel -> queue_unbind($queueName, 'outEvents', '', new Wire\AMQPTable($headers));
        }     
        
        if($debug) echo "[$clientId] Queue unbound: $queueName\n";
        
        unset($subDb[$baseStreamName][$res]);
    }
}

?>