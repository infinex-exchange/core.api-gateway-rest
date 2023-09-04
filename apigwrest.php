#!/usr/bin/env php
<?php

include_once __DIR__.'/config.inc.php';
require __DIR__.'/vendor/autoload.php';
include __DIR__.'/src/utils.inc.php';
include __DIR__.'/src/constants.inc.php';
include __DIR__.'/src/Pusher.inc.php';
include __DIR__.'/src/server_events.inc.php';
include __DIR__.'/src/sub_unsub.inc.php';
include __DIR__.'/src/candlestick.inc.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

// Debug mode

$debug = false;
if(defined('DEBUG_MODE') || (isset($argv[1]) && $argv[1] == '-d'))
    $debug = true;

// Global variables

$subDb = null;
$authDb = null;
$loop = null;
$rmq = null;
$channel = null;
$pdo = null;
$pusher = null;
$webSock = null;
$server = null;

while(true) {
    try {
        // ----- connDb, subDb and authDb -----
        if($debug) echo "Flushing subDb and authDb\n";
        $subDb = array();
        $authDb = array(); 
        
        // ----- Init event loop -----
        if($debug) echo "Initializing event loop\n";
        
        if($loop !== null)
            $loop -> stop();
        
        $loop = React\EventLoop\Factory::create();

        // ----- Init RabbitMQ -----
        if($debug) echo "Initializing RMQ connection\n";
        
        try {
            if($rmq !== null)
                $rmq -> close();
        }
        catch(Exception $e) {
        }
        
        $rmq = null;
        $channel = null;
    
        while(true) {
            try {
                $rmq = new AMQPStreamConnection(RMQ_HOST, RMQ_PORT, RMQ_USER, RMQ_PASS);
                $channel = $rmq -> channel();
                $channel -> exchange_declare('outEvents', AMQPExchangeType::HEADERS, false, true); // durable
                $hostname = gethostname().'_'.getmypid();
                
                foreach($STREAMS as $baseStreamName => $x) {
                    $queueName = $baseStreamName.'_'.$hostname;
                    $channel -> queue_declare($queueName, false, false, false, true); // auto delete
                    $channel -> basic_consume($queueName, "ct_$queueName", false, false, false, false, 'onEvent');
                }
                
                break;
            }
            catch(Exception $e) {
                echo 'Exception: ' . $e -> getMessage() . PHP_EOL;
                sleep(1);
            }
        }

        $loop -> addPeriodicTimer(0.0001, function () use ($channel) {
            $channel -> wait(null, true);
        });
        
        // ----- Init PostgreSQL connection -----
        if($debug) echo "Connecting to PostgreSQL\n";

        $pdo = null;

        while(true) {
            try {
                $pdo = new PDO('pgsql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
                $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo -> setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                break;
            }
            catch(Exception $e) {
                echo 'Exception: ' . $e -> getMessage() . PHP_EOL;
                sleep(1);
            }
        }
        
        // ----- Init WebSockets server -----
        if($debug) echo "Initializing WS server\n";

        while(true) {
            try {
                if($webSock !== null)
                    $webSock -> close();
                
                $pusher = new Pusher;
                $webSock = new React\Socket\Server(WS_BIND_ADDR.':'.WS_BIND_PORT, $loop);
                $server = new Ratchet\Server\IoServer(
                    new Ratchet\Http\HttpServer(
                        new Ratchet\WebSocket\WsServer(
                            $pusher
                        )
                    ),
                    $webSock
                );
                break;
            }
            catch(Exception $e) {
                echo 'Exception: ' . $e -> getMessage() . PHP_EOL;
                sleep(1);
            }
        }

        if($debug) echo "Initializing timers\n";
        
        // ----- EVERY 30 SEC: database ping -----
        $loop->addPeriodicTimer(30, function () {
            global $pdo, $debug;
    
            if($debug) echo "Ping database\n";
            $pdo -> query('SELECT 1');
        });
        
        // ----- EVERY 30 SEC: Kill idle connections -----
        $loop->addPeriodicTimer(15, function () {
            global $pusher;
            $pusher -> killIdleConnections();
        });

        // ----- EVERY 10 SEC: Debug databases print -----
        if($debug) $loop->addPeriodicTimer(10, function () {
            global $subDb, $authDb;
            echo "----- Every 10 seconds database print ------\n".
                 "SUBDB:\n".json_encode($subDb, JSON_PRETTY_PRINT)."\n".
                 "--------------------\n".
                 "AUTHDB:\n".json_encode($authDb, JSON_PRETTY_PRINT)."\n".
                 "--------------------\n";
        });

        // ----- EVERY 2 SEC: empty candleSticks for all pairs -----
        $loop->addPeriodicTimer(2, 'emptyCandleStickForAllPairs');

        // ----- Main loop -----
        if($debug) echo "Starting event loop\n";
        $loop->run();
    }
    catch(Exception $e) {
        echo 'Exception: ' . $e -> getMessage() . PHP_EOL;
    }
}
?>