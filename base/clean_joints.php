<?php
require_once dirname(__DIR__) . '/html/sess.php';
InfoPrefix(__FILE__);

$t = time();
Info('Run query');
$q = $DB->prepare("SELECT count(*) from gps_order_log gol
                left join gps_joint_items gji on log_id = gol.id
                left join gps_joint gj on gj.id = gji.jnt_id
                -- set gol.flags = gol.flags & ~8
                where gol.flags & 8 and (gj.flags & 2 OR gj.id IS NULL)")
    ->execute();
$dt = time() - $t;
if($q) {
    $cleaned = $DB->affectedRows();
    Info("Query ($dt sec.) finished. Updated $cleaned rows.");
} else {
    Info("Query ($dt sec.) error: " . $DB->error);
}