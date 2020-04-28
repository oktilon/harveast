<?php

class WorkOrder {
    public $id = 0;
    public $firm = null;
    /** @var Car */
    public $car = null;
    public $drv = 0;
    public $tech_op = null;
    public $equip = null;
    public $d_beg = null;
    public $d_end = null;
    public $created = null;
    public $updated = null;
    public $user = null;
    public $flags = 0;
    public $gps_id = 0;
    public $move_dst = 0;
    public $note = '';

    const FLAG_ORDER_DEL           = 0x1;   // deleted
    const FLAG_ORDER_LOG           = 0x2;   // parsed
    const FLAG_ORDER_AREA          = 0x4;   // area calculated
    const FLAG_ORDER_JOINT         = 0x8;   // joint calculated
    const FLAG_ORDER_RECALC        = 0x10;  // checked
    const FLAG_ORDER_DBL_TRACK     = 0x20;  // parsed till 6 hour
    const FLAG_ORDER_FUTURE_YEAR   = 0x40;  // area fast calculated
    const FLAG_ORDER_INVALID_GEO   = 0x80;  // joint fast calculated
    const FLAG_ORDER_NO_GPS        = 0x100; // has work (not gray)
    const FLAG_ORDER_NO_FLDWORK    = 0x200; // calc area using double track
    const FLAG_ORDER_NO_WIDTH      = 0x400;
    const FLAG_ORDER_NO_MSGS       = 0x800;

    private static $cache = [];
    public  static $total = 0;
    public  static $dbg   = [];
    public  static $err   = [];

    public static $flags_nm = [
        ['f'=>self::FLAG_ORDER_DEL,           'n'=>'Deleted',                    'o' => 4,  'i'=>'far fa-trash',          'c'=>'#ff0000'],

        ['f'=>self::FLAG_ORDER_LOG,           'n'=>'Parsed',                     'o' => 5,  'i'=>'far fa-crop',           'c'=>'#034605'],
        ['f'=>self::FLAG_ORDER_AREA,          'n'=>'Area',                       'o' => 6,  'i'=>'far fa-calculator',     'c'=>'#034605'],
        ['f'=>self::FLAG_ORDER_JOINT,         'n'=>'Joint area',                 'o' => 7,  'i'=>'far fa-check-square',   'c'=>'#034605'],
        ['f'=>self::FLAG_ORDER_RECALC,        'n'=>'Recalculation',              'o' => 1,  'i'=>'far fa-sync',           'c'=>'#007bff'],

        ['f'=>self::FLAG_ORDER_DBL_TRACK,     'n'=>'Double track',               'o' => 0,  'i'=>'far fa-clone'      ,    'c'=>'#753aff'],
        ['f'=>self::FLAG_ORDER_FUTURE_YEAR,   'n'=>'Next year',                  'o' => 14, 'i'=>'far fa-hand-point-up',  'c'=>'#3277a8'],
        ['f'=>self::FLAG_ORDER_INVALID_GEO,   'n'=>'Invalid geometry',           'o' => 0,  'i'=>'fas fa-infinity',       'c'=>'#A40000'],

        ['f'=>self::FLAG_ORDER_NO_GPS,        'n'=>'No Gps',                     'o' => 9,  'i'=>'far fa-warning',        'c'=>'#CE5C00'],
        ['f'=>self::FLAG_ORDER_NO_FLDWORK,    'n'=>'No fieldwork',               'o' => 10, 'i'=>'far fa-car',            'c'=>'#555753'],
        ['f'=>self::FLAG_ORDER_NO_WIDTH,      'n'=>'Operation width is unknown', 'o' => 11, 'i'=>'far fa-exclamation-circle', 'c'=>'#A40000'],
        ['f'=>self::FLAG_ORDER_NO_MSGS,       'n'=>'No messages',                'o' => 12, 'i'=>'far fa-times-circle',   'c'=>'#A40000'],
    ];

