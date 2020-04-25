<?php
require_once dirname(__DIR__) . '/html/sess.php';
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}
$tmB = $args ? array_shift($tm) : '07:37:00';
$tmE = $args ? array_shift($tm) : '07:38:00';

$api = new WialonApi();

$c = 919;
$b = new DateTime('2020-04-25 07:37:00', WialonApi::getTimezone());
$e = new DateTime('2020-04-25 07:38:00', WialonApi::getTimezone());

echo json_encode([
    'c' => $c,
    'b' => $b,
    'e' => $e,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
$lst = $api->getMessages($c, $b, $e);
echo "Count = " . count($lst) . "\n";
foreach($lst as $msg) {
    $tm = $msg->t;
    $dt = date('Y-m-d H:i:s', $tm);
    echo "$tm => $dt\n";
}