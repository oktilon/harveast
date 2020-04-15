<?php
require_once dirname(__DIR__) . '/html/sess.php';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$script = $args ? array_shift($args) : '';
if(!$script) die('Usage: php run.php MODULE');

JSON::run($script);