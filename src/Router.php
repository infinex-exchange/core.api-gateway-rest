<?php

use Infinex\Exceptions\Error;

class Router {
    private $loop;
    private $log;
    private $pdo;
    private $routes;
    private $refreshTimer;
    
    function __construct($loop, $log, $pdo) {
        $this -> loop = $loop;
        $this -> log = $log;

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
    }
    
    public function stop() {
        $this -> loop -> cancelTimer($this -> refreshTimer);
        $this -> log -> info('Stopped API router');
    }
    
    private function reloadRoutes() {
        try {
            $tmpRoutes = [];
            $count = 0;
            
            $q = $this -> pdo -> query('SELECT * FROM routes');
            
            while($row = $q -> fetch()) {    
                $exploded = explode('/', $row['path']);
                $first = array_shift($exploded);
                $expCount = count($exploded);
                
                if($expCount == 0 || in_array('', $exploded) || $first != '') {
                    $this -> log -> warn('Ignoring invalid route: '.$row['path']);
                    continue;
                }
                
                $routes = &$tmpRoutes;
                foreach($exploded as $k => $v) {
                    if(!isset($routes[$v]))
                        $routes[$v] = [
                            'service' => null,
                            'sub' => []
                        ];
                    
                    if($k == $expCount - 1)
                        $routes[$v]['service'] = $row['service'];
                    
                    $routes = &$routes[$v]['sub'];
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
        $service = null;
        $path = '';
        foreach($exploded as $part) {
            if(isset($routes[$part])) {
                if($routes[$part]['service']))
                    $service = $routes[$part]['service'];
                $routes = $routes[$part]['sub'];
            }
            else
                $path .= '/'.$part;
        }
            
        if(!$service)
            throw new Error('INVALID_ENDPOINT', 'Invalid endpoint', 404);
        
        return [
            'service' => $service,
            'path' => $path
        ];
    }
}

?>