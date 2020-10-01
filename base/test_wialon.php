<?php
require_once dirname(__DIR__) . '/html/sess.php';
$_REQUEST['obj'] = '{"p":1}';

$api = new WialonApi();


echo 'last_query = ' . WialonApi::$last_query . PHP_EOL;
echo 'last_data = ' . WialonApi::$last_data . PHP_EOL;
echo 'error = ' . WialonApi::$error . PHP_EOL;
echo 'reason = ' . WialonApi::$reason . PHP_EOL;
echo 'm_err = ' . WialonApi::$m_err . PHP_EOL;
echo 'm_res = ' . WialonApi::$m_res . PHP_EOL;
