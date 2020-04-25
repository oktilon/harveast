<?php
require_once dirname(__DIR__) . '/html/sess.php';
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$api = new WialonApi();

$c = 919;
$b = new DateTime('2020-04-25 07:37:00');
$e = new DateTime('2020-04-25 07:38:00');

echo json_encode([
    'c' => $c,
    'b' => $b,
    'e' => $e,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
$lst = $api->getMessages($c, $b, $e);
echo "Count = " . count($lst) . "\n";
echo json_encode($lst, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "Err:\n";
echo json_encode(WialoApi::$m_err, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "Res:\n";
echo json_encode(WialoApi::$m_res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;