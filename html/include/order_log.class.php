<?php
class OrderLog {
    public $id       = 0;
    public $firm     = 0;
    public $geo      = 0;
    public $car_gps  = 0;
    public $car      = 0;
    public $trailer  = 0;
    public $ord      = 0;
    public $ord_line = 0;
    public $techop   = 0;
    public $top_cond = 0;
    public $top_wd   = 0;

    public $dt_beg   = null;
    public $dt_end   = null;

    public $flags    = 0;
    public $ev_time  = 0;

    public $spd_min  = 0;
    public $spd_max  = 0;

    public $dst_low  = 0;
    public $dst_norm = 0;
    public $dst_high = 0;
    public $dst_rep  = 0;

    public $ord_area = 0.0;
    public $jnt_area = 0.0;
    public $msg_cnt  = 0;

    public $tm_low   = 0;
    public $tm_norm  = 0;
    public $tm_high  = 0;
    public $tm_stay  = 0;
    public $tm_rep   = 0;

    public $rep_mode = 0;
    public $rep_id   = 0;

    public $ang_idx  = 0;
    public $ang_dst  = 0;
    public $ang_lst  = [];

    public $lines    = [];

    public $log_pts  = [];

    private $geoPerimeter = 0;
    private $geoDist      = 0;
    private $geoTime      = 0;

    private static $cache = [];
    public static $total  = 0;
    public static $dbg_on = false;
    public static $tmz = null;
    public static $debug = [];

    public static $api = null;
    public static $points = 0;

    public static $error = '';

    public static $varMinAngleDist     = 50;   // Meters
    public static $varMinPassCount     = 2;    // Pieces
    public static $varMinGeofenceCoeff = 2.0;  //
    public static $varReportLifeTime   = 1800; // sec
    public static $varCloseOrderAfter  = 3600; // no data X sec after end
    public static $varCloseOrderIfSilent = 3600; // no data X sec till now (in period)
    public static $varSilentPeriods    = [ [0, 6] ]; // period to close order if silent

    const OL_MODE_STAY = 0;  // Простой
    const OL_MODE_LOW  = 1;  // Скорость ниже нормы
    const OL_MODE_NORM = 2;  // Нормальная скорость
    const OL_MODE_HIGH = 3;  // Превышение скорости

    const ONE_DAY = 86400;

    public static $rep_modes = [
        self::OL_MODE_STAY => 'z',
        self::OL_MODE_NORM => 'n',
        self::OL_MODE_HIGH => 'h',
        self::OL_MODE_LOW  => 'l',
    ];

    const ALERT_LIFETIME     = 1800; // sec = 30min
    const MIN_GEOFENCE_COEFF = 2.0;


    const ANG_HALF = 12;
    const ANG_STEP = 15; // = 180 / ANG_HALF

    const DEF_TIMEZONE = 'Europe/Kiev';

    const FLAG_IS_WORKING     = 0x0001;
    const FLAG_IS_CHANGE      = 0x0002;
    const FLAG_IS_REMOVED     = 0x0004;
    const FLAG_IS_CLOSED      = 0x0008;

    const FLAG_IS_DBL_DUAL    = 0x0010;
    const FLAG_IS_FUTURE_YEAR = 0x0020;
    const FLAG_IS_INVALID     = 0x0040;

    /**
    * Конструктор (из БД или из скрипта)
    *
    * @param mixed БД = строка ИЛИ s_id, СКРИПТ = массив скоростей
    * @param array СКРИПТ = массив из карлиста и нарядов
    * @param string СКРИПТ = дата проверки
    * @param integer СКРИПТ = id проверяемой геозоны
    * @return OrderLog
    */
    public function __construct($arg = 0, $line = null, CarLogPoint $point = null, AggregationList $agl = null) {
        global $DB;
        $this->dt_beg = new DateTime('2000-01-01');
        $this->dt_end = new DateTime('2000-01-01');
        for($i = 0; $i < self::ANG_HALF * 2; $i++) $this->ang_lst[$i] = 0;

        if($point && is_a($point, 'CarLogPoint') && is_a($arg, 'WorkOrder')) { //($ord, $msg, $gid)
            // echo "New Log {$arg->id} : $geo\n";
            $this->firm     = $arg->firm->id;
            $this->geo      = $point->geo_id;
            $this->car_gps  = $arg->car->device->gps_id;
            $this->car      = $arg->car->id;
            $this->trailer  = $line->trailer->id;
            $this->ord      = $arg->id;
            $this->ord_line = $line->id;
            $this->techop   = $line->tech_op->id;
            $this->top_cond = $line->tech_cond->id;
            $this->top_wd   = $line->tech_cond->width;
            $this->flags    = 0;
            $this->spd_min  = $agl->spd_min;
            $this->spd_max  = $agl->spd_max;
            $this->dt_beg->setTimestamp($point->dt);
            $this->dt_end->setTimestamp($point->dt);
            $this->readMyGeoParameters();
            return;
        }

        if(is_numeric($arg)) {
            if(!$arg) return;
            $row = $DB->prepare("SELECT * FROM gps_order_log WHERE id = :i")
                        ->bind('i', $arg)
                        ->execute_row();
            if($DB->error) throw new Exception($DB->error);
            $arg = $row;
        }
        if(is_array($arg)) {
            foreach($arg as $k => $v) $this->$k = self::getProperty($k, $v);
            $this->readMyGeoParameters();
        }
        if($point === true) {
            $this->lines = OrderLogLine::getList(["log_id = {$this->id}"], '');
        }
    }

