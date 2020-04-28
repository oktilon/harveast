<?php
class WorkOrderLine {
    public $id = 0;
    public $order_id = 0;
    public $pos = 0;
    public $del = 0;
    public $driver = null;
    public $dt_begin = null;
    public $dt_end = null;
    /**
     * @var TechOperation
     */
    public $tech_op = null;
    public $tech_cond = null;
    public $trailer = null;
    public $weight_station = 0;
    public $tok = 0;
    public $fld = null;

    public $agg = null;
    /**
     * @var PersonPosition[]
     */
    public $crew = [];

    private static $cache = [];
    public static $total = 0;

    public function __construct($arg = 0, $order = null) {
        global $DB;
        foreach($this as $k => $v) if($v === null) $this->$k = $this->getProperty($k);

        if(is_numeric($arg)) {
            $id = intval($arg);
            if($id == 0) return;
            $arg = $DB->select_row("SELECT * FROM gps_order_lines WHERE id = $id");
        }
        if(is_array($arg) || is_object($arg)) {
            foreach($arg as $key => $val) {
                $this->$key = $this->getProperty($key, $val);
            }
        }
        if($order && is_a($order, 'WorkOrder')) {
            $this->agg = AggregationList::byOrderLine($order, $this);
        }
        $this->readCrew();
    }

    public function getProperty($key, $val = 0) {
        switch($key) {
            case 'dt_begin':
            case 'dt_end': return $val === 0 ?
                            new DateTime() :
                            new DateTime($val);
            case 'fld': return Field::get($val);
            case 'driver': return PersonPosition::get($val);
            case 'trailer': return TrailerModel::get($val);
            case 'tech_cond': return TechOperationCondition::get($val);
            case 'agg': return AggregationList::get($val);
            case 'tech_op':
                if($val > 1000000) {
                    $top = $val % 1000000;
                    $cnd = ($val - $top) / 1000000;
                    $val = $top;
                    $this->tech_cond = TechOperationCondition::get($cnd);
                }
                return TechOperation::get($val);

            case 'crew': return self::parseCrew($val);
        }
        return intval($val);
    }

    public function readCrew() {
        global $DB;
        if(!$this->id) return;
        $lst = $DB->prepare("SELECT GROUP_CONCAT(driver_id) FROM gps_order_crew WHERE line_id = :i")
                    ->bind('i', $this->id)
                    ->execute_scalar();
        $this->crew = self::parseCrew($lst);
    }

    public static function parseCrew($drivers_list) {
        $ret = [];
        if($drivers_list) {
            $ret = PersonPosition::getList(["id IN({$drivers_list})"], 'id');
        }
        return $ret;
    }

    public function save() {
        global $DB;
        $t = new SqlTable('gps_order_lines', $this, ['agg', 'crew']);
        $ret = $t->save($this);
        if($this->id) {
            $DB->prepare('DELETE FROM gps_order_crew WHERE line_id = :l')
                ->bind('l', $this->id)
                ->execute();
            $vals = [];
            foreach($this->crew as $pp) $vals[] = "({$this->id},{$pp->id})";
            $vals = implode(',', $vals);
            if($vals) $DB->prepare("INSERT INTO gps_order_crew VALUES $vals")->execute();
        }
        return $ret;
    }

    public static function clean($ids) {
        global $DB;
        if(!$ids) return;
        $del = implode(',', $ids);
        $DB->prepare("DELETE FROM gps_order_lines WHERE id IN($del)")->execute();
    }

    public function u_time($fld = 'dt_begin') { return $this->$fld->format('U'); }
    public function u_beg() { return $this->u_time('dt_begin'); }
    public function u_end() { return $this->u_time('dt_end'); }

    public function workIntersect(WorkOrderLine $wol) {
        return $wol->u_end() > $this->u_beg() && $wol->u_beg() < $this->u_end();
    }

    public function valid(WorkOrder $o) {
        global $DB;
        if($this->del == 1) return true;
        if($this->driver->id == 0) {
            // $DB->error = _('Driver absent');
            // return false;
        }
        // foreach($o->lines as $wol) {
        //     if($wol == $this) continue;
        //     if($this->workIntersect($wol)) {
        //         $DB->error = _('Work intersection');
        //         return false;
        //     }
        // }
        return true;
    }

