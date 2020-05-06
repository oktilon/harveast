<?php
class OrderLogPoint {
    public $id     = 0;
    public $log_id = 0;
    public $dt     = 0;
    public $ord_id = 0;
    public $geo_id = 0;
    /**
     * speed
     * @var integer unsigned
     */
    public $spd    = 0;
    /**
     * course
     * @var integer unsigned
     */
    public $ang    = 0;
    /** @var StPoint */
    public $pt;

    public static $total  = 0;
    public static $cachePoints  = [];

    public function __construct($arg = 0) {
        global $PG;

        $this->pt = new StPoint();
        if(is_object($arg) && is_a($arg, 'WialonMessage')) {
            $this->dt =  $arg->t;
            $this->pt =  $arg->pos->getStPoint();
            $this->spd = $arg->pos->s;
            $this->ang = $arg->pos->c;
            return;
        }

        if(is_numeric($arg)) {
            $id = intval($arg);
            if(!$id) return;
            $row = $PG->prepare("SELECT *, ST_AsText(pt) ptt FROM order_log_point WHERE id = :id")
                        ->bind('id', $id)
                        ->execute_row();
            if($row) $arg = $row;
        }
        if(is_array($arg)) {
            foreach($arg as $k => $v) {
                if($k == 'pt') continue;
                if($k == 'ptt') {
                    $v = new StPoint($v);
                    $k = 'pt';
                } else {
                    $v = intval($v);
                }
                $this->$k = $v;
            }
        }
    }

    public function save() {
        global $PG;
        $t = new SqlTable('order_log_point', $this, [], 'id', false, $PG);
        return $t->save($this);
    }

    public static function getLastTimestamp($oid) {
        global $PG;
        $r = $PG->prepare("SELECT MAX(dt) FROM order_log_point
                            WHERE ord_id = :oid")
                ->bind('oid', $oid)
                ->execute_scalar();
        return $r ? intval($r) : 0;
    }

    // public static function hasNewPointsBefore($tm, $oid) {
    //     global $PG;
    //     $r = $PG->prepare("SELECT COUNT(id) FROM order_log_point
    //                         WHERE dt < :dt AND log_id = 0 AND ord_id = :oid")
    //             ->bind('dt', $tm)
    //             ->bind('oid', $oid)
    //             ->execute_scalar();
    //     return intval($r) > 0;
    // }

    public static function enumPointsGeo($oid) {
        global $PG;
        $ret = [];
        $q = $PG->prepare("SELECT geo_id FROM order_log_point
                            WHERE ord_id = :o AND geo_id > 0
                            GROUP BY geo_id")
                ->bind('o', $oid)
                ->execute_all();
        foreach($q as $r) $ret[] = intval($r['geo_id']);
        return $ret;
    }

    public static function hasPoint(WialonMessage $msg) {
        return in_array($msg->t, self::$cachePoints);
    }

    // public function setLog($lid) {
    //     global $PG;
    //     return $PG->prepare("UPDATE order_log_point
    //                         SET log_id = :lid
    //                         WHERE id = :id")
    //             ->bind('lid', $lid)
    //             ->bind('id',  $this->id)
    //             ->execute();
    // }

    // public static function resetOrder($oid) {
    //     global $PG;
    //     return $PG->prepare("UPDATE order_log_point
    //                         SET log_id = 0
    //                         WHERE ord_id = :id")
    //             ->bind('id',  $oid)
    //             ->execute();
    // }

    public static function getLastTime($iid) {
        global $PG;
        return intval($PG->prepare("SELECT MAX(dt)
                            FROM gps_points
                            WHERE id = :iid")
                ->bind('iid', $iid)
                ->execute_scalar());
    }


    public static function initPointsCache($oid, $tBeg, $tEnd) {
        self::$cachePoints = self::getList([
            ['ord_id = :o', 'o', $oid],
            ['dt BETWEEN :b AND :e', 'b', $tBeg - 600],
            [FALSE, 'e', $tEnd + 600],
            'dt_only'
        ], 'dt');
        return count(self::$cachePoints);
    }


    public static function addPoint(WialonMessage $msg, $gid, $oid) {
        global $PG;
        self::$cachePoints[] = $msg->t;
        return $PG->prepare("INSERT INTO order_log_point
                            (dt, pt, spd, ang, ord_id, geo_id)
                            VALUES (:dt, ST_GeomFromText(:pt), :spd, :ang, :oid, :gid)")
                ->bind('dt', $msg->t)
                ->bind('pt', $msg->pos->getWkt())
                ->bind('spd', $msg->pos->s)
                ->bind('ang', $msg->pos->c)
                ->bind('oid', $oid)
                ->bind('gid', $gid)
                ->execute();
    }

    public static function getList($flt = array(), $ord = '', $lim = '') {
        global $PG;
        self::$total = 0;
        $empty = true;
        //$lines = false;
        $obj = true;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*, ST_AsText(pt) ptt';
        $fld  = '';
        foreach($flt as $it) {
            if($it == 'non_empty') {
                $empty = false;
            } elseif($it == 'dt_only') {
                $flds = 'dt';
                $fld = 'dt';
                $obj = false;
            } elseif(is_array($it)) {
                $cond = array_shift($it);
                switch($cond) {
                    case 'fields':
                        $flds = implode(',', $it);
                        $obj = false;
                        break;

                    default:
                        if($cond) $add[] = $cond;
                        $par[$it[0]] = $it[1];
                        break;
                }
            } else {
                $add[] = $it;
            }
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        //$limit = $lim ? "LIMIT $lim" : '';
        //$calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $PG->prepare("SELECT $flds FROM order_log_point $add $order");
        foreach($par as $k => $v) {
            $PG->bind($k, $v);
        }
        $rows = $PG->execute_all();
        self::$total = count($rows);
        foreach($rows as $row) {
            $ret[] = $obj ? new OrderLogPoint($row) : ($fld ? intval($row[$fld]) : $row);
        }
        if(!$ret && $fld && !$empty) $ret[] = [-1];
        return $ret;
    }
}