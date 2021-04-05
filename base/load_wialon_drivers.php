<?php
require_once dirname(__DIR__) . '/html/sess.php';
InfoPrefix(__FILE__);
$time = time();
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$filter = $args ? array_shift($args) : '';
if($filter == '') Info("Started");

$txt = RfidCards::loadFromWialon();

Info($txt);
