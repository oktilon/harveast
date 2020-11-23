<?php
    $init = time();
    require_once dirname(__DIR__) . '/html/sess.php';
    $_REQUEST['obj'] = '{"p":1}';

    $args = [];
    if($argc > 1) {
        $args = array_slice($argv, 1);
    }

    $geo_list = [];
    $jnt_list = [];
    $jnt_firm = [];
    $jnt_cond = [];
    $forced = false;
    $purge_points = false;
    $purge_area = false;
    $purge_lines = false;
    while($args) {
        $arg = array_shift($args);
        if(preg_match('/^\-(\w+)$/', $arg, $m)) {
            $cmd = $m[1];
            if($cmd == 'pp') {
                $purge_points = true;
                $cmd = '';
            } elseif($cmd == 'pl') {
                $purge_lines = true;
                $cmd = '';
            } elseif($cmd == 'pa') {
                $purge_area = true;
                $cmd = '';
            } elseif($cmd == 'fc') {
                $forced = true;
            }
        } else {
            switch($cmd) {
                case 'j':
                    $i = intval($arg);
                    if($i && !in_array($i, $jnt_list)) {
                        $jnt_list[] = $i;
                    }
                break;

                case 'g':
                    $i = intval($arg);
                    if($i && !in_array($i, $geo_list)) {
                        $geo_list[] = $i;
                    }
                break;

                case 'f':
                    $i = intval($arg);
                    if($i && !in_array($i, $jnt_firm)) {
                        $jnt_firm[] = $i;
                    }
                break;

                case 'a':
                    if(preg_match('/^(\d{4})(\d\d)(\d\d)((\d\d)(\d\d)(\d\d))*$/', $arg, $m)) {
                        $dt = sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
                        $tm = isset($m[4]) && $m[4] != '' ? sprintf('%s:%s:%s', $m[5], $m[6], $m[7]) : '00:00:00';
                        $jnt_cond[] = "d_beg > '$dt $tm'";
                    }
                    $cmd = '';
                break;
            }
        }
    }

    if($jnt_firm) {
        $firms = implode(',', $jnt_firm);
        $jnt_cond[] = "firm IN({$firms})";
    }

    if($jnt_list) {
        $jnts = implode(',', $jnt_list);
        $jnt_cond[] = "id IN({$jnts})";
    }

    if($geo_list) {
        $geos = implode(',', $geo_list);
        $jnt_cond[] = "geo IN({$geos})";
    }

    if(!$jnt_cond) {
        echo "No condition to select joints!\n";
        die();
    }

    try {
        $cond = implode(' AND ', $jnt_cond);
        $lst = $DB->prepare("SELECT id
                            FROM gps_joint
                            WHERE $cond
                            ORDER BY id")
                    ->execute_all();

        foreach($lst as $row) {
            $id = intval($row['id']);
            $oj = new OrderJoint($id, true);
            if($oj->isClosed() && !$forced) {
                echo "JNT:{$id} is closed\n";
            } else {
                if($purge_area || $purge_points || $purge_lines) {
                    foreach($oj->list as $oji) {
                        $wo = new WorkOrder($oji->ord_id);
                        if($purge_points || $purge_lines) {
                            // OrderLog::$keepPoints = $purge_lines;
                            $wo->resetParser(true);
                        } elseif($purge_area) {
                            $wo->resetArea();
                        }
                    }
                }
                $ok = $oj->delete();
                if($ok) {
                    $pp = [];
                    if($purge_lines) {
                        $pp[] = 'lines purged';
                    } elseif($purge_points) {
                        $pp[] = 'pts.purged';
                    } elseif($purge_area) {
                        $pp[] = 'area purged';
                    }
                    $pp[] = 'deleted';
                    $p = implode(', ', $pp);
                    echo "JNT:{$id} {$p}\n";
                } else {
                    echo "JNT:{$id} error DB:{$DB->error}, PG:{$PG->error}\n";
                    echo json_encode(WorkOrder::$err, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                }
            }
        }
    }
    catch(Exception $e) {
        $m = $e->getMessage();
        $t = json_encode($e->getTrace(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "Exception: {$m}\n{$t}\n";
    }

    echo "Finished!\n";