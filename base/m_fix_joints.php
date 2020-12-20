<?php
require_once dirname(__DIR__) . '/html/sess.php';
InfoPrefix(__FILE__);

$t = time();
echo "Get backup\n";

$flagRestored = 0x1000;

$f = OrderJoint::FLAG_IS_CLOSED;
$lst = $DB->prepare("SELECT * FROM gps_joint_bak
                WHERE flags & $f
                    AND flags & $flagRestored = 0
                ORDER BY d_beg")
        ->execute_all();
$cnt = count($lst);
echo "Got $cnt backed joints\n";

$cntOne = 0;
$cntCls = 0;
$cntFix = 0;
$cntMul = 0;
$cntNo  = 0;

foreach($lst as $row) {
    $jnts = $DB->prepare("SELECT *
                    FROM gps_joint
                    WHERE   geo    = :g
                        AND techop = :t
                        AND d_beg  < :e
                        AND d_end  > :b
                    ORDER BY d_beg")
                ->bind('g', $row['geo'])
                ->bind('t', $row['techop'])
                ->bind('b', $row['d_beg'])
                ->bind('e', $row['d_end'])
                ->execute_all();
    $cnt = count($jnts);
    if($cnt == 1) {
        $jnt = $jnts[0];
        $flg = intval($row['flags']);
        $xFlg = intval($jnt['flags']);
        $c = '.';
        if($xFlg & $f) {
            $c = 'o';
            $cntCls++;
        } else {
            $area = floatval($row['area']);
            $flgBy = $flg & (OrderJoint::FLAG_BY_TOTAL | OrderJoint::FLAG_BY_USER);
            ob_start();
            $oj = new OrderJoint($jnt['id']);
            $oj->close($area, $flgBy, $row['close_note'], intval($row['close_user']));
            ob_end_clean();
            $cntFix++;
        }
        $oldId = $row['id'];
        $q = $DB->prepare("UPDATE gps_joint_bak
                        SET flags = flags | $flagRestored
                        WHERE id = $oldId")
                ->execute();
        echo $q ? $c : "[{$DB->error}]";
        $cntOne++;
    } elseif($cnt > 1) {
        $cl = 0;
        foreach($jnts as $jnt) {
            if(intval($jnt['flags']) & $f) {
                $cl++;
            }
        }
        echo "({$cnt},c:{$cl})";
        $cntMul++;
    } else {
        $no = true;
        $gid = $row['geo'];
        $geos = $DB->prepare("SELECT n.id
                            FROM gps_geofence g
                            LEFT JOIN gps_geofence n ON n.n = g.n AND n.id != g.id
                            WHERE g.id = $gid
                            ORDER BY n.ct ASC")
                    ->execute_all();
        foreach($geos as $grow) {
            $pgeo = intval($grow['id']);
            $jnts = $DB->prepare("SELECT *
                            FROM gps_joint
                            WHERE   geo    = :g
                                AND techop = :t
                                AND d_beg  < :e
                                AND d_end  > :b
                            ORDER BY d_beg")
                        ->bind('g', $pgeo)
                        ->bind('t', $row['techop'])
                        ->bind('b', $row['d_beg'])
                        ->bind('e', $row['d_end'])
                        ->execute_all();
            $cnt = count($jnts);
            if($cnt == 1) {
                $jnt = $jnts[0];
                $flg = intval($row['flags']);
                $xFlg = intval($jnt['flags']);
                $c = '.';
                if($xFlg & $f) {
                    $c = 'o';
                    $cntCls++;
                } else {
                    $area = floatval($row['area']);
                    $flgBy = $flg & (OrderJoint::FLAG_BY_TOTAL | OrderJoint::FLAG_BY_USER);
                    ob_start();
                    $oj = new OrderJoint($jnt['id']);
                    $oj->close($area, $flgBy, $row['close_note'], intval($row['close_user']));
                    ob_end_clean();
                    $cntFix++;
                }
                $oldId = $row['id'];
                $q = $DB->prepare("UPDATE gps_joint_bak
                                SET flags = flags | $flagRestored
                                WHERE id = $oldId")
                        ->execute();
                echo $q ? $c : "[{$DB->error}]";
                $cntOne++;
                $no = false;
            } elseif($cnt > 1) {
                $cl = 0;
                foreach($jnts as $jnt) {
                    if(intval($jnt['flags']) & $f) {
                        $cl++;
                    }
                }
                echo "({$cnt},c:{$cl})";
                $cntMul++;
                $no = false;
            }
            if(!$no) {
                break;
            }
        }

        if($no) {
            $cntNo++;
            echo "x";
        }
    }
}
echo "\n\n";
echo "Good  = $cntOne ($cntCls skipped, $cntFix closed)\n" .
     "Multy = $cntMul\n" .
     "Bad   = $cntNo\n";