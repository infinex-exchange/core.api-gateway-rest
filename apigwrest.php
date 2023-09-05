#!/usr/bin/env php
<?php

require __DIR__.'/config.inc.php';
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/src/App.php';

$app = new App();
$app -> run();

?>