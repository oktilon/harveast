<?php
$init = time();
require_once dirname(__DIR__) . '/html/sess.php';
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$step    = 1;
$dt_now  = new DateTime();
$dt_many = null;
$dt_eval = '';
$geo_only = [];
$add     = '';
$tot     = 0;
$cmd = '';
while($args) {
    $arg = array_shift($args);
    if(preg_match('/^\-(\w+)$/', $arg, $m)) {
        $cmd = $m[1];
    } else {
        if($cmd == 'd' && preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $arg)) {
            $dt_eval = $arg;
            $cmd = '';
        }
        if($cmd == 's' && preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $arg)) {
            $dt_many = new DateTime($arg);
            $cmd = '';
        }
        if($cmd == 'g' && preg_match('/^\d+$/', $arg)) {
            $geo_only[] = intval($arg);
            $add = '_g';
        }
    }
}

InfoPrefix(__FILE__, $add);
Info(sprintf('Started (%u)', getmypid()));

try {
    $lckFile  = str_replace('.php', "{$add}.lck", $_SERVER['SCRIPT_FILENAME']);

    if(!$geo_only) {
        if(!GlobalMethods::pidLock($lckFile, 10000, ADMIN_CHAT)) die();
    }

    while($step > 0) {
        if($dt_many != null) {
            $dt_many->modify('+1 MONTHS');
            $dt_eval = $dt_many->format('Y-m-d');
            if($dt_many >= $dt_now) {
                $dt_many = null;
                $step = 1;
            } else {
                $step = 2;
            }
        }
        if($dt_eval) $add = " till $dt_eval";

        $rows = OrderJoint::findJoints($dt_eval, $geo_only);
        $cnt = count($rows);
        $tot += $cnt;
        Info("Check $cnt rows" . $add);
        if($DB->error) throw new Exception("db error {$DB->error}");

        OrderJoint::resetOrdersList();

        foreach($rows as $ojo) {
            if($ojo->validForCheck()) {
                $oj = OrderJoint::checkJoint($ojo);
                if($oj->id < 0) {
                    $msg = sprintf("%d-%d [%s - %s] (%d) DB error : %s",
                                $oj->geo,
                                $oj->techop,
                                $oj->d_beg->format('Y-m-d H:i:s'),
                                $oj->d_end->format('Y-m-d H:i:s'),
                                OrderJoint::$total,
                                $DB->error
                    );
                    Info($msg);
                } else {
                    if($oj->needRecalc()) {
                        Info("Eval joint:{$oj->id} reason: " . OrderJoint::$recalcReason);
                        $oj->evalJointArea(0);
                        $oj->setRecalc(false);
                        $oj->save();
                    }
                }
            }
        }

        OrderJoint::markOrdersFromList();

        $step--;
    }

    OrderJoint::purgeEmptyJoints();

} catch(Exception $e) {
    echo PHP_EOL;
    Info('Exception : ' . $e->getMessage());
    print_r($e->getTrace());
}

if(!$geo_only) {
    GlobalMethods::pidUnLock();
}
$dt = time() - $init;
Info("Ended within $dt sec., Rows: $tot");