    public function __construct($arg = 0) {
        global $DB, $PM;
        foreach($this as $key => $val) {
            if($val === null) $this->$key = self::getProperty($key);
        }

        if(is_numeric($arg)) {
            $id = intval($arg);
            if($id == 0) return;
            $arg = $DB->select_row("SELECT * FROM gps_orders WHERE id = $id");
        }
        if(is_array($arg) || is_object($arg)) {
            foreach($arg as $key => $val) {
                $this->$key = self::getProperty($key, $val);
            }
        }
    }

    private static function getProperty($key, $val = 0) {
        global $PM;
        switch($key) {
            case 'd_beg':
            case 'd_end': return $val === 0 ? new DateTime('2000-01-01') : new DateTime($val);
            case 'created':
            case 'updated': return $val === 0 ? new DateTime() : new DateTime($val);
            case 'firm': return Firm::get($val);
            case 'tech_op': return TechOperation::get($val);
            case 'car': return Car::get($val);
            case 'type': return WorkOrderType::get($val);
            case 'user': return $val === 0 ? $PM->user : User::get($val);
            case 'note': return $val === 0 ? '' : $val;
        }
        return intval($val);
    }

    private static function translatorFunction() {
        return [ // Flag translator
            _('Deleted'),
            _('Points finished'),
            _('Parsed'),
            _('Area'),
            _('Joint area'),
            _('Points finished'),
            _('Parsed'),
            _('Area'),
            _('Joint area'),
            _('Checked'),
            _('Good'),
            _('Double track'),
            _('Recalculation'),
            _('No Gps'),
            _('No fieldwork'),
            _('Operation width is unknown'),
            _('No messages'),
            _('Wait for start'),
            _('Exported'),
            _('Saved'),
            _('Export error'),
            _('Relocations'),
            _('Next year'),
            _('Invalid geometry'),
        ];
    }

    public function save() {
        $t = new SqlTable('gps_orders', $this, ['created', 'lines']);
        return $t->save($this);
    }

    public function valid() {
        global $DB;
        $ord = $this->firm->id > 0 &&
            $this->car->id > 0 &&
            $this->type->id > 0;
        if(!$ord) {
            $DB->error = _('Incomplete order data');
            return false;
        }
        if($this->d_end <= $this->d_beg) {
            $DB->error = _('Wrong date interval');
            return false;
        }
        foreach($this->lines as $wol) {
            if(!$wol->valid($this)) {
                //PageManager::$dbg[] = 'invalid wol ' . $wol->pos;
                return false;
            }
        }

        // intersection with others test!
        $flt = [
            ['car = :c',   'c', $this->car->id],
            ['d_beg < :e', 'e', $this->d_end->format('Y-m-d H:i:s')],
            ['d_end > :b', 'b', $this->d_beg->format('Y-m-d H:i:s')]
        ];
        if($this->id) $flt[] = "id != {$this->id}";
        $lst = self::getList($flt);
        if(count($lst) == 0) return true;
        $err = [];
        foreach ($lst as $o) {
            self::$err[] = $o->getJson();
            $err[] = '№' . $o->id . ' ' . $o->firm->code . '<br>' .
                    $o->d_beg->format('d.m.Y H:i:s') . '-' .
                    $o->d_end->format('d.m.Y H:i:s');
        }
        $DB->error = _("Intersection with orders:") . '<br>' . implode('<br>', $err);
        return false;
    }

    public function getBegin(WorkOrderLine $wol) {
        $ok = $this->d_beg->format('Y') < 2001 ||
              $this->d_beg->format('U') > $wol->dt_begin->format('U');
        return $ok ? $wol->dt_begin : $this->d_beg;
    }

    public function getEnd(WorkOrderLine $wol) {
        $ok = $this->d_end->format('Y') < 2001 ||
              $this->d_end->format('U') < $wol->dt_end->format('U');
        return $ok ? $wol->dt_end : $this->d_end;
    }

    public function getFlag($flag) { return ($this->flags & $flag) > 0; }
    public function setFlag($flag, $val) {
        if($val) {
            $this->flags |= $flag;
        } else {
            $this->flags &= ~$flag;
        }
    }

