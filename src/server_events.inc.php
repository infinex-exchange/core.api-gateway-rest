<?php

use PhpAmqpLib\Message\AMQPMessage;
use Decimal\Decimal;

// onEvent direct handles amqp message
function onEvent(AMQPMessage $msgIn) {
    global $debug, $subDb, $pusher, $STREAMS;
    
    try {
        $body = json_decode($msgIn -> body, true);
        $headers = $msgIn->get('application_headers')->getNativeData();
        $baseStreamName = (explode('_', $msgIn -> getConsumerTag()))[1]; 
    
        if($debug) echo "Processing event:\nHeaders:\n".json_encode($headers, JSON_PRETTY_PRINT).
                        "\nBody:\n".$msgIn->body."\nStream: $baseStreamName\n";
                        
        foreach($STREAMS[$baseStreamName]['bind'] as $bind)
            if($bind['event'] == $headers['event']) {
                $res = $headers[ $bind['res'] ];
                
                if(isset($subDb[$baseStreamName][$res]))
                foreach($subDb[$baseStreamName][$res] as $argument => $clientsOfArgument) {
                    $msgOut = call_user_func($baseStreamName, $res, $argument, $headers, $body, $bind);
                    if($msgOut === NULL) continue;
                    
                    $streamName = $baseStreamName;
                    if($STREAMS[$baseStreamName]['public']) $streamName = $res.'@'.$streamName;
                    if($argument !== 'default') $streamName .= '/'.$argument;
        
                    $msgOut = array_merge(['class' => 'data', 'stream' => $streamName], $msgOut);
                    $pusher -> sendToClients($clientsOfArgument, $msgOut);
                }
            }  
                    
        $msgIn -> ack();
    }
    catch(Exception $e) {
        $msgIn -> reject(true);
        throw $e;
    }
}

// ----- Public events handlers -----

function marketTrade($pairid, $a, $headers, $body, $b) {
    return [
        'time' => $body['time'],
        'price' => $body['price'],
        'amount' => $body['amount'],
        'total' => $body['total'],
        'side' => $body['taker_side'],
        'pair' => $pairid
    ];
}

function candleStick($pairid, $argument, $headers, $body, $b) {
    global $STREAMS;
    
    $msgOut = getLatestCandleStick($pairid, $argument);
    $STREAMS['candleStick']['arguments'][$argument]['lastBucket'] = $msgOut['time'];
    return $msgOut;
}

function ticker($pairid, $a, $headers, $body, $b, $extended = false) {                       
    $msgOut = [
        'pair' => $pairid,
        'price' => trimFloat($body['price']),
        'change' => $body['change'],
        'previous' => trimFloat($body['previous'])
    ];
                        
    if($extended) {
        $msgOut['high'] = trimFloat($body['high']);
        $msgOut['low'] = trimFloat($body['low']);
        $msgOut['vol_base'] = trimFloat($body['vol_base']);
        $msgOut['vol_quote'] = trimFloat($body['vol_quote']);
    }
            
    return $msgOut;
}

function tickerEx($pairid, $a, $headers, $body, $b) {
    return ticker($pairid, $a, $headers, $body, $b, true);
}

function orderBook($pairid, $a, $headers, $body, $b) {
    global $pdo;
    
    $task = array(
        ':pairid' => $pairid
    );
                
    $sql = 'SELECT base_prec, quote_prec
            FROM spot_markets
            WHERE pairid = :pairid';
            
    $q = $pdo -> prepare($sql);
    $q -> execute($task);
    $row = $q -> fetch(PDO::FETCH_ASSOC);
                        
    $basePrec = $row['base_prec'];
    $quotePrec = $row['quote_prec'];
                        
    // Build message
                        
    $priceDec = new Decimal($body['price']);
    $amountDec = new Decimal($body['amount']);
                        
    return [
        'pair' => $pairid,
        'amount' => $amountDec -> toFixed($basePrec),
        'price' => $priceDec -> toFixed($quotePrec),
        'side' => $body['side']
    ];
}

// ----- Private events handlers -----

function myOrders($uid, $a, $headers, $body, $b) {
    $msgOut = [
        'event' => $headers['event']
    ];
            
    if(isset($headers['pairid'])) $msgOut['pair'] = $headers['pairid'];
    
    if(isset($body['obid'])) $msgOut['obid'] = $body['obid'];
    if(isset($body['side'])) $msgOut['side'] = $body['side'];
    if(isset($body['type'])) $msgOut['type'] = $body['type'];
    if(isset($body['time'])) $msgOut['time'] = $body['time'];
    if(isset($body['time_in_force'])) $msgOut['time_in_force'] = $body['time_in_force'];
    if(isset($body['base'])) $msgOut['base'] = $body['base'];
    if(isset($body['quote'])) $msgOut['quote'] = $body['quote'];
    if(isset($body['quote_prec'])) $msgOut['quote_prec'] = $body['quote_prec'];
    if(isset($body['price'])) $msgOut['price'] = trimFloat($body['price']);
    if(isset($body['amount'])) $msgOut['amount'] = trimFloat($body['amount']);
    if(isset($body['total'])) $msgOut['total'] = trimFloat($body['total']);
    if(isset($body['stop'])) $msgOut['stop'] = trimFloat($body['stop']);
    if(isset($body['status'])) $msgOut['status'] = trimFloat($body['status']);
    if(isset($body['triggered'])) $msgOut['triggered'] = trimFloat($body['triggered']);
    if(isset($body['filled'])) $msgOut['filled'] = trimFloat($body['filled']);
    if(isset($body['reason'])) $msgOut['reason'] = trimFloat($body['reason']);
            
    return $msgOut;
}

function myTrades($u, $a, $headers, $body, $bind) {
    $role = 'maker';
    if($bind['res'] == 'taker_uid') $role = 'taker';
    
    return [
        'time' => $body['time'],
        'price' => trimFloat($body['price']),
        'amount' => trimFloat($body['amount']),
        'total' => trimFloat($body['total']),
        'side' => $body[$role.'_side'],
        'pair' => $headers['pairid'],
        'obid' => $body[$role.'_obid'],
        'role' => strtoupper($role),
        'fee' => trimFloat($body[$role.'_fee'])
    ];
}

?>