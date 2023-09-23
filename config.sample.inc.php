<?php

define('LOG_LEVEL', 0);

define('AMQP_HOST', 'localhost');
define('AMQP_PORT', 5672);
define('AMQP_USER', 'core.rest-api-gw');
define('AMQP_PASS', 'password');

define('HTTP_BIND_ADDR', '0.0.0.0');
define('HTTP_BIND_PORT', 8080);

/* Debug mode
 * true: show all backend errors to user
 * false: show "Internal Server Error"
 */
define('DEBUG_MODE', false);

?>