<?php

use Infinex\Exceptions\Error;
use React\Promise;

class Router {
    private $loop;
    private $log;
    private $pdo;
    
    private $routes;
    private $refreshTimer;
    
    function __construct($loop, $log, $pdo) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> pdo = $pdo;

        $this -> log -> debug('Initialized API router');
    }
    
    public function start() {
        $th = $this;
        
        $this -> refreshTimer = $this -> loop -> addPeriodicTimer(
            30,
            function() use($th) {
                $th -> reloadRoutes();
            }
        );
        $this -> reloadRoutes();
        
        $this -> log -> info('Started API router');
        
        return Promise\resolve(null);
    }
    
    public function stop() {
        $this -> loop -> cancelTimer($this -> refreshTimer);
        $this -> log -> info('Stopped API router');
        return Promise\resolve(null);
    }
    
    private function reloadRoutes() {
        try {
            $tmpRoutes = [
                'service' => null,
                'sub' => []
            ];
            $count = 0;
            
            $q = $this -> pdo -> query('SELECT * FROM routes');
            
            while($row = $q -> fetch()) {    
                $exploded = explode('/', $row['path']);
                $first = array_shift($exploded);
                $expCount = count($exploded);
                
                if($expCount == 0 || $first != '') {
                    $this -> log -> warn('Ignoring invalid route: '.$row['path']);
                    continue;
                }
                
                if($exploded[$expCount-1] != '') {
                    $exploded[] = '';
                    $expCount++;
                }
                
                $routes = &$tmpRoutes;
                foreach($exploded as $k => $v) {
                    if($k == $expCount - 1)
                        $routes['service'] = $row['service'];
                        
                    else {
                        if(!isset($routes['sub'][$v]))
                            $routes['sub'][$v] = [
                                'service' => null,
                                'sub' => []
                            ];
                        $routes = &$routes['sub'][$v];
                    }
                }
                
                $count++;
            }
            
            $this -> routes = $tmpRoutes;
            $this -> log -> info("Reloaded $count routes from database");
        }
        catch(\Exception $e) {
            $this -> log -> error('Failed reload routes: '.((string) $e));
        }
    }
    
    public function route($path) {
        $exploded = explode('/', $path);
        array_shift($exploded);
        
        $routes = $this -> routes;
        $service = $routes['service'];
        $path = '';
        $broken = false;
        foreach($exploded as $part) {
            if(!$broken) {
                if(isset($routes['sub'][$part])) {
                    $routes = $routes['sub'][$part];
                    
                    if(isset($routes['service'])) {
                        $service = $routes['service'];
                        $path = '';
                    }
                }
                else
                    $broken = true;
            }
            else {
                if(!$service)
                    break;
                
                $path .= '/'.$part;
            }
        }
        
        if(!$service)
            throw new Error('INVALID_ENDPOINT', 'Invalid endpoint', 404);
        var_dump($service);
        var_dump($path);
        return [
            'service' => $service,
            'path' => $path
        ];
    }
}

?>