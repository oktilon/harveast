<?php
require_once dirname(__DIR__) . '/html/sess.php';
$_REQUEST['obj'] = '{"p":1}';
$init = time();

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$ord_only = [];
$part_div = 0;
$part_del = 8;
$cmd = '';
while($args) {
    $arg = array_shift($args);
    if(preg_match('/^\-(\w+)$/', $arg, $m)) {
        $cmd = $m[1];
    } else {
        $i = intval($arg);
        if($cmd != '' && $i) {
            switch($cmd) {
                case 'o':
                    $ord_only[] = $i;
                    break;
                case 'p':
                    $part_div = $i;
                    $cmd = '';
                    break;
            }
        } else {
            $cmd = '';
        }
    }
}

$add = '';
if($part_div > 0) {
    $add = "_{$part_div}";
} elseif(count($ord_only) > 0) {
    $oo = implode(',', $ord_only);
    $add = "_(o:{$oo})";
}

InfoPrefix(__FILE__, $add);

$info_msg = '';
/** @var WorkOrder */
$ord = null;
$oid = 0;
$cid = 0;
$iid = 0;

try {

    $f = WorkOrder::FLAG_ORDER_RECALC;

    $flt = [
        "flags & $f"
    ];
    if($part_div > 0) {
        $eq = $part_div - 1;
        $flt[] = "gps_id % {$part_del} = {$eq}";
    }
    if($ord_only) $flt[] = 'id IN(' . implode(',', $ord_only) . ')';

    $orders = WorkOrder::getList($flt, 'd_beg, car');

    if (!$orders) {
        Info('No orders to recalc');
        die();
    }

    $ord_cnt = count($orders);
    Info("Orders to recalc $ord_cnt");

    $ok = false;
    while($orders) {
        $ord = array_shift($orders);
        $lckFile  = str_replace('.php', "_{$ord->id}.lck", $_SERVER['SCRIPT_FILENAME']);
        $ok = GlobalMethods::pidLock($lckFile);
        if($ok) break;
    }
    if(!$ok) die(); // No free orders to calc

    $oid = $ord->id;
    $iid = $ord->gps_id;
    $cid = $ord->car->id;

    if(!$iid) {
        $ord->finalNoGps();
        throw new Exception("No gps (Device: {$ord->car->device->id})", 1);
    }

    $tBeg = intval($ord->d_beg->format('U'));
    $tEnd = intval($ord->d_end->format('U'));

    echo "Ord: $oid, " . date('Y-m-d H:i:s', $tBeg). ' - ' . date('Y-m-d H:i:s', $tEnd) . " ... ";

    $messages = OrderLog::getMessages($iid, $tBeg, $tEnd);
    if (!$messages) {
        if(OrderLog::$error) {
            $ord->finalWialonError();
            throw new Exception("WialonError: " . OrderLog::$error, 1);
        } else {
            $ord->finalNoMessages();
            throw new Exception("No messages", 1);
        }
    }
    $cnt = count($messages);
    echo "msgs: $cnt\n";

    foreach($messages as $msg) {
        $ok = CarLogPoint::calcPoint($msg, $iid);
        if($ok) echo CarLogPoint::$last_gid ? 'o' : '.';
    }
    $ord->updateCheckPoints(true);
    $info_msg = "Recalculated: $cnt messages.";
} catch(Exception $e) {
    $pref = $e->getCode() === 1 ? '' : 'Exception: ';
    $info_msg = $pref . $e->getMessage();
}

GlobalMethods::pidUnLock();
$dt = time() - $init;
$flg = $ord ? sprintf("[0x%X]", $ord->flags) : '';
Info("Ended within $dt sec., Ord: $oid, Car: $cid, Gpd: $iid, $info_msg $flg");