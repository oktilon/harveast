<?php
class CarLogItem {
    public $id = 0;
    public $log_id = 0;
    public $tm = 0;
    public $order_line = 0;
    public $geo = 0;
    public $dst = 0;
    public $flags = 0;
    public $tm_tot = 0;
    public $tm_move = 0;
    public $tm_last = 0;
    public $tm_eng = 0;
    public $spd_sum = 0;
    public $reason = 0;
    public $note = '';
    public $note_spd = '';
    public $dt_last = null;

    public $geos = [];

    private static $cache = [];
    private static $geo_cache = [];
    public static $total = 0;
    public static $lastGeo = 0;

    public static $is_debug = false;

    public static $tmMax    = 1440; // 24*60
    public static $minSpeed = 3;
    public static $stayTime = 4; // lesss then 4 min
    public static $timeStep = 15;
    public static $timeFar  = 350; // 5 min
    public static $moveTot  = 800; // meters
    public static $moveIgn  = 3600; // 1 hour

    public static $colors = [
        'sg' => 'FFFFFF99',
        's'  => 'FFF6A555',
        'mg' => 'FF99FF99',
        'm'  => 'FF007BFF',
    ];

    public function __construct($arg = 0, $msg = null) {
        global $DB;
        $this->dt_last = new DateTime('2000-01-01');
        if(is_object($arg) && is_a($arg, 'CarLog')) {
            $this->log_id = $arg->id;
            if(is_a($msg, 'WialonMessage')) {
                $dt = OrderLog::dateFromUTC($msg->t);
                $this->tm = $this->evalTm($dt);
            } elseif(is_numeric($msg)) {
                $this->tm = $this->timeFromIx($msg);
                $this->geo = -1;
                $tmz = $arg->getZeroTms();
                $tmx = $tmz + ($this->tm * 60);
                $dt = OrderLog::dateFromUTC($tmx);
            }
            $this->order_line = self::findOrderLine($arg->car, $dt->format('Y-m-d H:i:s'));
            $this->tm_last = intval($dt->format('U'));
            return;
        }
        if(is_numeric($arg)) {
            $id = intval($arg);
            if($id == 0) return;
            $arg = $DB->select_row("SELECT * FROM gps_car_log_item WHERE id = $id");
        }
        if(is_array($arg)) {
            foreach($arg as $key => $val) $this->$key = self::getProperty($key, $val);
        }
    }

    public static function getProperty($k, $v) {
        switch($k) {
            case 'note_spd':
            case 'note': return $v;
            case 'dt_last': return new DateTime($v);
            case 'geos': return self::setGeos($v);
        }
        return intval($v);
    }

    public function getIx($tmGap = 0) {
        $delim = $tmGap <= 0 ? self::$timeStep : $tmGap;
        return intval($this->tm / $delim);
    }

    public function timeFromIx($ix) {
        return $ix * self::$timeStep;
    }

    public function getGeos() {
        $ret = [];
        foreach($this->geos as $g => $t) $ret[] = "$g:$t";
        return implode(',', $ret);
    }

    public static function setGeos($txt = '') {
        $ret = [];
        $a = explode(',', $txt);
        foreach ($a as $s) {
            $p = explode(':', $s);
            if(count($p) != 2) continue;
            $ret[intval($p[0])] = intval($p[1]);
        }
        return $ret;
    }

