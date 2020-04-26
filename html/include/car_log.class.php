<?php
class CarLog {
    public $id = 0;
    public $car = 0;
    public $gps = 0;
    public $flags = 0;
    public $note = '';
    public $dt = null;
    // Aggregated info
    public $ord_line = 0;
    public $ctype = 0;
    public $top = 0;
    public $firm = 0;
    public $tr = 0;
    public $div = 0;
    public $rate = 99;
    public $mv_beg = 0;
    public $mv_end = 0;
    public $mv_calc = 0;
    // Indexes
    public $car_ix = 0;
    public $car_in = 0;
    public $ctype_ix = 0;
    public $top_ix = 0;
    public $firm_ix = 0;
    public $firm_ixc = 0;
    public $tr_ix = 0;
    public $div_ix = 0;

    public $dt_beg = null;
    public $dt_end = null;

    /** @var CarLogItem[] */
    public $items = [];

    private static $cache = [];
    public static $total = 0;
    public static $is_debug = false;

    const CL_GOOD     = 0x01;
    const CL_COMPLETE = 0x02;

    public static $mv_calc_count = 10;
    public static $mv_calc_speed = 3;

    public static $goodList = [];
    public static $mark = false;

    public function __construct($arg = 0, $utc = 0) {
        global $DB;
        $this->dt_beg = new DateTime('2000-01-01');
        $this->dt_end = new DateTime('2000-01-01');
        if(is_object($arg) && is_a($arg, 'CarCache')) {
            $this->gps = $arg->id;
            $this->car = $arg->car;
            $this->ctype = $arg->ctp;
            $this->dt  = self::getDateFromTms($utc);
            $this->checkCar();
            return;
        }
        if(is_numeric($arg)) {
            $id = intval($arg);
            if($id == 0) return;
            $arg = $DB->select_row("SELECT * FROM gps_car_log WHERE id = $id");
        }
        if(is_array($arg)) {
            foreach($arg as $key => $val) $this->$key = self::getProperty($key, $val);
        }
        if($this->id) $this->readItems();
    }

    public static function getProperty($k, $v) {
        switch($k) {
            case 'note': return $v;
            case 'dt_beg':
            case 'dt_end':
            case 'dt': return new DateTime($v);
        }
        return intval($v);
    }

    public function readItems() {
        $flt = [
            ['log_id = :l', 'l', $this->id]
        ];
        $this->items = CarLogItem::getList($flt, 'tm');
    }

    public function setFlag($flg, $on = true) {
        if($on) $this->flags |= $flg;
        else $this->flags &= ~$flg;
    }
    public function hasFlag($flg) { return ($this->flags & $flg) > 0; }
    public function isCompleted() { return $this->hasFlag(self::CL_COMPLETE); }

    public function checkCar() {
        if(empty(self::$goodList)) {
            self::readGoodList();
        }
        //$gl = json_encode(self::$goodList);
        if($this->car) {
            $c = Car::get($this->car);
            $this->ctype = $c->ts_type->id;
            $this->ctype_ix = $c->ts_type->ix;
            $this->firm = $c->owner->id;
            $this->firm_ix = $c->owner->ix;
            $this->firm_ixc = $c->owner->ixc;
            $this->car_ix = $c->ix;
            $this->car_in = $c->ixn;
            if(in_array($this->ctype, self::$goodList)) {
                $this->setFlag(self::CL_GOOD);
            }
            $this->guessOrder();
        }
    }