    public function apply(WorkOrder $o, $pos, $data) {
        global $DB;
        // {id:0, del:1, driver:{id:0}, dt:{dt_begin:'', tm_begin:'', dt_end:'', tm_end:''}
        //   tech_op:{id:0}, field:{id:0}, crew:[pp_id1, pp_id2, ...] }
        $this->order_id = $o->id;
        $this->pos = $pos;
        foreach($data as $key => $val) {
            if($key == 'id') continue;
            if($key == 'field') $key = 'fld';
            if($key == 'dt') {
                $this->dt_begin = new DateTime($val->dt_begin . ' ' . $val->tm_begin);
                $val = $val->dt_end . ' ' . $val->tm_end;
                $key = 'dt_end';
            } elseif($key == 'crew') {
                $val = implode(',', $val);
            } elseif(is_object($val)) {
                $val = $val->id;
            }
            if(isset($this->$key)) {
                $this->$key = $this->getProperty($key, $val);
                //PageManager::$dbg[] = $key . " => " . json_encode($val);
            }
        }
        //PageManager::$dbg[] = "After apply:";
        foreach($this as $key => $val) {
            $v = json_encode($val);
            //PageManager::$dbg[] = "$key = $v";
        }
    }

    public function getSimple() {
        $ret = new stdClass();
        foreach($this as $key => $val) {
            if(is_a($val, 'DateTime')) $val = $val->format('Y-m-d H:i:s');
            if(is_object($val)) {
                if(method_exists($val, 'getSimple')) $val = $val->getSimple();
            }
            if($key == 'crew') {
                $arr = [];
                foreach($val as $pp) {
                    $arr[] = $pp->getSearchJson();
                }
                $val = $arr;
            }
            $ret->$key = $val;
        }
        return $ret;
    }

    public static function get($id, $order = null) {
        $ix = $order == null ? $id : "o$id";
        if(!isset(self::$cache[$ix])) {
            self::$cache[$ix] = new WorkOrderLine($id, $order);
        }
        return self::$cache[$ix];
    }

    public static function techopFilter($lst) {
        $ids = [];
        $fld = false;
        $nfld = false;
        foreach($lst as $id) {
            if($id >= 0) $ids[] = $id;
            if($id == TechOperation::TOP_FIELDWORK) $fld = true;
            if($id == TechOperation::TOP_NOT_FIELDWORK) $nfld = true;
        }

        if($fld) {
            $tops = TechOperation::getFieldworks(true, false, false);
            $ids = array_merge($ids, $tops);
        }
        if($nfld) {
            $tops = TechOperation::getFieldworks(true, false, true);
            $ids = array_merge($ids, $tops);
        }

        if(count($ids) == 0) return [-1];

        $flt = [
            'orders',
            'tech_op IN(' . implode(',', $ids) . ')'
        ];
        return self::getList($flt, 'order_id');
    }

    public static function getList($flt = [], $ord = 'dt_begin DESC', $lim = '') {
        global $DB;
        self::$total = 0;
        $oOrder = null;
        $obj = true;
        $int = false;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        $fld  = '';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = $fld = 'id';
                $obj  = false;
                $int  = true;
            } elseif($it == 'orders') {
                $flds = 'DISTINCT order_id';
                $fld  = 'order_id';
                $obj  = false;
                $int  = true;
            } elseif(is_array($it)) {
                $cond = array_shift($it);
                switch($cond) {
                    case 'width':
                        $wd = array_shift($it);
                        $lst = AggregationList::getWidthFilter($wd);
                        $or = [];
                        foreach($lst as $tct) {
                            $or[] = sprintf("(tech_op=%d AND tech_cond=%d AND trailer=%d)", $tct[0], $tct[1], $tct[2]);
                        }
                        if(count($or) > 0) {
                            $add[] = '(' . implode(' OR ', $or) . ')';
                        }
                        break;

                    case 'fields':
                        $flds = implode(',', $it);
                        $obj = false;
                        break;

                    case 'ord':
                        $oOrder = $it ? array_shift($it) : null;
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
        $DB->prepare("SELECT $calc $flds FROM gps_order_lines $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new WorkOrderLine($row, $oOrder) : ($int ? intval($row[$fld]) : $row);
        }
        return $ret;
    }
}