    private static function getProperty($key, $val) {
        switch($key) {
            case 'dt_beg':
            case 'dt_end': return new DateTime($val);
            case 'jnt_area':
            case 'ord_area': return floatval($val);
            case 'ang_lst': return json_decode($val);
        }
        return intval($val);
    }

    public function save($with_lines = true) {
        global $PG;
        $t = new SqlTable('gps_order_log', $this, ['lines', 'log_pts']);
        self::$debug[] = "try save log {$this->id}";
        $ret = $t->save($this);
        self::$debug[] = "saved log = " . json_encode($ret);
        foreach($this->lines as $ln) {
            if($ln->log_id == 0) {
                $ln->log_id = $this->id;
            }
            if($with_lines) {
                self::$debug[] = "try save log_line";
                $q = $ln->save();
                self::$debug[] = "saved log_line = " . json_encode($q);
                self::$debug[] = "saved log_line error {$PG->error}";
            }
        }
        return $ret;
    }

    public function getFlag($flag) { return ($this->flags & $flag) > 0; }
    public function setFlag($flag, $val) {
        if($val) {
            $this->flags |= $flag;
        } else {
            $this->flags &= ~$flag;
        }
    }

    public function isWorking() { return $this->getFlag(self::FLAG_IS_WORKING); }
    public function isChange() { return $this->getFlag(self::FLAG_IS_CHANGE); }
    public function isRemoved() { return $this->getFlag(self::FLAG_IS_REMOVED); }
    public function isClosed() { return $this->getFlag(self::FLAG_IS_CLOSED); }
    public function isFutureYear() { return $this->getFlag(self::FLAG_IS_FUTURE_YEAR); }
    public function setChange($on = true) { $this->setFlag(self::FLAG_IS_CHANGE, $on); }
    public function setRemoved($on = true) { $this->setFlag(self::FLAG_IS_REMOVED, $on); }

    public function canEvalArea() {
        // return $this->geo > 0 && $this->isWorking() && $this->top_wd > 0;
        return $this->geo > 0 && $this->top_wd > 0;
    }

    public function allowShowArea() {
        return $this->geo > 0 && $this->top_wd > 0 && !$this->isRemoved();
    }

    public function getWorkingArea() {
        global $PG;
        $r = $PG->prepare("SELECT ST_AsText(poly) FROM order_area WHERE _id=:i")
                ->bind('i', $this->id)
                ->execute_scalar();
        return StMultiPolygon::fastParse($r);
    }

    public function getTrack() {
        global $PG;
        $r = $PG->prepare("SELECT ST_AsText(ml) FROM order_area WHERE _id=:i")
                ->bind('i', $this->id)
                ->execute_scalar();
        $t = StPolyline::fastParseMulty($r);
        if($t) return $t;
        return $this->getWay();
    }

    public function getWay() {
        global $PG;
        $r = $PG->prepare("SELECT ST_AsText(ST_LineMerge(ST_Collect(pts))) FROM order_log_line WHERE log_id = :i")
                ->bind('i', $this->id)
                ->execute_scalar();
        return StPolyline::fastParseMulty($r);
    }

    public function getClusterId() {
        return CFirm::getClusterId($this->firm);
    }

    /**
     * Get operation year
     * @return int
     */
    public function getYear() {
        $ret = intval($this->dt_beg->format('Y'));
        if($this->isFutureYear()) $ret++;
        return $ret;
    }

