<?php

define('LOG_LEVEL', 0);

define('AMQP_HOST', 'localhost');
define('AMQP_PORT', 5672);
define('AMQP_USER', 'core.api-gateway-rest');
define('AMQP_PASS', 'password');

define('DB_HOST', 'localhost');
define('DB_USER', 'core.api-gateway-rest');
define('DB_PASS', 'password');
define('DB_NAME', 'core.api-gateway-rest');

define('HTTP_BIND_ADDR', '0.0.0.0');
define('HTTP_BIND_PORT', 8080);

/* Debug mode
 * true: show all backend errors to user
 * false: show "Internal Server Error"
 */
define('DEBUG_MODE', false);

?>