    public function isParsed() { return $this->getFlag(self::FLAG_ORDER_LOG); }
    public function isDoubleTrack() { return $this->getFlag(self::FLAG_ORDER_DBL_TRACK); }
    public function isAreaCalculated() { return $this->getFlag(self::FLAG_ORDER_AREA); }
    public function isRecalculated() { return $this->getFlag(self::FLAG_ORDER_RECALC); }
    public function isFutureYear() { return $this->getFlag(self::FLAG_ORDER_FUTURE_YEAR); }

    public static function allErrors() {
        return self::FLAG_ORDER_NO_GPS |
               self::FLAG_ORDER_NO_FLDWORK |
               self::FLAG_ORDER_NO_WIDTH |
               self::FLAG_ORDER_NO_MSGS;
    }

    public function getYear() {
        $year = intval($this->d_beg->format('Y'));
        if($this->isFutureYear()) $year++;
        return $year;
    }

    public function apply($data) {
        global $DB;
        self::$err = [];
        // $work = [];
        foreach($data as $key => $val) {
            if($key == 'id') continue;
            // if($key == 'works') { $work = $val; continue; }
            if($key == 'flags') {
                $this->flags &= ~0x200;
                $this->flags |= ($val & 0x200);
                continue;
            }
            $key = strtr($key, [
                'ts'         => 'car'
            ]);
            if(isset($this->$key)) {
                $this->$key = is_object($this->$key) ? self::getProperty($key, $val->id) : $val;
            }
        }

        // reset all errors
        $err = $this->allErrors();
        if($err & $this->flags) $this->flags &= ~$err;

        // $this->lines = [];
        // $this->d_beg =new DateTime('2000-01-01');
        // $this->d_end =new DateTime('2000-01-01');
        $this->updated = new DateTime();
        // foreach($work as $pos => $line) {
        //     $id = property_exists($line, 'id') ? $line->id : 0;
        //     $wol = new WorkOrderLine($id);
        //     $wol->apply($this, $pos, $line);
        //     $this->d_beg = $this->getBegin($wol);
        //     $this->d_end = $this->getEnd($wol);
        //     $this->lines[] = $wol;
        // }
        if(!$this->valid()) {
            return false;
        }

        // $beg = intval($this->d_beg->format('U'));
        $end = intval($this->d_end->format('U'));
        if($end > time() || $this->gps_id == 0) {
            $this->gps_id = $this->car->device->gps_id;
        }

        // $this->setFlag(self::FLAG_ORDER_START_WAITING, $beg > time());

        if($this->save()) {
            // foreach($this->lines as $wol) {
            //     $wol->order_id = $this->id;
            //     $wol->save();
            //     //PageManager::$dbg[] = 'line save err ' . $DB->error;
            // }
            return true;
        }
        //PageManager::$dbg[] = 'ord notsave err ' . $DB->error;
        return false;
    }

    public function addPoint(WialonMessage $msg, $gid) {
        return OrderLogPoint::addPoint($msg, $gid, $this->id);
    }

    // public function needToResetLines() {
    //     return $this->chk_dt && OrderLogPoint::hasNewPointsBefore($this->chk_dt, $this->id);
    // }

    public function resetLines() {
        $this->chk_dt = 0;
        $this->chk_geo = 0;
        $this->chk_rep = 0;
        $this->move_dst = 0;
        $this->save();
        //OrderLogPoint::resetOrder($this->id);
        $logs = OrderLog::getControl($this);
        foreach($logs as $log) {
            if($log->geo == 0) {
                $log->delete();
            } else {
                $log->reset();
            }
        }
    }

    /**
     * @return OrderLogPoint[]
     */
    public function getPoints() {
        return OrderLogPoint::getList([
            ['ord_id = :o', 'o', $this->id]
        ], 'dt');
    }

    public function enumPointsGeo() {
        return OrderLogPoint::enumPointsGeo($this->id);
    }