    public function evalArea($dbl_track = false, $fast_mode = false, $no_crossout = false, $sz_erase = 0) {
        global $DB,$PG;

        self::$error = '';
        $ret = 0.0;
        $offset = $this->top_wd / 2000.0;
        $this->top_wd = intval($offset * 2000.0);
        $simple = false;
        $skip = false;
        $flg = 0;

        $loop = true;
        // 1 step buffer
        while($loop) {
            PageManager::debug(sprintf("make %s line and offset {$offset}", $simple ? 'simple' : 'normal'));
            if($simple) {
                $q = $PG->prepare("INSERT INTO order_area (_id, ml)
                                    SELECT :l, ST_Simplify(ST_LineMerge(ST_Collect(pts)), 0.00001)::geography
                                    FROM order_log_line WHERE log_id = :l
                                    ON CONFLICT (_id) DO UPDATE
                                        SET ml = excluded.ml")
                        ->bind('l', $this->id)
                        ->execute();
            } else {
                $q = $PG->prepare("INSERT INTO order_area (_id, ml)
                                    SELECT :l, ST_LineMerge(ST_Collect(pts))::geography
                                    FROM order_log_line WHERE log_id = :l
                                    ON CONFLICT (_id) DO UPDATE
                                        SET ml = excluded.ml")
                        ->bind('l', $this->id)
                        ->execute();
            }
            if(!$q) {
                self::$error = "merge line error {$PG->error}";
                $loop = false;
                $skip = true;
            } else {
                $q = $PG->prepare("UPDATE order_area oa
                                SET poly = ST_Buffer(ml, :o, :j)
                                WHERE oa._id = :l")
                        ->bind('o', $offset)
                        ->bind('l', $this->id)
                        ->bind('j', 'endcap=flat join=round')
                        ->execute();
                if(!$q) {
                    if($PG->errInfo[0] == 'XX000') {
                        if(!$simple) {
                            $simple = true;
                        } else {
                            self::$error = "offset {$offset} error {$PG->error}";
                            // Log error ?
                            $loop = false;
                            $skip = true;
                        }
                    } else {
                        self::$error = "offset {$offset} error {$PG->error}";
                        // Log error ?
                        $loop = false;
                        $skip = true;
                    }
                } else {
                    $loop = false;
                }
            }
        }

        $q = $PG->prepare("SELECT ST_IsValid(poly) FROM order_area
                            WHERE oa._id = :l")
                ->bind('l', $this->id)
                ->execute();
        if(!$q) {
            $flg |= self::FLAG_IS_INVALID;
            PageManager::debug(sprintf('invalid_buf L:%d', $this->id));
            // try to make_valid ?
        }


        if($skip) {
            PageManager::debug(sprintf('skip log %d', $this->id, $this->geo));
        } else {
            PageManager::debug(sprintf('cut log %d by geo %d', $this->id, $this->geo));
            // 2 step intersection
            $q = $PG->prepare("UPDATE order_area oa
                            SET poly = ST_Intersection(oa.poly, g.poly)
                            FROM geofences g
                            WHERE oa._id = :l AND g._id = :g")
                    ->bind('l', $this->id)
                    ->bind('g', $this->geo)
                    ->execute();

            if($sz_erase > 0) {
                PageManager::debug(sprintf('log %d erase islands less than %f', $this->id, $sz_erase));
                $this->removeIslands($sz_erase, true);
            }

            $q = $PG->prepare("SELECT ST_IsValid(poly) FROM order_area
                                WHERE oa._id = :l")
                    ->bind('l', $this->id)
                    ->execute();
            if(!$q) {
                $flg |= self::FLAG_IS_INVALID;
                PageManager::debug(sprintf('invalid_cut L:%d, G:%d', $this->id, $this->geo));
                // try to make_valid ?
            }


            //echo "calc area\n";
            $q = $PG->prepare("SELECT ST_Area(poly) FROM order_area WHERE _id = :l")
                ->bind('l', $this->id)
                ->execute_scalar();
            $ret = $q ? intval($q) : 0;
        }

        if($dbl_track) {
            $q = $PG->prepare("SELECT ST_Length(ml)
                        FROM order_area WHERE _id = :l;")
                    ->bind('l', $this->id)
                    ->execute_scalar();
            $len = $q ? intval($q) : 0;
            $dbl_area = $len * $offset * 2;
            $dual = $ret * 2;
            $max = max($dbl_area, $dual);
            $min = min($dbl_area, $dual);
            $dev = ($max - $min) / $max;
            // echo "Dbl: dual=$dual, len=$dbl_area, dev=$dev\n";
            if($dev > 0.5) {
                $ret = $dbl_area;
            } else {
                $ret = $dual;
                $flg = self::FLAG_IS_DBL_DUAL;
            }
        }

        $ret = round($ret / 10000, 2);

        $add = '';
        if(!$this->isWorking() && !$no_crossout) {
            $flg |= self::FLAG_IS_REMOVED;
        }

        if($flg > 0) {
            $add = ", flags = flags | " . $flg;
        }

        $geo_inv = $this->geo ? !GeoFence::isValid($this->geo) : false;
        if($flg & self::FLAG_IS_INVALID || $geo_inv) {
            WorkOrder::setInvalidGeo($this->ord);
        }

        $jnt = $fast_mode ? $ret : $this->jnt_area;

        $DB->prepare("UPDATE gps_order_log
                        SET ord_area = :a,
                            jnt_area = :j,
                            top_wd = :w $add
                        WHERE id = :l")
            ->bind('a', $ret)
            ->bind('j', $jnt)
            ->bind('w', $this->top_wd)
            ->bind('l', $this->id)
            ->execute();
        return $ret;
    }

    public function orderReadyToSave() {
        global $DB;
        $q = $DB->prepare("SELECT COUNT(*) FROM gps_order_log
                    WHERE id = :i AND ord_area > 0")
                ->bind('i', $this->id)
                ->execute_scalar();
        return intval($q) > 0;
    }

    public function setJoint($k, $fast = false) {
        global $DB;
        $can = true;
        if($fast) {
            $can = WorkOrder::canWriteFastJoint($this->ord);
        }
        $this->jnt_area = round($this->ord_area * $k, 2);
        if($can) {
            $q = $DB->prepare("UPDATE gps_order_log
                            SET jnt_area = :a
                            WHERE id = :i")
                ->bind('a', $this->jnt_area)
                ->bind('i', $this->id)
                ->execute();
        }
        $inf ="L:{$this->id} O:{$this->ord} ord:{$this->ord_area} jnt:{$this->jnt_area} ";
        Info($inf . ($q ? "ok" : "err:{$DB->error}"));
        return $can;
    }

    public function setLastJoint($da) {
        global $DB;
        $this->jnt_area += $da;
        if($this->jnt_area < 0) $this->jnt_area = 0;
        $q = $DB->prepare("UPDATE gps_order_log
                        SET jnt_area = :a
                        WHERE id = :i")
            ->bind('a', $this->jnt_area)
            ->bind('i', $this->id)
            ->execute();
        $inf ="L:{$this->id} O:{$this->ord} ord:{$this->ord_area} last_jnt:{$this->jnt_area} [da=$da] ";
        Info($inf . ($q ? "ok" : "err:{$DB->error}"));
    }

    public function getLine($rep, $pnt = null, $change = false) {
        $ln = null;
        if(!$change) {
            foreach($this->lines as $l) {
                if($l->rep == $rep && (!$ln || $l->dte > $ln->dte)) $ln = $l;
            }
        }
        if(!$ln && $pnt) {
            $ln = new OrderLogLine($this, $pnt);
            $this->lines[] = $ln;
        }
        return $ln;
    }

    public function addPoint($pnt, $dst, $oOrd, $pnt_prev = null) {
        $change = $oOrd->chk_rep != $this->rep_mode | $this->isChange();
        $ln = $this->getLine($this->rep_mode, $pnt, $change);
        if($change && $pnt_prev) {
            $ln->addPoint($pnt_prev, 0);
        }
        $ln->addPoint($pnt, $dst);
        $pnt->log_id = $this->id;
        $this->log_pts[] = $pnt;
    }

    public function addMessage(CarLogPoint $pnt, $pnt_prev, WorkOrder $oOrd, $plog) {
        self::$debug = [];
        $this->ev_time = $pnt->dt;
        $this->msg_cnt++;
        $this->dt_end = self::dateFromUTC($pnt->dt);
        if($this->dt_beg->format('Y') == 2000) {
            $this->dt_beg = self::dateFromUTC($pnt->dt);
        }
        $spd = $pnt->spd;
        $mov = $spd > 0;
        $dst = $pnt_prev ? StPolygon::distance($pnt->pt, $pnt_prev->pt) : 0;
        $tm  = $pnt_prev ? ($this->ev_time - $pnt_prev->dt) : 0;

        if($plog) {
            $plog->addPoint($pnt, $dst, $oOrd);
            $this->setChange(true);
        } else {
            $this->setChange(false);
        }

        $min = $this->spd_min > 1 ? ($this->spd_min - 1) : 0;
        $max = $this->spd_max > 0 ? ($this->spd_max + 1) : 0;

        if(!$mov) { // STAY
            $this->tm_stay += $tm;
            $tm = 0;
            $dst = 0;
        } else {
            $this->addAngle($pnt->ang, $dst);
        }

        // MOVE
        if(!$this->geo) { $spd = $min; } // Outside geo, speed = NORM

        if($spd < $min) {
            if($this->rep_mode != self::OL_MODE_LOW) $this->resetReport(self::OL_MODE_LOW);
            $this->dst_low += $dst;
            $this->tm_low += $tm;
            // echo " L";
        } elseif($max && $spd > $max) {
            if($this->rep_mode != self::OL_MODE_HIGH) $this->resetReport(self::OL_MODE_HIGH);
            $this->dst_high += $dst;
            $this->tm_high += $tm;
            // echo " H";
        } else {
            $this->dst_norm += $dst;
            $this->tm_norm += $tm;
            $this->resetReport(self::OL_MODE_NORM);
            // echo " N";
        }
        if($plog && $pnt_prev) $this->addPoint($pnt_prev, 0, $oOrd);
        // echo " a";
        $this->addPoint($pnt, $dst, $oOrd, $pnt_prev);
        //if(self::$dbg_on) printf("d:% 6.2f, t:% 2u, s:% 3u, r:%u ", $dst, $tm, $spd, $this->rep_mode);
        // WRONG SPEED
        if( $this->rep_mode == self::OL_MODE_HIGH ||
            $this->rep_mode == self::OL_MODE_LOW) {
                $this->dst_rep += $dst;
                $this->tm_rep += $tm;
                return $this->rep_mode == self::OL_MODE_HIGH; // Only overspeed
                //return true;
        }
        return false;
    }

    public function addAngle($ang, $dst) {
        $ind = intval($ang / self::ANG_STEP);
        $ids = intval($dst);
        if($ind >= 2 * self::ANG_HALF) $ind = 0;
        if($ind == $this->ang_idx) {
            $this->ang_dst += $ids;
            return;
        }
        if($this->ang_dst >= $this::$varMinAngleDist) {
            $this->ang_lst[$this->ang_idx]++;
        }
        $this->ang_dst = $ids;
        $this->ang_idx = $ind;
    }

    public function getAngleKoefficients() {
        $ret = [];
        for($i = 0; $i < self::ANG_HALF; $i++) {
            $j = $i + self::ANG_HALF;
            $ret[$i] = intval(( $this->ang_lst[$i] + $this->ang_lst[$j] ) / 2);
        }
        return $ret;
    }

    public function resetReport($rep_mode) {
        $this->dst_rep = 0;
        $this->tm_rep = 0;
        $this->rep_mode = $rep_mode;
    }

    public function reset() {
        global $PG, $DB;
        $this->flags    = 0;
        $this->dt_beg   = new DateTime('2000-01-01');
        $this->dt_end   = new DateTime('2000-01-01');
        $this->ev_time  = 0;
        $this->dst_low  = 0;
        $this->dst_norm = 0;
        $this->dst_high = 0;
        $this->dst_rep  = 0;
        $this->ord_area = 0.0;
        //$this->jnt_area = 0.0; -- keep fast joint area
        $this->msg_cnt  = 0;
        $this->tm_low   = 0;
        $this->tm_norm  = 0;
        $this->tm_high  = 0;
        $this->tm_stay  = 0;
        $this->tm_rep   = 0;
        $this->rep_mode = 0;
        $this->rep_id   = 0;
        $this->ang_idx  = 0;
        $this->ang_dst  = 0;
        $this->lines    = [];
        $this->log_pts  = [];

        for($i = 0; $i < self::ANG_HALF * 2; $i++) $this->ang_lst[$i] = 0;

        $this->save(false);

        $PG->prepare("DELETE FROM order_log_line WHERE log_id = :id")
            ->bind('id', $this->id)
            ->execute();
    }

    public function delete() {
        global $PG, $DB;
        $DB->prepare('DELETE FROM gps_order_log WHERE id = :i')
            ->bind('i', $this->id)
            ->execute();
        $PG->prepare("DELETE FROM order_log_line WHERE log_id = :id")
            ->bind('id', $this->id)
            ->execute();
        $PG->prepare("DELETE FROM order_area WHERE _id = :id")
            ->bind('id', $this->id)
            ->execute();
    }

    public function avgRepSpeed() {
        return $this->tm_rep ? (3.6 * $this->dst_rep / $this->tm_rep) : 0;
    }

    public function sumDst($scale = 1) { return $scale * ($this->dst_high + $this->dst_norm + $this->dst_low); }
    public function sumTm()  { return $this->tm_low + $this->tm_norm + $this->tm_low; }
    public function dstTot($prec = 2) { return number_format($this->sumDst(0.001), $prec, ',', ' '); }

    public function avgSpeed() {
        $tm = $this->sumTm();
        $ds = $this->sumDst();
        return $tm ? round(3.6 * $this->ds / $this->tm, 1) : 0;
    }

    public function readMyGeoParameters() {
        $ret = self::readGeoParameters($this->geo, $this->spd_max);
        $this->geoPerimeter = $ret['p'];
        $this->geoDist      = $ret['d'];
        $this->geoTime      = $ret['t'];
        return $ret;
    }

    public function toString() {
        $avg = $this->avgSpeed();
        return $this->id . ' [ord:' . $this->ord . '] = ' . $avg . ' > ' . $this->dst_low . ',' . $this->dst_norm . ',' . $this->dst_high;
    }

    public function levels() {
        $tot = $this->sumDst();
        if($tot == 0) return '0|0|0';
        return number_format(100 * $this->dst_low  / $tot, 0, '.', '') . '|' .
               number_format(100 * $this->dst_norm / $tot, 0, '.', '') . '|' .
               number_format(100 * $this->dst_high / $tot, 0, '.', '');
    }

    public function getNorm() { return $this->spd_min . '-' . $this->spd_max; }

    public function getField($notEmpty = false) {
        $ret = GeoFence::getFieldName($this->geo);
        if($notEmpty && empty($ret)) {
            if($this->geo) $ret = "id:{$this->geo}";
            else $ret = "не определено";
        }
        return $ret;
    }

    public function debug1() {
        $koe = $this->getAngleKoefficients();
        $max = 0;
        $cnt = self::$varMinPassCount;
        foreach($koe as $k) if($k > $max) $max = $k;
        return "enough(DSrep: $this->dst_rep > $this->geoDist && TM: $this->tm_rep > $this->geoTime && Ps: $max > $cnt)? ";
    }

    public function isReportEnough() {
        return $this->dst_rep > $this->geoDist &&
               $this->tm_rep  > $this->geoTime;
    }

    public function isDistanceEnough() {
        // $minWorkDist = $this->geoPerimeter / 2;
        // if($this->sumDst() < $minWorkDist) return false;
        $koe = $this->getAngleKoefficients();
        foreach($koe as $k) if($k > self::$varMinPassCount) return true;
        return false;
    }

    public function evaluate() {
        $this->setFlag(self::FLAG_IS_WORKING, $this->isDistanceEnough());
        echo 'Dst,';
        //CarLogPoint::setLogBulk($this->id, $this->log_pts);
        //echo 'Pts,';
        //$this->setFlag(self::FLAG_IS_WORKING, true);
        return $this->msg_cnt;
    }

    // http://www.spatialdbadvisor.com/postgis_tips_tricks/92/filtering-rings-in-polygon-postgis
    public function removeIslands($min_size = 0, $skip_calc = false) {
        global $PG, $DB;

        $ret = 0;
        if(!$this->id) return -1;

        $q = $PG->prepare("SELECT ST_NumGeometries(poly::geometry) FROM order_area WHERE _id = :i")
            ->bind('i', $this->id)
            ->execute_scalar();
        PageManager::debug($q, 'ST_NumGeometries');
        if($q > 1) {
            $PG->prepare("UPDATE order_area
                    SET poly = (SELECT ST_Collect(gg)::geography FROM (
                        SELECT filter_rings((ST_Dump(o2.poly::geometry)).geom, :s) AS gg
                        FROM order_area o2 WHERE o2._id = :i) as subq)
                    WHERE _id = :i")
                ->bind('i', $this->id)
                ->bind('s', $min_size)
                ->execute();
            PageManager::debug($PG->error, 'DumpMpErr');
        } else {
            $PG->prepare("UPDATE order_area
                    SET poly = filter_rings(poly::geometry, :s)::geography
                    WHERE _id = :i")
                ->bind('i', $this->id)
                ->bind('s', $min_size)
                ->execute();
            PageManager::debug($PG->error, 'DumpErr');
        }

        if($skip_calc) return $ret;

        $q = $PG->prepare("SELECT ST_Area(poly) FROM order_area WHERE _id = :i")
            ->bind('i', $this->id)
            ->execute_scalar();
        $ret = $q ? intval($q) : 0;

        $ret = round($ret / 10000, 2);

        $DB->prepare("UPDATE gps_order_log
                        SET ord_area = :a,
                            jnt_area = :a
                        WHERE id = :i")
            ->bind('a', $ret)
            ->bind('i', $this->id)
            ->execute();
        return $ret;
    }

    public function resetJoint() {
        global $DB;
        return $DB->prepare("UPDATE gps_order_log
                        SET jnt_area = ord_area
                        WHERE id = :i")
                    ->bind('i', $this->id)
                    ->execute();
    }

    public function getSimple() {
        $ret = new stdClass();
        $a = ['id', 'firm', 'geo', 'car', 'trailer', 'ord', 'techop', 'top_cond', 'dt_beg', 'dt_end'];
        foreach($a as $k) {
            $v = $this->$k;
            if(is_a($v, 'DateTime')) $v = $v->format('Y-m-d H:i:s');
            $ret->$k = $v;
        }
        return $ret;
    }

    public function getJson() {
        $ret = new stdClass();
        foreach($this as $k => $v) {
            if(is_a($v, 'DateTime')) $v = $v->format('Y-m-d H:i:s');
            $ret->$k = $v;
        }
        return $ret;
    }

    /**
     * Get export version
     * @param int $year Operation year
     * @return ExportOrderWork
     */
    public function getExportJson($year) {
        $field = GeoFence::getField($this->geo);
        $crop_by = $field->getCropByYear($year);
        $field_name = $field->id ? $field->name : GeoFence::getFieldName($this->geo);
        $ret = new ExportOrderWork();
        $ret->begin = $this->dt_beg->format('Y-m-d H:i:s');
        $ret->end   = $this->dt_end->format('Y-m-d H:i:s');
        $ret->field = $field_name;
        $ret->area  = $this->jnt_area;
        $ret->crop  = $crop_by->guid;
        $ret->crop_name = $crop_by->name;
        return $ret;
    }

    public static function close($id) {
        global $DB;
        $q = $DB->prepare("UPDATE gps_order_log
                        SET flags = flags | :f
                        WHERE id = :i")
            ->bind('f', self::FLAG_IS_CLOSED)
            ->bind('i', $id)
            ->execute();
    }

    public static function beginOf($log_id = 0) {
        global $DB;
        $q = $DB->prepare("SELECT dt_beg FROM gps_order_log WHERE id = :i")
            ->bind('i', $log_id)
            ->execute_scalar();
        return new DateTime($q ? $q : '2000-01-01');
    }

    public static function readGeoParameters($gid = 0, $max = 0) {
        global $DB;
        $ret = array(
            'p' => 0,         // perimeter
            'd' => 1000000,   // max allowed high-speed distance
            't' => self::ONE_DAY      // max allowed high-speed time
        );
        if(!$gid) return $ret;
        $pr = floatval($DB->select_scalar("SELECT pr FROM gps_geofence WHERE id = $gid"));
        if($pr) {
            $ret['p'] = intval($pr); // m
            $ret['d'] = intval(sqrt($pr)); // m
            if($max) {
                //   sec  = 3600 *     ((     m    / 1000) / km/h)
                $ret['t'] = intval(3600 * ($ret['d'] / 1000) / $max);
            }
        }
        return $ret;
    }

    public static function getMessagesBounds($messages) {
        $min_x = 200;
        $min_y = 200;
        $max_x = -200;
        $max_y = -200;
        foreach($messages as $m) {
            if($m->pos->x < $min_x) $min_x = $m->pos->x;
            if($m->pos->y < $min_y) $min_y = $m->pos->y;
            if($m->pos->x > $max_x) $max_x = $m->pos->x;
            if($m->pos->y > $max_y) $max_y = $m->pos->y;
        }
        if($min_x ==  200) $min_x = 28.4711800;
        if($max_x == -200) $max_x = 28.4711801;
        if($min_y ==  200) $min_y = 49.2325813;
        if($max_y == -200) $max_y = 49.2325814;
        return [$min_x, $min_y, $max_x, $max_y];
    }

    public static function getZonesCache($messages) {
        $bnd = self::getMessagesBounds($messages);
        return GeoFence::getByBounds($bnd[0], $bnd[2], $bnd[1], $bnd[3]);
    }

    public static function getApi() {
        if(self::$api == null) self::$api = new WialonApi();
        return self::$api;
    }

    public static function restartApi() {
        return self::$api->restart();
    }

    public static function getMessages($iid, $beg, $end = '') {
        global $PG;
        if(!$end) $end = $beg;
        self::$error = '';
        $w = self::getApi();
        $ret = $w->getMessages($iid, $beg, $end);
        if(!$ret) {
            if($w->err) {
                if($w->needRestart()) {
                    $ok = self::restartApi();
                    if($ok) {
                        $ret = $w->getMessages($iid, $beg, $end);
                    }
                }
                if(!$ret) {
                    self::$error = $w->myErrorText();
                }
            }
        }
        return $ret;
    }

    /**
    * Logs by order
    *
    * @param WorkOrder $ord work order
    * @return OrderLog[]
    */
    public static function getControl(WorkOrder $ord) {
        $flt = [
            ['`ord` = :o','o', $ord->id],
            'geo'
        ];
        return self::getList($flt, 'dt_beg, id');
    }

    /**
    * Working geozone ids by order
    *
    * @param WorkOrder $ord work order
    * @return int[]
    */
    public static function getWorkingGeoZoneIds(WorkOrder $ord) {
        $flt = [
            ['`ord` = :o','o', $ord->id],
            ['flags & :f','f', self::FLAG_IS_WORKING],
            'geo > 0',
            'geo_only'
        ];
        return self::getList($flt, 'id');
    }

    public static function getLastTime(WorkOrder $ord) {
        global $DB;
        $q = $DB->prepare("SELECT MAX(ev_time) FROM gps_order_log WHERE car = :c AND ord = :o")
                ->bind('o', $ord->id)
                ->bind('c', $ord->car->id)
                ->execute_scalar();
        return intval($q);
    }

    public static function getTimezone() {
        if(self::$tmz == null) {
            self::$tmz = new DateTimeZone(self::DEF_TIMEZONE);
        }
        return self::$tmz;
    }

    public static function dateFromUTC($utc) {
        $dt = new DateTime("@$utc");
        $dt->setTimezone(self::getTimezone());
        return $dt;
    }

    public static function arrResetToLog($ojs, $obj = '') {
        global $DB;
        if(!$ojs && $DB->error) {
            WorkOrder::$err[] = "findJnt({$obj}):{$DB->error}";
            return;
        }
        if(!$ojs) {
            WorkOrder::$err[] = "jntToClean({$obj}):[]";
        } else {
            WorkOrder::$err[] = "jntToClean({$obj}):";
            foreach($ojs as $oj) WorkOrder::$err[] = ' - ' . json_encode($oj);
        }
    }

    public static function resetAreas($oids) {
        global $PG, $DB;
        $ods = is_array($oids) ? implode(',', $oids) : $oids;
        $logs = self::getList(['id_only', "`ord` IN($ods)"], 'id');
        $log_ids = implode(',', $logs);

        WorkOrder::$err[] = "log_to_clean($log_ids)";

        $q = $PG->prepare("DELETE FROM order_area WHERE _id IN ($log_ids)")
                ->execute();
        WorkOrder::$err[] = $q ? 'PG:ok' : "PG:{$PG->error}";

        $q = $PG->prepare("DELETE FROM order_log_line WHERE log_id IN ($log_ids)")
                ->execute();
        WorkOrder::$err[] = $q ? 'PG_LN:ok' : "PG_LN:{$PG->error}";

        $q = $PG->prepare("UPDATE order_log_point SET log_id = 0 WHERE ord_id IN ($ods)")
                ->execute();
        WorkOrder::$err[] = $q ? 'PG_PT:ok' : "PG_PT:{$PG->error}";

        $ojs = OrderJointItem::findJoints($logs);
        self::arrResetToLog($ojs, 'log');
        foreach ($ojs as $oj) $oj->delete();

        $ojs = OrderJointItem::findJoints('' . $ods, 'ord_id');
        self::arrResetToLog($ojs, 'ord');
        foreach ($ojs as $oj) $oj->delete();

        $DB->prepare("UPDATE gps_order_log SET
                        ord_area = 0
                        WHERE id IN($log_ids)")
            ->execute();
    }

    public static function resetParser($oids) {
        global $PG, $DB;
        $ods = is_array($oids) ? implode(',', $oids) : $oids;

        $PG->prepare("DELETE FROM order_log_point WHERE ord_id IN($ods)")
            ->execute();

        $logs = OrderLog::getList(['id_only', "`ord` IN($ods)"], 'id');
        $log_ids = implode(',', $logs);
        if($log_ids) {
            $PG->prepare("DELETE FROM order_log_line WHERE log_id IN($log_ids)")
                ->execute();

            $DB->prepare("DELETE FROM gps_order_log WHERE id IN($log_ids)")
                ->execute();
        }
    }

    /**
     * @return OrderLog
     */
    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new OrderLog($id);
        }
        return self::$cache[$id];
    }

    public static function getCount($beg, $geos, $top) {
        global $DB;
        $dt_b = date('Y-m-d H:i:s', $beg);
        $dt_e = date('Y-m-d H:i:s', $beg + self::ONE_DAY);
        $cond = is_array($geos) ?
            sprintf('IN (%s)', implode(',', $geos)) :
            sprintf('= %d', intval($geos));
        return intval(
            $DB->prepare("SELECT COUNT(*)
                        FROM gps_order_log
                        WHERE geo $cond
                            AND techop = :t
                            -- AND top_cond = :tc
                            AND dt_beg < :de
                            AND dt_end > :db")
                ->bind('t',  $top)
                // ->bind('tc', $topc)
                ->bind('de', $dt_e)
                ->bind('db', $dt_b)
                ->execute_scalar()
        );
    }


    /**
    * Создание списка контролей скорости
    *
    * @return OrderLog[]
    */
    public static function makeList() {
        $ret = [];
        return $ret;
    }

    public static function silentTime($tm) {
        $h = intval(date('H', $tm));
        foreach(self::$varSilentPeriods as $per) {
            if($h >= $per[0] && $h < $per[1]) return true;
        }
        return false;
    }

    public static function silentEnough($tm) {
        if(!self::silentTime($tm)) return false;
        $dxTime = time() - $tm;
        return $dxTime >= self::$varCloseOrderIfSilent;
    }

    public static function easter() {
        return gzdecode(base64_decode("H4sICCpxg14AA2Vhc3Rlci50eHQAU1JNjsmLyVOAgHh9EIQz0UXgE
                                jANUCXoDJhSMAuqFkkF3GgUcRSj0czDqhrJeGzuxm54TJ4SFwCEYwzN9AAAAA=="));
    }

    /**
    * Список контролей скорости
    *
    * @param string[] массив условий
    * @param string сортировка
    * @param string LIMIT
    * @return OrderLog[]
    */
    public static function getList($flt = array(), $ord = '', $lim = '') {
        global $DB;
        self::$total = 0;
        $empty = true;
        $geo = false;
        $obj = true;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        $fld  = '';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = $fld = 'id';
                $obj  = false;
            } elseif($it == 'geo_only') {
                $flds = 'DISTINCT geo';
                $fld = 'geo';
                $obj  = false;
            } elseif($it == 'non_empty') {
                $empty = false;
            } elseif($it == 'geo') {
                $geo = true;
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
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM gps_order_log $add $order $limit");
        // echo PHP_EOL . $DB->sql . PHP_EOL;
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
            // echo "$k = $v\n";
        }
        $rows = $DB->execute_all();
        self::$error = $DB->error;
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new OrderLog($row, $geo) : ($fld ? intval($row[$fld]) : $row);
        }
        if(!$ret && $fld && !$empty) $ret[] = [-1];
        return $ret;
    }
}