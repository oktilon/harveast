<?php
class WorkOrderType {
    public $id = 0;
    public $name = '';
    public $car_types = 0;

    private static $cache = [];
    public static $total = 0;

    public function __construct($arg = 0) {
        global $DB;
        if(is_numeric($arg)) {
            $id = intval($arg);
            if($id == 0) return;
            $arg = $DB->select_row("SELECT * FROM gps_order_types WHERE id = $id");
        }
        if(is_array($arg) || is_object($arg)) {
            foreach($arg as $key => $val) {
                if($key != 'name') $val = intval($val);
                $this->$key = $val;
            }
        }
    }

    public function save() {
        $t = new SqlTable('gps_order_types');
        foreach($this as $key => $val) {
            $t->addFld($key, $val);
        }
        return $t->save($this);
    }

    public function getSimple($webix = false) {
        $ret = new stdClass();
        $ret->id = $this->id;
        if($webix) $ret->value = $this->name;
        else $ret->name = $this->name;
        return $ret;
    }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new WorkOrderType($id);
        }
        return self::$cache[$id];
    }

    public static function getJsArray($webix = false) {
        $ret = [ ];
        $lst = self::getList([], 'name');
        foreach($lst as $st) {
            $ret[] = $st->getSimple($webix);
        }
        return json_encode($ret);
    }


    public static function getList($flt = [], $ord = 'name', $lim = '') {
        global $DB;
        self::$total = 0;
        $ret = [];
        $par = [];
        $add = [];
        foreach($flt as $it) {
            if(is_array($it)) {
                $add[] = $it[0];
                $par[$it[1]] = $it[2];
            } else {
                $add[] = $it;
            }
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc * FROM gps_order_types $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = new WorkOrderType($row);
        }
        return $ret;
    }
}
