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
        //file_put_contents("/var/www/html/public/base/point_".$oid."_".date("Y-m-d").".txt", "\nord ----- ".print_r($ord, 1), FILE_APPEND);

        $dbl_track_radius = $DB->prepare("SELECT id, radius FROM dbl_track_radius WHERE techops_id = ".$ord->tech_op->id)->execute_row();
        if(isset($dbl_track_radius['id']))
        {
            $x = str_replace(",",".",$msg->pos->x);
            $y = str_replace(",",".",$msg->pos->y);
            $st_astext = $PG->prepare("SELECT st_astext(st_envelope(st_buffer(st_point(".$x.", ".$y.")::geography, 25)::geometry)) AS p")->execute_row();
            if(isset($st_astext['p']))
            {
                $st_astext['p'] = str_replace("POLYGON((", "", $st_astext['p']);
                $st_astext['p'] = str_replace("))", "", $st_astext['p']);
                $st_astext = explode(",", $st_astext['p']);
                $pMin = explode(" ", $st_astext[0]);
                $pMax = explode(" ", $st_astext[2]);
                file_put_contents("/var/www/html/public/base/point_".$oid."_".date("Y-m-d").".txt", "\nsql ----- ".print_r("SELECT *
                                                FROM (SELECT *
                                                        FROM gps_points 
                                                        WHERE id=966
                                                            AND ST_X(pt) BETWEEN ".$pMin[0]." AND ".$pMax[0]."
                                                            AND ST_Y(pt) BETWEEN ".$pMin[1]." AND ".$pMax[1]."
                                                            AND dt BETWEEN ".strtotime($ord->d_beg->format('Y-m-d H:i:s'))." AND ".strtotime($ord->d_end->format('Y-m-d H:i:s')).") AS sub
                                                WHERE ST_DWithin(sub.pt::geography, ST_GeogFromText('POINT (".$x." ".$y.")'), ".$dbl_track_radius['radius'].", false);", 1), FILE_APPEND);
                /*$st_astext = $PG->prepare("SELECT *
                                                FROM (SELECT *
                                                        FROM gps_points 
                                                        WHERE id=966
                                                            AND ST_X(pt) BETWEEN ".$pMin[0]." AND ".$pMax[0]."
                                                            AND ST_Y(pt) BETWEEN ".$pMin[1]." AND ".$pMax[2]."
                                                            AND dt BETWEEN ".strtotime($ord->d_beg->format('Y-m-d H:i:s'))." AND ".strtotime($ord->d_end->format('Y-m-d H:i:s')).") AS sub
                                                WHERE ST_DWithin(sub.pt::geography, ST_GeogFromText('POINT (".$x." ".$y.")'), ".$dbl_track_radius['radius'].", false);")->execute_all();*/

            }
        }
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