    public function resetArea() {
        global $DB;
        if($this->getFlag(self::FLAG_ORDER_AREA)) {
            OrderLog::resetAreas($this->id);
            $flg = self::FLAG_ORDER_AREA | self::FLAG_ORDER_JOINT | self::FLAG_ORDER_LOG;
            return $DB->prepare("UPDATE gps_orders
                SET flags = flags & ~$flg,
                    chk_dt  = 0,
                    chk_geo = 0,
                    chk_rep = 0
                WHERE id = :i")
                ->bind('i', $this->id)
                ->execute();
        } elseif($this->getFlag(self::FLAG_ORDER_JOINT)) {
            $this->setFlag(self::FLAG_ORDER_JOINT, false);
            return $this->save();
        }
        return true;
    }

    public function resetParser($force = false) {
        global $DB;
        if($this->getFlag(self::FLAG_ORDER_LOG) || $this->getFlag(self::FLAG_ORDER_PTS) || $force) {
            OrderLog::resetAreas($this->id);
            OrderLog::resetParser($this->id);

            $flg = $this->flags & (self::FLAG_ORDER_DBL_TRACK | self::FLAG_ORDER_FUTURE_YEAR); // keep this flags
            $flg |= self::FLAG_ORDER_RECALC;

            return $DB->prepare("UPDATE gps_orders
                SET flags = :f,
                    chk_dt  = 0,
                    chk_pt  = 0,
                    chk_geo = 0,
                    chk_rep = 0
                WHERE id = :i")
                ->bind('f', $flg)
                ->bind('i', $this->id)
                ->execute();
        }
        return true;
    }

    public function setToChessboard() {
        $beg = 0;
        $end = 0;
        $tot = 0;
        $logs = [];
        foreach ($this->lines as $ol) {
            //PageManager::debug($ol, 'ol');
            $intb = 0;
            $inte = 0;
            $olb = intval($ol->dt_begin->format('U'));
            $ole = intval($ol->dt_end->format('U'));

            if($beg == 0) { $intb = $olb; $beg = $olb; }
            if($end == 0) { $inte = $ole; $end = $ole; }

            if($olb < $beg) $intb = $olb;

            if($ole > $end) $inte = $ole;

            if($intb && !$inte) $inte = $beg;
            if($inte && !$intb) $intb = $end;

            if($intb && $inte) {
                $tot += CarLogItem::setOrderLine($this->car->id, $ol, $intb, $inte);
                $logs = CarLog::appendLogs($logs, $this->car->id, $intb, $inte);
                $beg = min($beg, $intb);
                $end = max($end, $inte);
            }
        }
        //PageManager::debug($tot, 'tot');
        //PageManager::debug($logs, 'logs');
        if($tot > 0) {
            foreach ($logs as $lid) {
                $cl = new CarLog($lid);
                $cl->evalOrder();
            }
        } else {
            if(count($this->lines) && $logs) {
                $ol = $this->lines[0];
                foreach ($logs as $lid) {
                    $cl = new CarLog($lid);
                    $cl->applyOrder($ol->id, $cl->rate);
                }
            }
        }
    }

    public static function timeLog($msg, $tm) {
        $n = time();
        self::$dbg[] = $msg . ' ' . ($n - $tm) . ' sec.';
        return $n;
    }

    public static function getTodayOrdersIdList($mode, $id, $implode = true) {
        global $DB;
        switch($mode) {
            case 1: // Cluster
                $DB->prepare('SELECT o.id as oid
                    FROM gps_orders o
                    JOIN spr_firms f ON f.id = o.firm
                    WHERE f.cluster = :id
                        AND DATE(o.d_beg) = :dt');
                break;

            case 2: // Tech.operation
                $DB->prepare('SELECT o.id as oid
                    FROM gps_orders o
                    JOIN gps_order_lines l ON l.order_id = o.id
                    WHERE l.tech_op = :id
                        AND DATE(o.d_beg) = :dt
                    GROUP BY o.id');
                break;
        }
        $ret = [];
        $lst = $DB->bind('id', $id)
                    ->bind('dt', date('Y-m-d'))
                    ->execute_all();
        foreach($lst as $r) $ret[] = intval($r['oid']);
        return $implode ? implode(',', $ret) : $ret;
    }

    public static function evalArea($mode = 0, $list = []) {
        $dbg = [];

        $ord_ids = $mode ? self::getTodayOrdersIdList($mode, $list[0]) :  implode(',', $list);
        if(empty($ord_ids)) {
            throw new Exception(_('No orders found'), 400);
        }

        /**
         * @var JointOperation[]
         */
        $opers = [];
        $jo = new JointOperation();

        $logs = OrderLog::getList([
            "ord IN($ord_ids)",
            'geo > 0'
        ], 'techop, geo, dt_beg, ord');
        foreach($logs as $log) {
            $toi = $log->techop;
            $t = TechOperation::get($toi);
            if($t->isFieldOperation()) {
                if($jo->isNewOperation($toi)) {
                    $jo = new JointOperation($t);
                    $opers[] = $jo;
                }
                $jo->addLog($log);
            }
        }
        // PageManager::debug($logs, 'logs');
        // PageManager::debug($jo, 'jo');
        if($jo->top->id == 0) throw new Exception(_('No fieldwork'), 400);
        if(empty($opers)) throw new Exception(_('No work found'), 400);

        $ret = [];
        $tot = 0;
        foreach($opers as $jo) {
            $tot += $jo->evalOperationArea();
            $ret[] = $jo->getJson();
        }
        return [
            'area' => $tot,
            'tops' => $ret
        ];
    }


    public function getJson() {
        $ret = new stdClass();
        foreach($this as $key => $val) {
            if(in_array($key, ['created'])) continue;
            if(is_a($val, 'DateTime')) $val = $val->format('Y-m-d H:i:s');
            if(is_object($val) && method_exists($val, 'getSimple')) {
                $val = $val->getSimple();
            }
            if($key == 'lines') {
                $val = [];
                if($this->lines) foreach($this->lines as $line) $val[] = $line->getSimple();
            }
            $ret->$key = $val;
        }
        return $ret;
    }

    /**
     * Create export JSON
     *
     * @param OrderLog[] $logs
     * @param int $year Operation year
     *
     * @return stdClass
     */
    public function getExportJson($logs, $year) {
        // $line = count($this->lines) > 0 ? array_shift($this->lines) : new WorkOrderLine();
        $ret = new stdClass();
        $ret->order = $this->id;
        $ret->firm = $this->firm->guid;
        $ret->car = $this->car->ts_name;
        // $ret->car_model = $this->car->model->guid;
        // $ret->car_model_name = $this->car->model->name;
        $ret->car_number = $this->car->ts_number;
        $ret->double_trace = $this->isDoubleTrack();
        $ret->driver = '$this->driver->guid';
        $ret->driver_name = '$this->driver->name';
        $ret->techop = $this->tech_op->guid;
        $ret->techop_name = $this->tech_op->name;
        // $ret->techop_cond = $this->tech_cond->guid;
        // $ret->techop_cond_name = $this->tech_cond->name;
        $ret->comment = $this->note;
        $ret->order_begin = $this->d_beg->format('Y-m-d H:i:s');
        $ret->order_end = $this->d_end->format('Y-m-d H:i:s');
        // crew
        // foreach($line->crew as $pp) {
        //     $ret->crew[] = new ExportOrderDriver($pp);
        // }
        // foreach($this->lines as $ln) {
        //     foreach($ln->crew as $pp) {
        //         $ret->crew[] = new ExportOrderDriver($pp);
        //     }
        // }
        // aggregation
        // $ret->trailer = $line->trailer->guid;
        // $ret->trailer_name = $line->trailer->name;
        // logs
        // $ret->movement = [];
        foreach($logs as $log) {
            if($log->geo) {
                if(!$log->isRemoved()) {
                    $ret->work[] = $log->getExportJson($year);
                } else {
                    // $ret->movement += $log->sumDst();
                }
            } else {
                // $ret->movement += $log->sumDst();
            }
        }
        // $relocs = $this->readRelocations();
        // $ret->movement = [];
        // foreach($relocs as $reloc) {
        //     $ret->movement[] = $reloc->getExportJson();
        // }

        return $ret;
    }

    public function readRelocations($fast_json = false) {
        $flt = [ "ord = {$this->id}" ];
        if($fast_json) $flt[] = 'fast_json';
        return OrderRelocation::getList($flt, 'id');
    }

    public static function updateExportFlag($order_id, $flag_on = 0, $flag_off = 0) {
        global $DB;
        $left = $flag_off ? '(flags & ~:fd)' : 'flags';
        $oper = $flag_on  ? ' | :fe'         : '';
        $expr = $left . $oper;
        if($expr == 'flags') return false;
        $DB->prepare("UPDATE gps_orders
                SET flags = {$expr}
                WHERE id = :i");
        if($flag_off) $DB->bind('fd', $flag_off);
        if($flag_on)  $DB->bind('fe', $flag_on);
        return $DB->bind('i', $order_id)
                    ->execute();
    }

    public function getLines($agg = false, $simple = false) {
        $this->lines = [];
        $flt = [
            ['order_id = :o', 'o', $this->id],
            'del = 0'
        ];
        if($agg) $flt[] = ['ord', $this];
        $ret = WorkOrderLine::getList($flt, 'pos');
        if($simple) {
            foreach ($ret as $it) {
                $this->lines[] = $it->getSimple();
            }
            return $this->lines;
        }
        return $this->lines = $ret;
    }

    public static function finalOrderFlag($fast = false, $flag = 0) {
        return ($fast ? self::FLAG_ORDER_AREA_FAST : self::FLAG_ORDER_AREA) | $flag;
    }

    public function finalNoGps() { return $this->updateCheck(true, self::FLAG_ORDER_NO_GPS); }
    public function finalNoFldWork() { return $this->updateCheck(true, self::FLAG_ORDER_NO_FLDWORK); }
    public function finalAreaNoFldWork($fast) {
        $fin = !$fast;
        return $this->updateCheck($fin, self::finalOrderFlag($fast, self::FLAG_ORDER_NO_FLDWORK), $fast);
    }
    public function finalAreaNoWidth($fast) {
        $fin = !$fast;
        return $this->updateCheck($fin, self::finalOrderFlag($fast, self::FLAG_ORDER_NO_WIDTH), $fast);
    }

    public function finalNoMessages() {
        $flag = 0;
        if($this->chk_dt == 0) $flag = self::FLAG_ORDER_NO_MSGS;
        return $this->updateCheck(true, $flag);
    }

    public static function canWriteFastJoint($oid) {
        global $DB;
        $q = $DB->prepare("SELECT COUNT(*) FROM gps_orders
                    WHERE id = :i AND flags & :f = 0")
                ->bind('i', $oid)
                ->bind('f', self::FLAG_ORDER_JOINT)
                ->execute_scalar();
        return intval($q) > 0;
    }

    public function updateCheckPoints($final, $fast) {
        global $DB;
        $flgOn  = 0;
        $flgOff = 0;

        if($final) {
            $flgOff = self::FLAG_ORDER_RECALC;
            $flgOn  = self::FLAG_ORDER_PTS;
        } elseif($fast) {
            $flgOn  = self::FLAG_ORDER_PTS_FAST;
        }
        $this->setFlag($flgOn, true);
        $this->setFlag($flgOff, false);

        return $DB->prepare("UPDATE gps_orders
                SET chk_pt  = :d
                  , flags   = (flags & ~:foff) | :fon
                WHERE id = :i")
            ->bind('d', $this->chk_pt)
            ->bind('foff', $flgOff)
            ->bind('fon',  $flgOn)
            ->bind('i', $this->id)
            ->execute();
    }
    public function updateCheck($final, $flg = 0, $fast = false) {
        global $DB;
        $flgOn  = 0;
        $flgOff = 0;

        if($final) {
            $flgOff = self::FLAG_ORDER_RECALC;
            $flgOn  = self::FLAG_ORDER_LOG | self::FLAG_ORDER_PTS | $flg;
        } elseif($fast) {
            $flgOn = self::FLAG_ORDER_LOG_FAST;
        }
        $this->setFlag($flgOn, true);
        $this->setFlag($flgOff, false);

        return $DB->prepare("UPDATE gps_orders
                SET chk_dt  = :d
                  , chk_geo = :g
                  , chk_rep = :r
                  , flags   = (flags & ~:foff) | :fon
                WHERE id = :i")
            ->bind('d', $this->chk_dt)
            ->bind('g', $this->chk_geo)
            ->bind('r', $this->chk_rep)
            ->bind('foff', $flgOff)
            ->bind('fon',  $flgOn)
            ->bind('i', $this->id)
            ->execute();
    }

    /**
     * Finish area calculation
     *
     * @param int  $dst  distance
     * @param bool $fast fast mode
     *
     * @return void
     */
    public function finishArea($dst, $fast) {
        global $DB;
        $flg = $this->finalOrderFlag($fast); //self::FLAG_ORDER_AREA;
        $this->setFlag($flg, true);
        return $DB->prepare("UPDATE gps_orders
                SET flags = flags | $flg,
                    move_dst = :d
                WHERE id = :i")
            ->bind('i', $this->id)
            ->bind('d', $dst)
            ->execute();
    }

    public function resetJoint() {
        global $DB;
        $flg = self::FLAG_ORDER_JOINT;
        return $DB->prepare("UPDATE gps_orders
                SET flags = flags & ~$flg
                WHERE id = :i")
            ->bind('i', $this->id)
            ->execute();
    }

    public function resetWait() {
        global $DB;
        return $DB->prepare("UPDATE gps_orders
                SET flags = flags & ~:f,
                    gps_id = :g
                WHERE id = :i")
            ->bind('f', self::FLAG_ORDER_START_WAITING)
            ->bind('g', $this->gps_id)
            ->bind('i', $this->id)
            ->execute();
    }

    public function getParsingPercent() {
        if($this->isFinalPoint()) return 100;
        $b = intval($this->d_beg->format('U'));
        $e = intval($this->d_end->format('U'));
        if($this->chk_pt < $b) return 0;
        $x = $e - $b;
        if($x <= 0) $x = 1.0;
        $per = intval(100.0 * ($this->chk_pt - $b) / $x);
        if($per > 100) $per = 100;
        if($per < 0) $per = 0;
        return $per;
    }

    /**
     * Get cached WorkOkers
     *
     * @param int Order Id
     * @return WorkOrder
     */
    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new WorkOrder($id);
        }
        return self::$cache[$id];
    }

    public static function byCar(Car $car, DateTime $dt) {
        global $DB;
        $sdt = $dt->format('Y-m-d H:i:s');
        $q = $DB->prepare("SELECT o.* FROM gps_orders o
                            LEFT JOIN gps_order_lines l ON l.order_id = o.id
                            WHERE o.car = :c
                                AND l.dt_begin <= :d
                                AND l.dt_end >= :d
                            GROUP BY o.id
                            ORDER BY l.dt_begin DESC
                            LIMIT 1")
                ->bind('c', $car->id)
                ->bind('d', $sdt)
                ->execute_row();
        if(!$q) $q = 0;
        return new WorkOrder($q);
    }

    public function updateGpsId() {
        global $DB;
        $this->gps_id = $this->car->device->gps_id;
        return $DB->prepare("UPDATE gps_orders
                            SET gps_id = :g
                            WHERE id = :i")
                    ->bind('g', $this->gps_id)
                    ->bind('i', $this->id)
                    ->execute();
    }

    public static function setInvalidGeo($oid) {
        global $DB;
        return $DB->prepare("UPDATE gps_orders
                            SET flags = flags | :f
                            WHERE id = :i")
                    ->bind('f', self::FLAG_ORDER_INVALID_GEO)
                    ->bind('i', $oid)
                    ->execute();
    }

    public function getWebixItem() {
        $this->getLines(true);
        $obj = $this->getJson();
        $obj->car->agg = $this->car->hasAggregation();
        $obj->parse = $this->getParsingPercent();
        $gd = Equipment::byGpsId($this->gps_id);
        $cd = Equipment::byGpsId($this->car->device->gps_id);
        $obj->gps_imei = $gd->imei;
        $obj->car_gps = $cd->gps_id;
        $obj->car_imei = $cd->imei;
        return $obj;
    }

    public static function getWebixArray($flt = [], $ord = 'id DESC', $lim = '') {
        $ret = [ ];
        $lst = self::getList($flt, $ord, $lim);
        foreach($lst as $it) {
            $ret[] = $it->getWebixItem();
        }
        return $ret;
    }

    public static function cacheTechOperationsAndTrailers($flt, $flt_cache, $flt_top, $flt_trailer) {
        global $DB;
        $del = self::FLAG_ORDER_DEL;
        $all   = false;
        $top = [];
        $trl = [];
        $par_top = [];
        $par_trl = [];
        $add_top = [];
        $add_trl = [];
        foreach($flt as $ix => $it) {
            $cache_item = isset($flt_cache[$ix]) ? $flt_cache[$ix] : '';
            $ok_top = $cache_item != $flt_top;
            $ok_trl = $cache_item != $flt_trailer;
            if($it == 'id_only') {
                // dummy
            } elseif($it == 'all') {
                $all = true;
            } elseif($it == 'non_empty') {
                // dummy
            } elseif($it == 'with_lines') {
                // dummy
            } elseif(is_array($it)) {
                $cond = array_shift($it);
                switch($cond) {
                    case 'fields':
                        $flds = implode(',', $it);
                        $obj = false;
                        break;

                    default:
                        if($cond) {
                            $tx = substr($cond, 0, 2) == 'id' ? "o.{$cond}" : $cond;
                            if($ok_top) $add_top[] = $tx;
                            if($ok_trl) $add_trl[] = $tx;
                        }
                        if($ok_top) $par_top[$it[0]] = $it[1];
                        if($ok_trl) $par_trl[$it[0]] = $it[1];
                        break;
                }
            } else {
                $tx = substr($it, 0, 2) == 'id' ? "o.{$it}" : $it;
                if($ok_top) $add_top[] = $tx;
                if($ok_trl) $add_trl[] = $tx;
            }
        }
        if(!$all) {
            $add_top[] = "(o.flags & $del) = 0";
            $add_trl[] = "(o.flags & $del) = 0";
        }
        $add_trl[] = 'trailer > 0';
        $add_top[] = 'tech_op > 0';

        // Techops
        $add = implode(' AND ', $add_top);
        $DB->prepare("SELECT tech_op FROM gps_orders o
                        LEFT JOIN gps_order_lines ol ON ol.order_id = o.id
                        WHERE $add
                        GROUP BY tech_op
                        ORDER BY tech_op");
        foreach($par_top as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        foreach($rows as $row) {
            $top[] = intval($row['tech_op']);
        }

        // Trailers
        $add = implode(' AND ', $add_trl);
        $DB->prepare("SELECT trailer FROM gps_orders o
                        LEFT JOIN gps_order_lines ol ON ol.order_id = o.id
                        WHERE $add
                        GROUP BY trailer
                        ORDER BY trailer");
        foreach($par_trl as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        foreach($rows as $row) {
            $trl[] = intval($row['trailer']);
        }

        return [$top, $trl];
    }

    /**
     * @return WorkOrder[]
     */
    public static function getList($flt = [], $ord = 'd_beg DESC', $lim = '') {
        global $DB;
        self::$total = 0;
        $empty = true;
        $all   = false;
        $json  = false;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        $fld  = '';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = $fld = 'id';
            } elseif($it == 'all') {
                $all = true;
            } elseif($it == 'non_empty') {
                $empty = false;
            } elseif($it == 'json') {
                $json = true;
            } elseif(is_array($it)) {
                $cond = array_shift($it);
                if($cond == 'fields') {
                    $flds = implode(',', $it);
                } else {
                    if($cond) $add[] = $cond;
                    $par[$it[0]] = $it[1];
                }
            } else {
                $add[] = $it;
            }
        }
        if(!$all) $add[] = sprintf("(flags & %u) = 0", self::FLAG_ORDER_DEL);
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM gps_orders $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $it = $flds == '*' ? new WorkOrder($row) : ($fld ? intval($row[$fld]) : $row);
            $ret[] = $flds == '*' && $json ? $it->getJson() : $it;
        }
        if(!$ret && $fld && !$empty) $ret[] = [-1];
        return $ret;
    }
}
