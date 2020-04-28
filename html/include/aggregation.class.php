<?php
class Aggregation {
    public $id = 0;
    public $upd = null;
    public $car_mdl = null;
    public $trailer_mdl = null;
    public $trailer2_mdl = null;

    private static $cache = [];
    public  static $total = 0;

    public function __construct($arg = 0) {
        global $DB, $PM;
        foreach($this as $k => $v) if($v === null) $this->$k = self::getProperty($k, 0);

        if(is_numeric($arg)) {
            $id = intval($arg);
            if($id == 0) return;
            $arg = $DB->select_row("SELECT * FROM spr_aggregations WHERE id = $id");
        }
        if(is_array($arg) || is_object($arg)) {
            foreach($arg as $key => $val) {
                $this->$key = self::getProperty($key, $val);
            }
        }
    }

    private static function getProperty($key, $val) {
        switch($key) {
            case 'upd': return new DateTime($val === 0 ? '1970-01-01' :$val);
            case 'car_mdl': return CarModel::get($val);
            case 'trailer_mdl':
            case 'trailer2_mdl': return TrailerModel::get($val);
            default: return intval($val);
        }
        return false;
    }

    public static function init(CarModel $cm, TrailerModel $tm1, TrailerModel $tm2) {
        global $DB;
        $row = $DB->prepare("SELECT * FROM spr_aggregations
                            WHERE car_mdl = :cm
                                AND trailer_mdl  = :tm1
                                AND trailer2_mdl = :tm2")
                ->bind('cm',  $cm->id)
                ->bind('tm1', $tm1->id)
                ->bind('tm2', $tm2->id)
                ->execute_row();
        if($row) return new Aggregation($row);
        $ret = new Aggregation();
        $ret->car_mdl = $cm;
        $ret->trailer_mdl = $tm1;
        $ret->trailer2_mdl = $tm2;
        $ret->save();
        return $ret;
    }

    public function save() {
        $t = new SqlTable('spr_aggregations', $this, ['upd']);
        return $t->save($this);
    }

    public function getSimple($webix = false) {
        $ret = new stdClass();
        $arr = ['id', 'car_mdl', 'trailer_mdl'];
        foreach($arr as $key) {
            $val = $this->$key;
            if(is_object($val)){
                if(method_exists($val, 'getSimple')) $val = $val->getSimple();
            }
            $ret->$key = $val;
        }
        return $ret;
    }

    public function getJson() {
        $ret = $this->getSimple(false);
        $tmp = new CarSubType(0); // kostyl
        $ret->car_sub = $tmp->getSimple();
        return $ret;
    }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new Aggregation($id);
        }
        return self::$cache[$id];
    }

    public static function getWebixArray($flt = [], $ord = 'id DESC', $lim = '') {
        $ret = [ ];
        $lst = self::getList($flt, $ord, $lim);
        foreach($lst as $it) {
            $ret[$it->id] = $it->getJson();
        }
        return $ret;
    }

    public static function getList($flt = [], $ord = 'car_mdl', $lim = '') {
        global $DB;
        self::$total = 0;
        $obj = true;
        $emp = false;
        $fld = false;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        foreach($flt as $it) {
            switch($it) {
                case 'id_only':
                    $flds = 'id';
                    $fld  = true;
                    $obj  = false;
                    break;
                case 'non_empty':
                    $emp  = true;
                    break;
                case 'cars':
                    $flds = 'car_mdl';
                    $fld  = true;
                    $obj  = false;
                    break;
                case 'trailers':
                    $flds = 'trailer_mdl';
                    $fld  = true;
                    $obj  = false;
                    break;
                default:
                    if(is_array($it)) {
                        $cond = array_shift($it);
                        switch($cond) {
                            case 'fields':
                                $flds = implode(',', $it);
                                break;
                            default:
                                $add[] = $cond;
                                $par[$it[0]] = $it[1];
                        }
                    } else {
                        $add[] = $it;
                    }
            }
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM spr_aggregations $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        $total = count($rows);
        if($calc) {
            $total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new Aggregation($row) : ($fld ? intval($row[$flds]) : $row);
        }
        self::$total = $total;
        if($emp && $total == 0 && $fld) $ret[] = -1;
        return $ret;
    }
}
