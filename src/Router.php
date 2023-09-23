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
            
            $q = $this -> pdo -> query('SELECT * FROM routes');
            $count = 0;
            while($row = $q -> fetch()) {    
                $exploded = explode('/', $row['path'])
                $count = count($exploded);
                
                if($count < 2 || $exploded[0] != '')) {
                    $this -> log -> warn('Ignoring invalid route: '.$row['path']);
                    continue;
                }
                
                for($i = 1; $i < $count; $i++) {
                    if(!isset($this -> routes[)
                    $tmpRoutes[ $row['path'] ] = $row['module'];
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
            
        if(count($exploded) < 2 || !isset($this -> routes[ $exploded[1] ]))
            throw new Error(404, 'INVALID_ENDPOINT', 'Invalid endpoint');
            
            $body['path'] = '/'.implode('/', array_slice($pathExploded, 2));
            $target = $this -> routes[ $pathExploded[1] ];
            
            return [
                'method' => $target,
                'body' => $body
            ];
        }
    }
}

?>