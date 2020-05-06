<?php
require_once dirname(__DIR__) . '/html/sess.php';
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

echo " gps_car_type...";
DataIndex::afterUpdate('gps_car_type');
echo "\n spr_firms...";
DataIndex::afterUpdate('spr_firms');
echo "\n gps_carlist...";
DataIndex::afterUpdate('gps_carlist');
echo "\n techops...";
DataIndex::afterUpdate('techops');
echo "\n fixed_assets...";
DataIndex::afterUpdate('spr_fixed_assets');
echo "\n fin\n";