    public function getPreviousNote() {
        global $DB;
        $q = $DB->prepare("SELECT note, id
                        FROM gps_car_log
                        WHERE gps = :gps
                            AND car = :car
                            AND dt < :dt
                        ORDER BY dt DESC
                        LIMIT 1")
                ->bind('gps', $this->gps)
                ->bind('car', $this->car)
                ->bind('dt', $this->dt->format('Y-m-d'))
                ->execute_row();
        if($q) {
            $note = $q['note'];
            $id   = intval($q['note']);
            if($note) {
                $this->note = $note;
                $r = $this->save();
                if($r) {
                    CarLogHistory::create($this, $id);
                }
            }
        }
    }

    public static function getDateFromTms($utc) {
        $tm = OrderLog::dateFromUTC($utc);
        $hi = intval($tm->format('Hi'));
        if($hi < CarCache::$begH * 100 + CarCache::$begM) {
            $dt = CarCache::$tmBeg;
            $tm->modify("-$dt MINUTES");
        }
        $tm->modify('today');
        return $tm;
    }

    public function getTms() { return intval($this->dt->format('U')); }

    public function getZeroTms() {
        $zero = $this->getTms();
        $zero += CarCache::$begH * 3600 + CarCache::$begM * 60;
        return $zero;
    }

    public static function evalZeroTms($any) {
        $dDay = date('H', $any) < CarCache::$begH ? 1 : 0;

        return mktime(
            CarCache::$begH,
            CarCache::$begM,
            0,
            date('n', $any),
            date('j', $any) - $dDay,
            date('Y', $any)
        );
    }

    public function sameDate($utc) {
        $tm = self::getDateFromTms($utc);
        return $this->dt == $tm;
    }

    public static function appendLogs($logs, $cid, $intb, $inte) {
        global $DB;
        $ret = $logs;
        $beg = date('Y-m-d', self::evalZeroTms($intb));
        $end = date('Y-m-d', self::evalZeroTms($inte));
        $q = $DB->prepare("SELECT id FROM gps_car_log
                            WHERE car = :c
                              AND dt BETWEEN :b AND :e")
                ->bind('c', $cid)
                ->bind('b', $beg)
                ->bind('e', $end)
                ->execute_all();
        foreach($q as $row) {
            $id = intval($row['id']);
            if(!in_array($id, $ret)) $ret[] = $id;
        }
        return $ret;
    }

    public function yearBeg() {
        return intval($this->dt_beg->format('Y'));
    }

    public function append(WialonMessage $msg, WialonMessage $pm = null, $iid) {
        $item = null;
        $last = null;
        self::$mark = false;
        foreach($this->items as $it) {
            $last = $it;
            if($it->thisTime($msg->t)) {
                $item = $it;
                break;
            }
        }

        if($msg->pos->s >= CarLogItem::$minSpeed) {
            if($this->yearBeg() < 2001) {
                $this->dt_beg->setTimestamp($msg->t);
            }
            $this->dt_end->setTimestamp($msg->t);
        }

        // Start-Stop calc
        if($this->mv_calc < self::$mv_calc_count) {
            if($msg->pos->s > self::$mv_calc_speed) {
                if($this->mv_calc == 0) {
                    $this->mv_beg = $msg->t;
                }
                $this->mv_calc++;
            } else {
                $this->mv_calc = 0;
            }
        } else {
            if($msg->pos->s > self::$mv_calc_speed) {
                $this->mv_end = $msg->t;
            }
        }

        if($item == null) {
            if($last != null) $last->close($this, $msg, $pm);
            $item = new CarLogItem($this, $msg);
            if(self::$is_debug) echo "\n\t\033[1;34m[" . $item->timeString() . "]\033[0m";
            self::$mark = true;
            $this->items[] = $item;
        }
        $item->append($msg, $pm, $iid);
        return true;
    }

    public function setNote($request) {
        global $DB;
        $r = $DB->prepare("UPDATE gps_car_log SET note = :n WHERE id = :i")
                ->bind('n', $request->n)
                ->bind('i', $this->id)
                ->execute();
        if($r) {
            $this->note = $request->n;
            CarLogHistory::create($this);
        }
        return $r;
    }

    public static function setComplete(CarCache $cc) {
        global $DB;
        if(!$cc->id) return;
        $dt = date('Y-m-d 00:00:00', $cc->tm);
        return $DB->prepare("UPDATE gps_car_log
                            SET flags = flags | :flg
                            WHERE gps = :gps
                                AND dt < :dt
                                AND (flags & :flg) = 0")
                    ->bind('flg', self::CL_COMPLETE)
                    ->bind('gps', $cc->id)
                    ->bind('dt', $dt)
                    ->execute();
    }

    public function save() {
        global $DB;
        $t = new SqlTable('gps_car_log', $this, ['items']);

        if(self::$is_debug) echo "Try to save Log id={$this->id} at " . $this->dt->format('Y-m-d H:i') . "\n";

        $ret = $t->save($this);
        if(!$ret) {
            if(self::$is_debug) echo "Saved gps_car_log (id=\033[0;32m{$this->id}\033[0m) with error : \033[1;31m{$DB->error}\033[0m\n";
        }
        foreach($this->items as $it) $it->save($this);
        if(!$this->isCompleted()) $this->evalOrder();
        return $ret;
    }

    public static function getCount(CarCache $cc) {
        global $DB;
        $q = $DB->prepare("SELECT COUNT(*)
                        FROM gps_car_log
                        WHERE gps = :gps
                            AND dt > DATE(FROM_UNIXTIME(:dt))")
                ->bind('gps', $cc->id)
                ->bind('dt', $cc->dt + OrderLog::ONE_DAY)
                ->execute_scalar();
        return intval($q);
    }

    public function evalOrder() {
        $ol = CarLogItem::getOrderLine($this->id);
        $rt = CarLogItem::getRating($this->id);
        $this->applyOrder($ol, $rt);
    }

    public function guessOrder() {
        if(self::$is_debug) echo "\nguessOrder ";
        $tms = $this->getZeroTms();
        $chk = date('Y-m-d H:i:s', $tms + 100);
        if(self::$is_debug) echo "\033[1;36m{$chk}\033[0m ";
        $oid = CarLogItem::findOrderLine($this->car, $chk);
        $rt = CarLogItem::getRating($this->id);
        if(self::$is_debug) echo "found \033[1;33m{$oid}\033[0m ";
        $this->applyOrder($oid, $rt);
    }

    public function applyOrder($ol_id, $rt) {
        // $o = WorkOrder::byLine($ol_id);
        // $l = WorkOrderLine::get($ol_id);
        $upd = false;
        if($this->ord_line != $ol_id) {
            $this->ord_line = $ol_id;
            $upd = true;
        }
        // if($this->top != $l->tech_op->id) {
        //     $this->top = $l->tech_op->id;
        //     $this->top_ix = $l->tech_op->ix;
        //     $upd = true;
        // }
        // if($this->tr != $l->trailer->id) {
        //     $this->tr = $l->trailer->id;
        //     $this->tr_ix = $l->trailer->ix;
        //     $upd = true;
        // }
        // if($this->div != $o->division->id) {
        //     $this->div = $o->division->id;
        //     $this->div_ix = $o->division->ix;
        //     $upd = true;
        // }
        // if($o->firm->id && $this->firm != $o->firm->id) {
        //     $this->firm = $o->firm->id;
        //     $this->firm_ix = $o->firm->ix;
        //     $this->firm_ixc = $o->firm->ixc;
        //     $upd = true;
        // }
        if($this->rate != $rt) {
            $this->rate = $rt;
            $upd = true;
        }
        if($upd) {
            $this->save();
        }
    }

    public function getSimple() {
        $ret = new stdClass();
        $arr = ['id', 'dt', 'dt_beg', 'dt_end', 'flags', 'ctype', 'note', 'rate'];
        foreach($arr as $k) {
            $v = $this->$k;
            if($k == 'dt_beg' || $k == 'dt_end') {
                $k = 'd' . substr($k, 3, 1);
                $v = intval($v->format('U'));
            }
            if(is_object($v) && is_a($v, 'DateTime')) $v = $v->format('Y-m-d H:i:s');
            $ret->$k = $v;
        }

        $tmBeg = CarCache::$tmBeg;
        $ret->d = Device::byGpsId($this->gps)->getJson();
        $ret->wo = 0;
        $geos = [];
        $ret->g = [];
        foreach($this->items as $it) {
            if($it->geo && !in_array($it->geo, $geos)) {
                $geos[] = $it->geo;
                $g = GeoFence::get($it->geo);
                $ret->g[] = ['i' => $it->geo, 'n' => $g->n ];
            }
            if($it->order_line == 0) $ret->wo += $it->tm_move;
            $ix = $it->getIx();
            $k = "t$ix";
            $t = $it->tm;// - $tmBeg;
            $ret->$k = $it->getSimple($tmBeg);
            $ix++;
        }
        // $o = WorkOrder::byLine($this->ord_line);
        // $o->lines = [WorkOrderLine::get($this->ord_line, $o)];
        $ret->wo = intval($ret->wo / 60);
        $ret->o = []; // $o;
        $ret->c = Car::get($this->car)->getJson();
        return $ret;
    }

    public function getItems($aTm) {
        $tmBeg = CarCache::$tmBeg;
        $ixs   = [];
        $ret   = [];
        foreach($aTm as $oTm) {
            $ix = $oTm->t;
            for($i = 0; $i < $oTm->s; $i++) {
                $ixs[$ix + $i] = 0;
            }
        }
        foreach($this->items as $it) {
            $ix = $it->getIx();
            if(array_key_exists($ix, $ixs)) {
                $ret[] = $it;
                $ixs[$ix]++;
            }
        }
        // Empty cells
        foreach($ixs as $ix => $cnt) {
            if($cnt == 0) {
                $item = new CarLogItem($this, $ix);
                $ret[] = $item;
                $this->items[] = $item;
            }
        }
        return $ret;
    }

    public function getTrack($ret, $tm_last = 0, $geo = []) {
        $w = new WialonApi();
        $beg = intval($this->dt->setTime(CarCache::$begH, CarCache::$begM, 0)->format('U'));
        $end = intval($this->dt->setTime(CarCache::$begH, CarCache::$begM, 0)->modify('+1 day')->format('U'));
        if($tm_last > 0) {
            $beg = $tm_last + 1;
        } else {
            $ret->tm = $beg;
        }
        // get Route
        $ret->b = 0;
        $ret->e = 0;
        $ret->t = [];
        $lst = $w->getMessages($this->gps, $beg, $end);
        foreach($lst as $it) {
            if($ret->b == 0) $ret->b = $it->t;
            $ret->e = $it->t;
            $ret->t[] = [
                't' => $it->t,
                'x' => $it->pos->x,
                'y' => $it->pos->y,
            ];
        }
        // Geofences
        $ret->g = [];
        $has = $geo;
        foreach($this->items as $it) {
            if($it->geo && !in_array($it->geo, $has)) {
                $has[] = $it->geo;
                $g = new GeoFence($it->geo, true, true);
                if(!empty($g->poly)) {
                    $ret->g[] = [
                        'i' => $it->geo,
                        'n' => $g->n,
                        'c' => $g->c,
                        'p' => $g->poly
                    ];
                }
            }
        }
        return $ret;
    }

    /**
     * Gets CarLog by id
     * @param int $id CarLog id
     * @return CarLog
     */
    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new CarLog($id);
        }
        return self::$cache[$id];
    }

    public static function getList($flt = [], $ord = 'dt DESC', $lim = '') {
        global $DB;
        self::$total = 0;
        $ret = [];
        $par = [];
        $add = [];
        $all = false;
        foreach($flt as $it) {
            if($it == 'all') {
                $all = true;
            } elseif(is_array($it)) {
                $cnd = array_shift($it);
                if($cnd) $add[] = $cnd;
                $par[$it[0]] = $it[1];
            } else {
                $add[] = $it;
            }
        }
        if(!$all) $add[] = sprintf('flags & %d', self::CL_GOOD);
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc * FROM gps_car_log $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = new CarLog($row);
        }
        return $ret;
    }

    public static function getWebixArray($flt = [], $ord = 'dt DESC', $lim = '') {
        $lst = self::getList($flt, $ord, $lim);
        $ret = [];
        foreach($lst as $log) {
            $ret[] = $log->getSimple();
        }
        return $ret;
    }

    public static function readGoodList() {
        global $DB;
        self::$goodList = [];
        $q = $DB->prepare("SELECT car_type FROM gps_car_log_types ORDER BY car_type")
                ->execute_all();
        foreach ($q as $r) {
            self::$goodList[] = intval($r['car_type']);
        }
    }

    public function getGoodList() {
        if(empty(self::$goodList)) {
            self::readGoodList();
        }
        return self::$goodList;
    }
}