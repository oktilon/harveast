<?php
$init = time();
require_once dirname(dirname(dirname(__FILE__))) . '/cron.php';

$ord_only = [];
$part_div = 0;
$part_del = 4;
$since    = '2020-03-01';
$arg      = '';
while($PM->args) {
    $cmd = array_shift($PM->args);
    if(preg_match('/^\-(\w)$/', $cmd, $m)) {
        $arg = $m[1];
    } else {
        if($arg == 's' && preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $cmd)) {
            $since = $cmd;
            $snc = $cmd;
            $arg = '';
        }
        if($arg == 'o' && preg_match('/^\d+$/', $cmd)) {
            $i = intval($cmd);
            if($i) {
                $ord_only[] = $i;
            }
        }
        if($arg == 'p' && preg_match('/^\d+$/', $cmd)) {
            $i = intval($cmd);
            if($i) {
                $part_div = $i;
            }
            $arg = '';
        }
    }
}

// $echo = json_encode($tmStart, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

$ord_list = '';
$add = '';
if($part_div > 0) {
    $add = "_{$part_div}";
}
if(count($ord_only) > 0) {
    $ord_list = implode(',', $ord_only);
    $add .= "_(o:{$ord_list})";
}
$ext = $since != '2020-03-01' ? " since {$since}" : '';

InfoPrefix(__FILE__, $add);
Info(sprintf('Started (%u)%s', getmypid(), $ext));

// $echo = json_encode($tmStart, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
try {
    $lckFile  = str_replace('.php', "_{$part_div}.lck", $_SERVER['SCRIPT_FILENAME']);

    if(!count($ord_only)) {
        if(!PageManager::pidLock($lckFile, 10000, ADMIN_CHAT)) die();
    }

    $fEqu = WorkOrder::FLAG_ORDER_AREA;
    $fChk = $fEqu | WorkOrder::FLAG_ORDER_RELOCATIONS;
    $total = 0;
    $total_points = 0;

    $flt = [
        "(flags & $fChk) = $fEqu"
    ];
    if($part_div > 0) {
        $eq = $part_div - 1;
        $flt[] = "id % {$part_del} = {$eq}";
    }
    if(count($ord_only)) {
        $flt[] = "id IN($ord_list)";
    }
    if($since) {
        $flt[] = ["d_beg >= :s", 's', $since];
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


    foreach($orders as $iRow => $ord) { // По каждому наряду

        OrderRelocation::reset($ord->id);

        printf("OID:%d\n", $ord->id);

        echo "Read geos ...";
        $geos = OrderLog::getWorkingGeoZoneIds($ord);
        echo ' ' . count($geos) . " readed\n";

        echo "Read points ...";
        $points = $ord->getPoints();
        $cnt = count($points);
        echo ' ' . $cnt . " readed\n";
        $total_points += $cnt;

        /** @var OrderLogPoint */
        $prev_pnt = null;
        /** @var OrderRelocation[] */
        $relocations = [];
        /** @var OrderRelocation */
        $reloc    = null;
        foreach($points as $pnt) {
            if(!in_array($pnt->geo_id, $geos)) {
                if($reloc) {
                    if($pnt->spd > 0) $reloc->addPoint($pnt);
                } else {
                    $reloc = OrderRelocation::init($ord->id, $pnt, $prev_pnt);
                }
            } else {
                if($reloc) {
                    if($reloc->finish($pnt)) {
                        $relocations[] = $reloc;
                    }
                    $reloc = null;
                }
            }
            $prev_pnt = $pnt;
        }
        if($reloc && $reloc->finish()) {
            $relocations[] = $reloc;
        }

        $cnt = count($relocations);
        $total += $cnt;
        $ord->reloc_far = 0;
        $ord->reloc_near = 0;
        foreach($relocations as $reloc) {
            if($reloc->isFar()) {
                $ord->reloc_far += $reloc->dst;
            } else {
                $ord->reloc_near += $reloc->dst;
            }
            $reloc->save();
        }
        $ord->setFlag(WorkOrder::FLAG_ORDER_RELOCATIONS, true);
        $ord->save();

        Info(sprintf(
            "Ord: %d near=%0.2f km, far=%0.2f km, cnt:%d [0x%X]"
            , $ord->id
            , round($ord->reloc_near / 1000, 2)
            , round($ord->reloc_far / 1000, 2)
            , $cnt
            , $ord->flags
        ));
    }
} catch(Exception $e) {
    echo PHP_EOL;
    Info('EvalRelocations Exception : ' . $e->getMessage());
    print_r($e->getTrace());
}
if($ord_only == 0) PageManager::pidUnLock();
$dt = time() - $init;
Info("Ended within $dt sec., Orders: $ord_cnt, Relocations: $total, Points: $total_points");