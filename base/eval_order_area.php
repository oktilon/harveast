<?php
$init = time();
require_once dirname(dirname(dirname(__FILE__))) . '/cron.php';

$tm_mark = $init;
function timeMark($cap) {
    global $tm_mark;
    $tm = time();
    $dt = $tm - $tm_mark;
    echo "$cap \033[1;36m{$dt}\033[0m\n";
    $tm_mark = $tm;
}

function timeReset() {
    global $tm_mark;
    $tm_mark = time();
}

$itBeg = 0;
$itCnt = 0;
function itemTime() {
    global $itBeg, $itCnt;

    if($itBeg) {
        $dur = time() - $itBeg;
        $ppt = $dur ? intval(round($itCnt/$dur, 0)) : $itCnt;
        printf(" %d sec (%d pt/sec)", $dur, $ppt);
    }
    $itBeg = time();
}

$fast_mode = false;
$ord_only = false;
while($PM->args) {
    $cmd = array_shift($PM->args);
    switch($cmd) {
        case '-f':
            $fast_mode = true;
            break;
        case '-o':
            $ord_only = true;
            break;
        default:
            if($ord_only === true) $ord_only = intval($cmd);
    }
}


$add = '';
if($fast_mode) {
    $add = "_f";
}
if(is_bool($ord_only)) {
    $ord_only = 0;
} else {
    $add = "_o($ord_only)";
}

InfoPrefix(__FILE__, $add);
Info(sprintf('Started (%u)', getmypid()));

// $echo = json_encode($tmStart, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
try {
    $lckFile  = str_replace('.php', "{$add}.lck", $_SERVER['SCRIPT_FILENAME']);

    if($ord_only == 0) {
        if(!PageManager::pidLock($lckFile, 10000, ADMIN_CHAT)) die();
    }

    $sBp = substr(basename($_SERVER['PHP_SELF']), 0, -4);
    $esc = Escalation::get($sBp);

    $fLog = $fast_mode ? WorkOrder::FLAG_ORDER_LOG_FAST : WorkOrder::FLAG_ORDER_LOG;
    $fChk = $fLog | WorkOrder::FLAG_ORDER_AREA | ($fast_mode ? WorkOrder::FLAG_ORDER_AREA_FAST : 0);

    $flt = [
        "(flags & $fChk) = $fLog",
        'with_lines'
    ];
    if($ord_only) {
        $flt[] = ["id = :i", 'i', $ord_only];
    }
    /**
     * @var WorkOrder[]
     */
    $orders = WorkOrder::getList($flt, 'd_beg, car');
    //echo "DB ERR : {$DB->error}\n";

    // timeMark('WorkOrder::getList');

    if (!$orders) {
        Info('No ready orders. ' . $DB->error);
    }

    $ord_cnt = count($orders);
    Info("Ready orders count = $ord_cnt");

    $rows = [];
    $total = 0;
    $info = '';

    // select ST_AsText() FROM order_log_line WHERE log_id=1

    /*
        select id, log_id,
        to_timestamp(dtb)::time,
        to_timestamp(dte)::time,
        ST_NumPoints(pts),
        St_AsText(ST_PointN(pts,1)),
        St_AsText(ST_PointN(pts,-1)),
        ST_AsText(pts)
        from order_log_line WHERE log_id=1
        ORDER BY dtb
    */
    //echo json_encode($ord->getSimple(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    foreach($orders as $iRow => $ord) { // По каждому наряду
        $ord_line = null;
        foreach ($ord->lines as $line) {
            if($line->tech_op->isFieldOperation() && !$ord_line) {
                $ord_line = $line;
                break;
            }
        }
        if(!$ord_line) {
            $ord->finalAreaNoFldWork($fast_mode);
            Info(sprintf("Ord: %d without fieldworks [0x%X]", $ord->id, $ord->flags));
            continue;
        }

        if($ord_line->tech_cond->width == 0) {
            $ord->finalAreaNoWidth($fast_mode);
            Info(sprintf(
                "Ord: %d without width (TOC:%d) [0x%X]"
                , $ord->id
                , $ord_line->tech_cond->id
                , $ord->flags
            ));
            continue;
        }

        printf("OID:%d, TOP:%d(%d), CAR_MDL:%d, TR_MDL:%d, Wd:%d\n",
            $ord->id,
            $ord_line->tech_op->id,
            $ord_line->tech_cond->id,
            $ord->car->model->id,
            $ord_line->trailer->id,
            $ord_line->tech_cond->width
        );

        $dbl_track = $ord->isDoubleTrack();

        echo "Read logs ...";
        $logs = OrderLog::getControl($ord);
        echo ' ' . count($logs) . " readed\n";

        $stm = 0;
        $dst = 0;
        $area = 0;
        foreach($logs as $log) {
            $log->top_wd = $ord_line->tech_cond->width;
            if($log->canEvalArea()) {
                echo "eval area for log {$log->id}\n";
                $stm += $log->tm_stay;
                $a = $log->evalArea($dbl_track, $fast_mode);
                $err = OrderLog::$error;
                $area += $a;
                Info("{$ord->id} work in {$log->geo} [L:$log->id] = $a $err");
                $dbg = PageManager::popDebug();
                if($dbg) foreach($dbg as $ln) echo "$ln\n";
            } else {
                echo "skip area for log {$log->id}\n";
                $dst += $log->sumDst();
            }
        }
        $ord->finishArea($dst, $fast_mode);

        $ln_reloc = null;
        foreach ($ord->lines as $line) {
            if($line->tech_op->isRelocation()) {
                $ln_reloc = $line;
                break;
            }
        }
        if(!$ln_reloc) {
            // create it
        } else {
            if(!$ln_reloc->tech_op->isRelocationFar() && $dst > 20000) {
                // change techop
            }
            if($ln_reloc->tech_op->isRelocationFar() && $dst <= 20000) {
                // change techop
            }
        }

        $dst = number_format($dst / 1000, 2, ",", " ");
        $stm = gmdate("H:i:s", $stm);

        Info(sprintf(
            "Ord: %d area=%0.2f, move=%s km, stay=%s [0x%X]"
            , $ord->id
            , $area
            , $dst
            , $stm
            , $ord->flags
        ));
    }
} catch(Exception $e) {
    echo PHP_EOL;
    Info('EvalArea Exception : ' . $e->getMessage());
    print_r($e->getTrace());
}
$esc->finish();
if($ord_only == 0) PageManager::pidUnLock();
$dt = time() - $init;
Info("Ended within $dt sec., Orders: $ord_cnt, Messages: $total");