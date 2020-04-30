<?php
$init = time();
require_once dirname(__DIR__) . '/html/sess.php';
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$ord_only = [];
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
            }
        } else {
            $cmd = '';
        }
    }
}

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

$add = '';
if($ord_only) {
    $add = "_ords";
}

InfoPrefix(__FILE__, $add);
Info(sprintf('Started (%u)', getmypid()));

// $echo = json_encode($tmStart, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
try {
    $lckFile  = str_replace('.php', "{$add}.lck", $_SERVER['SCRIPT_FILENAME']);

    if(!$ord_only) {
        if(!GlobalMethods::pidLock($lckFile, 10000, ADMIN_CHAT)) die();
    }

    $fLog = WorkOrder::FLAG_ORDER_LOG;
    $fChk = $fLog | WorkOrder::FLAG_ORDER_AREA;

    $flt = [
        "(flags & $fChk) = $fLog",
        'with_lines'
    ];
    if($ord_only) {
        $lst = implode(',', $ord_only);
        $flt[] = "id IN{$lst}";
    }
    /** @var WorkOrder[] */
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

    foreach($orders as $iRow => $ord) { // По каждому наряду
        $ord_line = null;
        if(!$ord->tech_op->isFieldOperation()) {
            $ord->finalAreaNoFldWork();
            Info(sprintf("Ord: %d without fieldworks [0x%X]", $ord->id, $ord->flags));
            continue;
        }

        $wd = $ord->equip->model_equip->wd;

        if($wd == 0) {
            $ord->finalAreaNoWidth();
            Info(sprintf(
                "Ord: %d without width (TOC:%d) [0x%X]"
                , $ord->id
                , 0
                , $ord->flags
            ));
            continue;
        }

        $dbl_track = $ord->isDoubleTrack();

        printf("OID:%d, TOP:%d(%d), CAR_MDL:%d, EQ_MDL:%d, Wd:%d%s\n",
            $ord->id,
            $ord->tech_op->id,
            0,
            $ord->car->model->id,
            $ord->equip->id,
            $wd,
            $dbl_track ? ', DBL' : ''
        );



        echo "Read logs ...";
        $logs = OrderLog::getControl($ord);
        echo ' ' . count($logs) . " readed\n";

        $stm = 0;
        $dst = 0;
        $area = 0;
        foreach($logs as $log) {
            // $log->top_wd = $wd;
            if($log->canEvalArea()) {
                echo "eval area for log {$log->id}\n";
                $stm += $log->tm_stay;
                $a = $log->evalArea($dbl_track);
                $err = OrderLog::$error;
                $area += $a;
                Info("{$ord->id} work in {$log->geo} [L:$log->id] = $a $err");
                $dbg = GlobalMethods::popDebug();
                if($dbg) foreach($dbg as $ln) echo "$ln\n";
            } else {
                echo "skip area for log {$log->id}\n";
                $dst += $log->sumDst();
            }
        }
        $ord->finishArea($dst);

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
if($ord_only == 0) GlobalMethods::pidUnLock();
$dt = time() - $init;
Info("Ended within $dt sec., Orders: $ord_cnt, Messages: $total");