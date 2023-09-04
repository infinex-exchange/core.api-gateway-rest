<?php

function trimFloat($float) {
    if($float === '0') return $float;
    if(strpos($float, '0.') !== 0)
        $float = ltrim($float, '0');
    return rtrim(rtrim($float, '0'), '.');
}

function validatePairName($pair) {
    return preg_match('/^[A-Z0-9]{1,32}\/[A-Z0-9]{1,32}$/', $pair);
}

function validateApiKey($apiKey) {
    return preg_match('/^[a-f0-9]{64}$/', $apiKey);
}

function apiLogin($apiKey, $id) {
    global $pdo;
    
    $task = array(
        ':api_key' => $apiKey
    );
    
    $sql = 'SELECT uid
        FROM sessions
        WHERE api_key = :api_key';
    
    $q = $pdo -> prepare($sql);
    $q -> execute($task);
    $row = $q -> fetch(PDO::FETCH_ASSOC);
    
    if(! $row) return 0;
    return $row['uid'];
}

?>