    public static function setOrderLine($cid, $ol, $intb, $inte) {
        global $DB;
        $zero = CarLog::evalZeroTms($intb);
        $dt   = date('Y-m-d', $zero);
        $tm_b = intval(($intb - $zero) / 60);
        $tm_e = intval(($inte - $zero) / 60);
        $ret = 0;
        $q = $DB->prepare("UPDATE gps_car_log_item i
                            JOIN gps_car_log g ON g.id = i.log_id
                            SET i.order_line = :ol
                            WHERE g.car = :c
                                AND g.dt = :dt
                                AND i.tm BETWEEN :b AND :e")
                ->bind('ol', $ol->id)
                ->bind('c', $cid)
                ->bind('dt', $dt)
                ->bind('b', $tm_b)
                ->bind('e', $tm_e)
                ->execute();
        if($q) $ret = $DB->affectedRows();
        if($tm_e > self::$tmMax) {
            $beg = $zero + self::$tmMax * 60;
            $ret += self::setOrderLine($cid, $ol, $beg, $inte);
        }
        return $ret;
    }

    public static function evalTimeString($tm) {
        $m = $tm % 60;
        $h = intval(($tm - $m) / 60);
        $m += CarCache::$begM;
        $h += CarCache::$begH;
        if($m >= 60) {
            $m = $m % 60;
            $h++;
        }
        if($h > 23) $h -= 24;
        return sprintf("%02d:%02d", $h, $m);
    }

    public static function formatTime($tm) {
        if($tm == 0) return ' ';
        $h = intval($tm / 60);
        $m = $tm - 60 * $h;
        return sprintf("%02d:%02d", $h, $m);
    }

    public function timeString() { return self::evalTimeString($this->tm); }

    public static function getOrderLine($log_id) {
        global $DB;
        $q = $DB->prepare('SELECT order_line
                        FROM gps_car_log_item
                        WHERE log_id = :id
                            AND order_line > 0
                        GROUP BY order_line
                        ORDER BY COUNT(*) DESC
                        LIMIT 1')
                ->bind('id', $log_id)
                ->execute_scalar();
        if(!$q) return 0;
        return intval($q);
    }

    public function findOrderLine($cid, $dt) {
        global $DB;
        $q = $DB->prepare("SELECT ol.id FROM gps_orders o
                            LEFT JOIN gps_order_lines ol ON ol.order_id = o.id
                            WHERE o.car = :c
                                AND ol.dt_begin <= :d
                                AND ol.dt_end >= :d
                            LIMIT 1")
                ->bind('c', $cid)
                ->bind('d', $dt)
                ->execute_scalar();
        $olid = $q ? intval($q) : 0;
        if(self::$is_debug) echo " findOrdLn(c:{$cid}, d:{$dt})=$olid {$DB->error} ";
        return $olid;
    }

    public static function getRating($log_id) {
        global $DB;
        $q = $DB->prepare('SELECT SUM(dst) sd,
                             SUM(tm_eng) te,
                             SUM(tm_move) tm,
                             SUM(IF(order_line > 0, 1, 0)) oo,
                             SUM(IF(geo > 0, 1, 0)) gg
                        FROM gps_car_log_item
                        WHERE log_id = :id')
                ->bind('id', $log_id)
                ->execute_row();
        if(!$q) return 6;
        $dst = intval($q['sd']);
        $tme = intval($q['te']);
        $tmm = intval($q['tm']);
        $ord = intval($q['oo']) > 0;
        $geo = intval($q['gg']) > 0;
        $mv = $dst > self::$moveTot || $tme > self::$moveIgn || $tmm > self::$moveIgn;
        if($mv) {
            $ord = $ord ? 2 : 0;
            $inv = $geo ? 0 : 1;
            return 1 + $ord + $inv;
        }
        $inv = $ord ? 0 : 1;
        return 5 + $inv;
    }

    public function thisTime($utc) {
        $dt = OrderLog::dateFromUTC($utc);
        return $this->tm == self::evalTm($dt);
    }

    public function setReason($dat) {
        $spd = 0;
        if($dat->r == 0) {
            $this->note_spd = $dat->n;
            $spd = 1;
        } else {
            $this->reason = $dat->r;
            $this->note = $dat->n;
            $this->dt_last = new DateTime();
        }
        $r = $this->save();
        CarLogItemHistory::create($this, $spd);
        return $r;
    }

    public function resetReason($dat) {
        $this->reason = 0;
        $this->note = '';
        $this->dt_last = new DateTime();
        $r = $this->save();
        CarLogItemHistory::create($this, $dat->s);
        return $r;
    }

    public function setSpeedNote($dat) {
        $this->note_spd = $dat->n;
        CarLogItemHistory::create($this, 1);
        return $this->save();
    }

    public static function evalHour($dt) {
        $h = intval($dt->format('H')) - CarCache::$begH;
        if($h < 0) $h = 24 + $h;
        return $h;
    }

    public static function evalMinute($dt) {
        $m = intval($dt->format('i')) - CarCache::$begM;
        if($m < 0) $m = 60 + $m;
        return $m;
    }

    public static function evalTm($dt) {
        $h = self::evalHour($dt);
        $m = self::evalMinute($dt);
        $b = $m - $m % self::$timeStep;
        return $h*60 + $b;
    }

    public function dist($m, $pm = null, $k = 1) {
        if($pm == null) {
            return 0;
        }
        $d = intval($k * StPolygon::distance($m->pos, $pm->pos));
        return $d;
    }

    public function isIgnitionOn($msg) {
        return false;
    }

    public function append(WialonMessage $msg, WialonMessage $pm = null) {
        if($msg->t < $this->tm_last) return;

        $dt = $msg->t - $this->tm_last;
        if($dt > 900) {
            if(self::$is_debug) {
                echo "\nappend \033[91m{$dt}\033[0m = \033[35m" . date('Y-m-d H:i:s', $msg->t) . "\033[33m - \033[35m" . date('Y-m-d H:i:s', $this->tm_last) . "\033[0m\n";
            }
            syslog(LOG_CRIT, "bigtime append $dt=(" . date('Y-m-d H:i:s', $msg->t) . ")-(" . date('Y-m-d H:i:s', $this->tm_last) . ")");
        }
        $this->spd_sum += ($msg->pos->s * $dt);
        $this->tm_tot += $dt;
        if($msg->pos->s >= self::$minSpeed) {
            $this->tm_move += $dt;
            $this->dst += $this->dist($msg, $pm);
        }
        if($this->isIgnitionOn($msg)) {
            $this->tm_eng += $dt;
        }

        $this->tm_last = $msg->t;
        // GEO
        $lst = GeoFence::findPointFieldFast($msg->pos);
        $gid = 0;
        foreach($lst as $id) {
            if(!$gid) $gid = $id;
            if(isset($this->geos[$id])) {
                $gid = $id;
                break;
            }
        }
        if(!isset($this->geos[$gid])) $this->geos[$gid] = 0;
        $this->geos[$gid] += $dt;
        arsort($this->geos);
        $fst = key($this->geos);
        if($fst == 0 && count($this->geos) > 1) {
            $zTm = current($this->geos);
            $nTm = next($this->geos);
            if($nTm * 3 >= $zTm) $fst = key($this->geos);
        }
        $this->geo = $fst;
        self::$lastGeo = $fst;
    }

    public function close(CarLog $log, WialonMessage $msg, WialonMessage $pm = null) {
        $zero = $log->getZeroTms();
        $max = $zero + ($this->tm + self::$timeStep) * 60;
        if($msg->t < $this->tm_last) return;
        $far = $msg->t - $this->tm_last;
        $mid = $msg->t > $max ? $max : $msg->t;
        $dt  = $mid - $this->tm_last;
        if($dt < 0) return;

        if($dt > 900) {
            if(self::$is_debug) {
                echo "\nclose \033[91m{$dt}\033[0m = \033[35m" . date('Y-m-d H:i:s', $mid) . "\033[93m - \033[35m" . date('Y-m-d H:i:s', $this->tm_last) . "\033[0m\n";
                echo "\033[94mmsg\033[0m = \033[35m" . date('Y-m-d H:i:s', $msg->t) . "\033[0m\n";
                echo "\033[94mmax\033[0m = \033[35m" . date('Y-m-d H:i:s', $max) . "\033[0m\n";
                echo "\033[94mfar\033[0m = \033[35m" . $far . "\033[0m\n";
            }
            syslog(LOG_CRIT, "bigtime close $dt=(" . date('Y-m-d H:i:s', $mid) . ")-(" . date('Y-m-d H:i:s', $this->tm_last) . ")");
            syslog(LOG_CRIT, "bigtime msg=(" . date('Y-m-d H:i:s', $msg->t) . ")");
            syslog(LOG_CRIT, "bigtime max=(" . date('Y-m-d H:i:s', $max) . ")");
            syslog(LOG_CRIT, "bigtime far=" . $far . "");
        }

        if($far < self::$timeFar) {
            $this->spd_sum += ($msg->pos->s * $dt);
            $this->tm_tot += $dt;
            if($msg->pos->s >= self::$minSpeed) {
                $this->tm_move += $dt;
                $k = $far ? ($dt / $far) : 0;
                $this->dst += $this->dist($msg, $pm, $k);
            }
            if($this->isIgnitionOn($msg)) {
                $this->tm_eng += $dt;
            }
        }
        $this->tm_last = $mid;
    }

    public function save(CarLog $log = null) {
        global $DB;
        if($log != null) {
            if(!$this->log_id) $this->log_id = $log->id;
            if(!$this->log_id) return false;
        }
        // $sName, $obj = null, $info = [], $sIdFieldName = 'id', $json = false, $db = null, $debug = false
        $t = new SqlTable('gps_car_log_item', $this, ['geos'], 'id', false, null, self::$is_debug);
        $t->addFld('geos', $this->getGeos());
        //$t->debug = true;
        $r = $t->save($this);
        //if(!$r) echo "/* {$DB->sql} */";
        return $r;
    }

    public function getSpeed() {
        return $this->tm_tot == 0 ? 0 : round($this->spd_sum / $this->tm_tot);
    }

    public static function convertToMinutes($seconds) {
        return round($seconds / 60);
    }

    public function getMoveTime() {return self::convertToMinutes($this->tm_move); }
    public function getStayTime() {return self::$timeStep - $this->getMoveTime(); }

    public function getSimple($tmBeg) {
        $d = $this->dt_last->format('Y') == 2000 ? 0 : intval($this->dt_last->format('U'));
        $nnh = CarLogItemHistory::getLast($this->id, 0);
        $nsh = CarLogItemHistory::getLast($this->id, 1);
        return [
            'i' => $this->tm,// - $tmBeg,
            't' => round($this->tm_move / 60),
            // z - stay time
            'w' => $this->tm_tot,
            's' => $this->getSpeed(),
            'r' => $this->reason,
            'n' => $nnh->jsonArray(),
            'q' => $nsh->jsonArray(),
            'g' => $this->geo,
            'd' => $d
        ]; // test
    }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new CarLogItem($id);
        }
        return self::$cache[$id];
    }

    public static function getGeo($id) {
        if(!isset(self::$geo_cache[$id])) {
            self::$geo_cache[$id] = GeoFence::getFieldName($id);
        }
        return self::$geo_cache[$id];
    }

    public static function filterLog($fld, $val, $oper = 'IN') {
        global $DB;
        $txt = $oper == 'IN' ? "({$val})" : $val;
        $q = $DB->prepare("SELECT DISTINCT log_id
                        FROM gps_car_log_item
                        WHERE $fld $oper $txt
                        ORDER BY log_id")
                ->execute_all();
        $ret = [];
        foreach ($q as $r) {
            $ret[] = $r['log_id'];
        }
        return $ret;
    }

    public static function getList($flt = [], $ord = 'tm', $lim = '') {
        global $DB;
        self::$total = 0;
        $ret = [];
        $par = [];
        $add = [];
        foreach($flt as $it) {
            if(is_array($it)) {
                $cnd = array_shift($it);
                if($cnd) $add[] = $cnd;
                $par[$it[0]] = $it[1];
            } else {
                $add[] = $it;
            }
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc * FROM gps_car_log_item $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = new CarLogItem($row);
        }
        return $ret;
    }

    public static function reportRow($log_id, $md, $gap, $time_marks, $tot_reasons) {
        $fields  = [];
        $items   = [];
        $times   = [];
        $reasons = [];
        $totals  = [];
        $geos    = [];
        $extra   = [];
        $color   = [];
        $uncom = 0;
        $works = 0;
        $stays = 0;
        foreach($tot_reasons as $ix => $sh) $totals[$ix] = 0;
        foreach($time_marks as $tm) {
            $items[$tm] = [$md == 1 ? (15 * ($gap / 15)) : 0];
            $reasons[$tm] = [0];
            $extra[$tm] = [];
            $color[$tm] = '';
            foreach($tot_reasons as $r => $sh) $reasons[$tm][$r] = 0;
        }
        $tmFull = self::$timeStep * 60;

        $lst = self::getList(["log_id = $log_id"]);
        foreach($lst as $it) {
            $ix = $it->getIx($gap);
            $tm = $ix * $gap;
            $move = $it->getMoveTime();
            $stay = $it->getStayTime();
            $spd  = $it->getSpeed();

            $nnh = CarLogItemHistory::getLast($it->id, 0);
            //$nsh = CarLogItemHistory::getLast($it->id, 1);

            $clr = '';
            if($move < self::$stayTime) {
                if($it->geo) $clr = 'sg';
                else $clr = 's';
            } else {
                if($it->geo) $clr = 'mg';
                else $clr = 'm';
            }

            if($nnh->user->id) {
                $extra[$tm] = [
                    $nnh->note,
                    $nnh->user->fi(),
                    $nnh->user->fi()
                ];
            }

            $color[$tm] = $clr;

            if($it->geo) {
                if(!array_key_exists($it->geo, $geos)) {
                    $geos[$it->geo] = self::getGeo($it->geo);
                }
            }
            switch($md) {
                case 1:
                    $items[$tm][0] -= $move;
                    $reasons[$tm][$it->reason]++;
                    break;
                case 2:
                    $items[$tm][0] += $spd;
                    $reasons[$tm][$it->reason]++;
                    break;
            }
            if($it->reason) {
                $totals[$it->reason] += $stay;
            } else {
                if($move < self::$stayTime) $uncom++;
            }
            $works += $it->getMoveTime();
            $stays += $stay;
        }

        // echo "<pre>";
        // echo json_encode($reasons, JSON_PRETTY_PRINT);
        // echo "</pre>";
        foreach($reasons as $tm => $reasonArr) {
            $max = 0;
            $res = 0;
            foreach($reasonArr as $r => $cnt) {
                if($r > 0 && $cnt > $max) {
                    $res = $r;
                    $max = $cnt;
                }
            }
            //if($items[$tm] == 0) $items[$tm] = ' ';
            if($res) {
                $v = $items[$tm][0];
                $items[$tm][0] = $tot_reasons[$res];
            }
        }

        foreach($time_marks as $tm) {
            $add = [];
            if($color[$tm]) {
                $add = ['##Color##', self::$colors[$color[$tm]]];
            }
            if(count($extra[$tm])) {
                $add = array_merge($add, $extra[$tm]);
            }
            if(count($add)) {
                $items[$tm] = array_merge($items[$tm], $add);
            }
        }


        $works = self::formatTime($works);
        $stays = self::formatTime($stays);
        foreach($totals as $r => $tm) {
            $totals[$r] = self::formatTime($tm);
        }

        $fields = array_values($geos);

        $end = array_merge([$uncom, $works, $stays], $items, $totals);


        // echo json_encode([
        //     'geos'       => $geos,
        //     'fields'  => $fields,
        // ], JSON_PRETTY_PRINT);
        // die();

        return [$fields, $end];
    }
}