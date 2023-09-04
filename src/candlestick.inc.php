<?php

function getLatestCandleStick($pair, $resolution) {
    global $pdo, $STREAMS;
    
    $task = array(
        ':pairid' => $pair,
        ':interval' => $STREAMS['candleStick']['arguments'][$resolution]['interval']
    );
    
    $sql = 'WITH latest AS (
                SELECT bucket,
                       open,
                       high,
                       low,
                       close,
                       volume,
                       time_bucket(:interval, CURRENT_TIMESTAMP(6)) AS current_bucket
                FROM spot_candlestick_'.$STREAMS['candleStick']['arguments'][$resolution]['view_suffix'].'
                WHERE pairid = :pairid
                ORDER BY bucket DESC
                LIMIT 1
            )
            SELECT EXTRACT(epoch FROM current_bucket) AS time,
                   CASE WHEN bucket = current_bucket THEN open ELSE close END AS open,
                   CASE WHEN bucket = current_bucket THEN high ELSE close END AS high,
                   CASE WHEN bucket = current_bucket THEN low ELSE close END AS low,
                   close,
                   CASE WHEN bucket = current_bucket THEN volume ELSE 0 END AS volume
            FROM latest';
                        
    $q = $pdo -> prepare($sql);
    $q -> execute($task);
    $row = $q -> fetch(PDO::FETCH_ASSOC);
    
    if(!$row) return NULL;
                        
    return [
        'pair' => $pair,
        'time' => $row['time'],
        'open' => trimFloat($row['open']),
        'high' => trimFloat($row['high']),
        'low' => trimFloat($row['low']),
        'close' => trimFloat($row['close']),
        'volume' => trimFloat($row['volume']) 
    ];
}

function emptyCandleStickForAllPairs() {
    global $subDb, $pusher, $STREAMS;
    
    if(isset($subDb['candleStick']))
        foreach($subDb['candleStick'] as $pairName => $arguments)
                foreach($arguments as $argument => $clientsOfArgument) {
                    $msgOut = getLatestCandleStick($pairName, $argument);
                
                    if($msgOut &&
                        (
                            !isset($STREAMS['candleStick']['arguments'][$argument]['last_bucket'])
                            ||
                            $STREAMS['candleStick']['arguments'][$argument]['last_bucket'] != $msgOut['time']
                        )
                    ) {                    
                        $STREAMS['candleStick']['arguments'][$argument]['last_bucket'] = $msgOut['time'];
                        $streamName = $pairName.'@candleStick/'.$argument;
                        $msgOut = array_merge(['class' => 'data', 'stream' => $streamName], $msgOut);
                        $pusher -> sendToClients($clientsOfArgument, $msgOut);
                    }
                }
}

?>