<?php
require_once dirname(dirname(dirname(__FILE__))) . '/cron.php';
$init = time();

$ord_only = [];
$ord_list = false;
$part_run = false;
$part_div = 0;
$part_del = 8;
while($PM->args) {
    $cmd = array_shift($PM->args);
    switch($cmd) {
        case '-o':
            $ord_list = true;
            $part_run = false;
            break;

        case '-p':
            $part_run = true;
            $ord_list = false;
            break;

        default:
            $i = intval($cmd);
            if($ord_list && $i) {
                $ord_only[] = $i;
            }
            if($part_run && $i) {
                $part_div = $i;
                $part_run = false;
            }
            break;
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
Info(sprintf('Started (%u)', getmypid()));

try {

    $lckFile  = str_replace('.php', "_{$part_div}.lck", $_SERVER['SCRIPT_FILENAME']);

    if(!$ord_only) {
        if(!PageManager::pidLock($lckFile, 10000, ADMIN_CHAT, ["eval_order_points_{$part_div}", 'Ord:'])) die();
    }

    $f = WorkOrder::FLAG_ORDER_LOG | WorkOrder::FLAG_ORDER_PTS;

    $now = date('Y-m-d H:i:s', time() + 1800);

    $flt = [
        "(flags & $f) = 0",
        ['d_beg > :b', 'b', '2019-05-25'],
        ['d_beg <= :d', 'd', $now],
        'with_lines'
    ];
    if($part_div > 0) {
        $eq = $part_div - 1;
        $flt[] = "gps_id % {$part_del} = {$eq}";
    }
    if($ord_only) $flt[] = 'id IN(' . implode(',', $ord_only) . ')';
    //echo json_encode($flt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $orders = WorkOrder::getList($flt, 'chk_pt, d_beg, car');
    //echo "DB ERR : {$DB->error}\n";


    if (!$orders) {
        Info('No orders. ' . $DB->error);
    }

    $ord_cnt = count($orders);
    Info("Orders count $ord_cnt");

    $rows = [];
    $total = 0;
    $info = '';
    /** @var WorkOrder **/
    $ord = null;
    foreach($orders as $iRow => $ord) { // По каждому наряду

        $total_time = time() - $init;
        if($total_time > 7200) {
            throw new Exception('Timeout (More 2 hours)');
        }


        $oid = $ord->id;
        $iid = $ord->gps_id;
        $cid = $ord->car->id;
        $fid = $ord->firm->id;
        $tid = $ord->car->ts_type->id;
        $recalc = $ord->isRecalculated();
        if($ord->isWaitForStart()) {
            if($iid != $ord->car->device->gps_id) {
                $ord->gps_id = $ord->car->device->gps_id;
                $iid = $ord->gps_id;
            }
            $ord->resetWait();
        }

        $msg_oid = sprintf("%d (%d of %d)", $oid, $iRow+1, $ord_cnt);
        echo PHP_EOL;

        if($info) Info($info);
        $info = '';

        if(!$iid) {
            $ord->finalNoGps();
            Info(sprintf("Ord: $msg_oid no gps (car=$cid, device={$ord->car->device->id}) [0x%X]", $ord->flags));
            continue;
        }

        $ord_line = null;
        foreach ($ord->lines as $line) {
            if($line->tech_op->isFieldOperation() && !$ord_line) {
                $ord_line = $line;
                break;
            }
        }

        if(!$ord_line) {
            $ord->finalNoFldWork();
            Info(sprintf("Ord: $msg_oid without fieldworks [0x%X]", $ord->flags));
            continue;
        }


        printf("%04d ORD:%06d ", $iRow + 1, $oid);

        $workIn   = [];

        $oBeg = intval($ord->d_beg->format('U'));
        $tBeg = max($oBeg, $ord->chk_pt - 3600); // 3600-gap

        $oEnd = intval($ord->d_end->format('U'));
        $oLimit = $oEnd + 1800; // Half hour more

        $oFast = $oEnd - 7200; // Two hours before end

        $tChk = $tBeg + 7200; // 2 hours max
        if($ord_list) $tChk = $oLimit; // Whole order for manual start
        $tEnd = min($oLimit, $tChk, time());
        if($recalc) {
            $tEnd = $oLimit; // on recalc - full order
        }

        if($tBeg == $ord->chk_pt) $tBeg++;
        if($tBeg >= $tEnd) {
            echo "Beg gte End";
            continue;
        }

        $workIn = $ord->enumPointsGeo();

        $ptcs = CarLogPoint::initPointsCache($oid, $tBeg, $tEnd);
        $final = false;
        $fast  = false;

        echo date('Y-m-d H:i:s', $tBeg). ' - ' . date('Y-m-d H:i:s', $tEnd) . ", ptc={$ptcs}, ";

        $inf = '';
        $messages = OrderLog::getMessages($iid, $tBeg, $tEnd);
        if (!$messages) {
            if(OrderLog::$error) {
                echo 'err';
                Info("Ord:$msg_oid, Gps:$iid, WialonError:" . OrderLog::$error);
            } else {
                // if(OrderLog::silentEnough($tBeg)) {
                //     $ord->finalNoMessages();
                //     Info("finish silent");
                //     continue;
                // }
                $min = min($oEnd, time());
                while($tEnd < $min) {
                    $tEnd += 14400;
                    $len = intval(($tEnd - $tBeg) / 3600.0);
                    $messages = OrderLog::getMessages($iid, $tBeg, $tEnd);
                    if($messages) break;
                }
            }
        }
        if(!$messages) {
            $dxt = time() - $oEnd;
            $inf  = sprintf("till %s", date('Y-m-d H:i:s', $tEnd));
            if($dxt > 3600) { // 1 hour after order end
                if($ptcs == 0) {
                    $minutes = round($dxt / 60, 0);
                    $inf .= " after $minutes min (no-data) (fin)";
                    $ord->finalNoMessages();
                } else {
                    $final = true;
                    $fast  = false;
                    $inf   .= ' (fin)';
                }
            }
            if($final) $ord->updateCheckPoints($final, $fast);
            $info = sprintf(
                "Ord:%s (gps:%d) no messages %s [0x%X]"
                , $msg_oid
                , $iid
                , $inf
                , $ord->flags
            );
            echo 'no messages ' . $inf;
            $itCnt = 0;
            continue;
        } else {
            $itCnt = count($messages);
        }
        $total += $itCnt;

        $msg_prev = null;


        $noFast = count($ord_only) > 0 || (time() - $oEnd) > 14400; // 4 hours after work ends

        $repeat = true;
        $repeat_cnt = 0;
        while($repeat) {

            // cache zones
            $geoZones = ''; //OrderLog::getZonesCache($messages);

            $msg_count = count($messages);
            $new_count = 0;

            echo 'gc=' . count(explode(',',$geoZones)) . ", m={$msg_count} ";
            $repeat = false;

            foreach($messages as $msg) {
                if(CarLogPoint::hasPoint($msg)) {
                    echo "-";
                    continue;
                }
                if($msg->t > $oFast && !$noFast && !$ord->isFastPoint() && !$fast) {
                    $fast = true;
                    echo '(f)';
                }
                if($msg->t > $oEnd) {
                    $final = true;
                    $fast  = false;
                }

                $lst = GeoFence::findPointFieldFast($msg->pos);
                $gid = 0;
                $new_count++;
                foreach($lst as $id) {
                    if(!$gid) $gid = $id;
                    if(in_array($id, $workIn)) {
                        $gid = $id;
                        break;
                    }
                }
                echo $gid ? 'o' : '.';
                $ord->addPoint($msg, $gid);
                $ord->chk_pt  = $msg->t;
                if($final) break;
            }
            $inf = '';
            $stop = false;
            if($new_count == 0) {
                $tEnd = min($oEnd, time());
                $messages = OrderLog::getMessages($iid, $tBeg, $tEnd);
                $next_cnt = count($messages);
                if($next_cnt > $msg_count && $repeat_cnt < 1) {
                    $repeat = true;
                    $repeat_cnt++;
                }
            }
        }
        if($new_count == 0 && $ptcs > 0 && time() > $oFast && !$final && !$noFast) {
            $fast = true;
        }
        if(!$final) {
            $dxt = time() - $oEnd;
            if($dxt > 3600) { // 1 hour
                $minutes = round($dxt / 60, 0);
                if($ptcs == 0 && $new_count == 0) {
                    $inf = "no messages after $minutes minutes (fin)";
                    $ord->finalNoMessages();
                    $stop = true;
                }
                $final = true;
                $fast = false;
            }
        }
        if(!$stop) $ord->updateCheckPoints($final, $fast);
        $info = sprintf(
            "Ord:%s, pts:%d, new:%d, till %s%s %s [0x%X]"
            , $msg_oid
            , $msg_count
            , $new_count
            , $ord->chk_pt ? date('Y-m-d H:i:s', $ord->chk_pt) : '-'
            , $final ? ' (fin)' : ($fast ? ' (fast)' : '')
            , $inf
            , $ord->flags
        );
    }
    echo PHP_EOL;
    if($info) Info($info);
} catch(Exception $e) {
    echo PHP_EOL;
    Info('OrderPoints Exception : ' . $e->getMessage());
    print_r($e->getTrace());
}
if(!$ord_only) {
    PageManager::pidUnLock();
}
$dt = time() - $init;
Info("Ended within $dt sec., Orders: $ord_cnt, Messages: $total");