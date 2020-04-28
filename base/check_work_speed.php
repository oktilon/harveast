<?php
require_once dirname(__DIR__) . '/html/sess.php';
InfoPrefix(__FILE__);
$_REQUEST['obj'] = '{"p":1}';

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
Info(sprintf('Started (%u)', getmypid()));

try {

    $lckFile  = str_replace('.php', "_{$part_div}.lck", $_SERVER['SCRIPT_FILENAME']);

    if(!$ord_only) {
        if(!GlobalMethods::pidLock($lckFile, 10000, ADMIN_CHAT, ["check_work_speed_{$part_div}", 'Ord:'])) die();
    }

    $now = date('Y-m-d H:i:s', time() + 1800);

    $flt = [
        ['(o.flags & :flog) = 0', 'flog', WorkOrder::FLAG_ORDER_LOG],
        'o.gps_id > 0',
        ['o.d_beg <= :d', 'd', $now],
        ['d.tm.d_beg <= :d', 'd', $now],
        'join_devices'
    ];
    if($part_div > 0) {
        $eq = $part_div - 1;
        $flt[] = "o.gps_id % {$part_del} = {$eq}";
    }
    if($ord_only) $flt[] = 'o.id IN(' . implode(',', $ord_only) . ')';

    $orders = WorkOrder::getList($flt, 'd.tm, o.d_beg, o.car');

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
        $cid = $ord->car->id;
        $fid = $ord->firm->id;
        $tid = $ord->car->ts_type->id;
        $fast = $ord->isFinalPoint() ? false : $ord->isFastPoint();

        $msg_oid = sprintf("%d (%d of %d)", $oid, $iRow+1, $ord_cnt);

        echo PHP_EOL;
        if($info) Info($info);
        $info = '';

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
            $itBeg = 0;
            continue;
        }

        printf("%04d ORD:%06d ", $iRow + 1, $oid);

        $agl = AggregationList::byOrderLine($ord, $ord_line);
        // echo json_encode($agl, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        // die();

        $oEnd = intval($ord->d_end->format('U'));

        $ord->resetLines();

        $logs = OrderLog::getControl($ord);

        $points = $ord->getPoints();

        $pnt_prev = null;

        $pnt_count = count($points);
        $prc_count = 0;

        foreach($points as $pnt) {
            //if($pnt->log_id) { echo 's'; continue; }

            /** @var OrderLog */
            $log  = null;
            $plog = null;
            // find proper log
            $change = $pnt->geo_id != $ord->chk_geo;
            // $mdt = OrderLog::dateFromUTC($pnt->dt)->format('H:i:s');
            // printf("%s %04d %s ", $mdt, $pnt->geo_id, $change ? 'C' : '-');
            foreach($logs as $it) {
                if($it->geo == $pnt->geo_id) $log = $it;
                if($change && $it->geo == $ord->chk_geo) $plog = $it;
            }
            // printf("L:%04d:%04d, PL:%04d:%04d",
            //     $log ? $log->id : 0,
            //     $log ? $log->geo : 0,
            //     $plog ? $plog->id : 0,
            //     $plog ? $plog->geo : 0);
            if($log == null) {
                $log = new OrderLog($ord, $ord_line, $pnt, $agl);
                $log->save(false);
                $logs[] = $log;
                echo "[{$log->geo}]";
            } else {
                echo ".";
            }

            // Add event to counters + evaluate wrong speed
            $alert = $log->addMessage($pnt, $pnt_prev, $ord, $plog);
            $prc_count++;
            // echo "\n";

            $pnt_prev = $pnt;
            $ord->chk_geo = $pnt->geo_id;
            $ord->chk_rep = $log->rep_mode;
            $ord->chk_dt  = $pnt->dt;
        }
        $mdt = OrderLog::dateFromUTC($ord->chk_dt)->format('Y-m-d H:i:s');
        // printf("Fin {$ord->id} at $mdt fin = %d\n", $final);
        foreach($logs as $it) {
            //printf("Save L:%d(G:%d) = ", $it->id, $it->geo);
            $it->evaluate();
            $r = $it->save();
            printf(" log:%d  \n", $it->id);
        }
        $ord->updateCheck(!$fast, 0, $fast);
        $info = sprintf(
            "Ord:%s till %s, proceed: %d of %d pts. %s [0x%X]"
            , $msg_oid
            , $mdt
            , $prc_count
            , $pnt_count
            , ($fast ? ' (fast)' : '')
            , $ord->flags
        );
    }
    echo PHP_EOL;
    if($info) Info($info);
} catch(Exception $e) {
    echo PHP_EOL;
    Info('WorkSpeed Exception : ' . $e->getMessage());
    print_r($e->getTrace());
}
if(!$ord_only) {
    GlobalMethods::pidUnLock();
}
$dt = time() - $init;
Info("Ended within $dt sec., Orders: $ord_cnt, Messages: $total");