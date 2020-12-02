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
        $area = floatval($row['area']);
        $flgBy = $flg & (OrderJoint::FLAG_BY_TOTAL | OrderJoint::FLAG_BY_USER);
        // $oj = new OrderJoint($jnt['id']);
        // $oj->close($area, $flgBy, $row['close_note'], intval($row['close_user']));
        echo '.';
        $cntOne++;
    } elseif($cnt > 1) {
        echo "({$cnt})";
        $cntMul++;
    } else {
        $cntNo++;
        echo "x";
    }
}

echo "Good  = $cntOne\n" .
     "Multy = $cntMul\n" .
     "Bad   = $cntNo\n";