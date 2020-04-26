<?php
class CarLogPoint {
    public $id     = 0;
    public $dt     = 0;
    public $geo_id = 0;
    /** @var integer unsigned */
    public $spd    = 0;
    /** @var integer unsigned */
    public $ang    = 0;
    /** @var StPoint */
    public $pt;

    public static $total  = 0;
    public static $cachePoints  = [];
    public static $fields = 'id, dt, geo_id, spd, ang, St_AsText(pt) pt';

    public function __construct($arg = 0, $tms = 0) {
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
            $dt = intval($tms);
            if(!$id || !$dt) return;
            $flds = self::$fields;
            $part = self::getPartitionFromTime($dt);
            $row = $PG->prepare("SELECT $flds FROM $part WHERE id = :id AND dt = :dt")
                        ->bind('id', $id)
                        ->bind('dt', $dt)
                        ->execute_row();
            if($row) $arg = $row;
        }
        if(is_array($arg)) {
            foreach($arg as $k => $v) {
                $vv = intval($v);
                if($k == 'pt') {
                    $vv = new StPoint($v);
                }
                $this->$k = $vv;
            }
        }
    }

    public function save() {
        global $PG;
        $t = new SqlTable('gps_points', $this, [], 'id', false, $PG);
        $t->setConflictField('id,dt');
        return $t->save($this, true);
    }

    public static function hasPoint(WialonMessage $msg) {
        return in_array($msg->t, self::$cachePoints);
    }

    public static function getPartition(DateTime $dt) {
        return "gps_points_" . $dt->format('Y_m');
    }

    public static function getPartitionFromTime($tms) {
        return "gps_points_" . date('Y_m', $tms);
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

    public static function convertWialonTimestamp($tms) {
        $dt = new DateTime("@{$tms}");
        $dt->setTimezone(WialonApi::getTimezone());
        $loc = new DateTime($dt->format('Y-m-d H:i:s'));
        return intval($loc->format('U'));
    }

    public static function calcPoint(WialonMessage $msg, $iid) {
        $lst = GeoFence::findPointFieldFast($msg->pos);
        $gid = $lst ? array_shift($lst) : 0;
        self::addPoint($msg, $gid, $iid);

    }

    public static function addPoint(WialonMessage $msg, $gid, $iid) {
        global $PG;
        $tms = self::convertWialonTimestamp($msg->t);
        $part = self::getPartitionFromTime($tms);
        self::$cachePoints[] = $msg->t;
        $ret = $PG->prepare("INSERT INTO $part
                            (id, dt, geo_id, spd, ang, pt)
                            VALUES (:id, :dt, :gid, :spd, :ang, ST_GeomFromText(:pt))")
                ->bind('id', $iid)
                ->bind('dt', $tms)
                ->bind('gid', $gid)
                ->bind('spd', $msg->pos->s)
                ->bind('ang', $msg->pos->c)
                ->bind('pt', $msg->pos->getWkt())
                ->execute();
        if(!$ret) echo "[pt_err:{$PG->error}]";
        return $ret;
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
        $flds = self::$fields;
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
        $PG->prepare("SELECT $flds FROM gps_points $add $order");
        foreach($par as $k => $v) {
            $PG->bind($k, $v);
        }
        $rows = $PG->execute_all();
        self::$total = count($rows);
        foreach($rows as $row) {
            $ret[] = $obj ? new CarLogPoint($row) : ($fld ? intval($row[$fld]) : $row);
        }
        if(!$ret && $fld && !$empty) $ret[] = [-1];
        return $ret;
    }
}