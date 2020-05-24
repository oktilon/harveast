<?php
require_once dirname(__DIR__) . '/html/sess.php';
InfoPrefix(__FILE__);
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$init = time();
$devs_only = false;
$is_debug = false;
$devs = [];
$cars = [];
$part = 0;
$pnt_tot = 0;
$cmd = '';

while($args) {
    $arg = array_shift($args);
    if(preg_match('/^\-(\w+)$/', $arg, $m)) {
        $cmd = $m[1];
        if($cmd == 'dbg') {
            $is_debug = true;
            $cmd = '';
        }
        if($cmd == 'h') {
            die("-h      = This help\n" .
                "-dbg    = Debug mode\n" .
                "-p part = Partial selector\n" .
                "-d dev1 dev2... = only devices\n" .
                "-c car1 car2... = only cars\n"
            );
        }
    } else {
        $i = intval($arg);
        if($cmd != '' && $i) {
            switch($cmd) {
                case 'd':
                    $devs[] = $i;
                    break;
                case 'c':
                    $cars[] = $i;
                    break;
                case 'p':
                    $part = $i;
                    $cmd = '';
                    break;
            }
        } else {
            $cmd = '';
        }
    }
}

if($is_debug) {
    CarLog::$is_debug = true;
    CarCache::$is_debug = true;
    CarLogItem::$is_debug = true;
}

$add = '';
$devs = implode(',', $devs);
$cars = implode(',', $cars);
if($part > 0) {
    $add = "_$part";
}

InfoPrefix(__FILE__, $add);
$msg = $devs ? " devs:[$devs]" : '';
$msg .= $cars ? " cars:[$cars]" : '';
Info('Started' . $msg);

$DB->prepare("TRUNCATE TABLE wialon_msg_error")->execute();

if($cars) {
    $q = $DB->prepare("SELECT device FROM gps_carlist WHERE id IN($cars) AND device > 0")
            ->execute_all(PDO::FETCH_NUM);
    if($q) {
        $devs = [];
        foreach($q as $r) $devs[] = intval($r[0]);
        $devs = implode(',', $devs);
    }
}

try {

    if(!$devs) {
        $ext = $part ? "_{$part}.lck" : ".lck";
        $lckFile = str_replace('.php', $ext, $_SERVER['SCRIPT_FILENAME']);
        if(!GlobalMethods::pidLock($lckFile, 10800, ADMIN_CHAT, [], 7200)) die();
    } else {
        CarCache::$is_unlimit = true;
    }

    $cars = CarCache::getCache($devs, $part);
    $carCnt = count($cars);
    Info("Cars count : $carCnt");
    $ix = 1;
    foreach($cars as $cc) {
        $ext = "($ix of $carCnt)";
        if(!$cc->calcLog($ext)) {
            Info("{$cc->id}, c:{$cc->car} $ext error: " . CarCache::$error);
            $DB->prepare("INSERT IGNORE INTO wialon_msg_error
                            (gps_id, err) VALUES (:i, :e)")
                ->bind('i', $cc->id)
                ->bind('e', CarCache::$error)
                ->execute();
        }

        if($is_debug) {
            echo "\nCarCache Debug:\n";
            foreach(CarCache::$debug as $t) echo $t;
            echo "\n\n";
        }

        $pnt_tot += CarCache::$points;
        $cc->setComplete();
        $cc->checkNewDay();
        $cc->save();
        $ix++;
        if($cc->stop) {
            Info('Stop by Wialon error');
            break;
        }
        //echo "\n" . implode("\n", CarCache::$debug) . "\n";
    }
} catch(Exception $e) {
    Info('Chessboard Exception : ' . $e->getMessage());
    foreach($e->getTrace() as $line) Info("Chessboard Trace : " . json_encode($line, JSON_UNESCAPED_UNICODE));
}

if(!$devs_only) {
    GlobalMethods::pidUnLock();
}
$dt = time() - $init;
$pp = $dt > 0 ? ($pnt_tot / $dt) : 0;
$per_car = number_format($dt / count($cars), 3, ',', ' ');
$per_pnt = number_format($pp, 3, ',', ' ');
Info("Ended within $dt sec, $per_car sec/car, $per_pnt pnt/sec");