<?php
class WorkTypeParent {
    public $id    = 0;
    public $name  = '';

    private static $cache = [];
    public static $total = 0;

    public function __construct($arg = 0) {
        global $DB;
        $fld = '';
        $val = $arg;


        if(is_numeric($arg)) {
            $fld = 'id';
            $val = intval($arg);
            if($val == 0) return;
        }
        if(is_string($arg) && $arg && !$fld) {
            $fld = 'name';
        }
        if($fld) {
            $q = $DB->prepare("SELECT * FROM work_type_parents WHERE $fld = :v")
                    ->bind('v', $val)->execute_row();
            if($q) {
                $arg = $q;
            }
        }
        if(is_array($arg) || is_object($arg)) {
            foreach($arg as $key => $val) {
                $this->$key = self::getProperty($key, $val);
            }
        }
    }

    private static function getProperty($key, $val) {
        switch($key) {
            case 'name': return $val;
        }
        return intval($val);
    }

    public static function init($name) {
        if(empty(trim($name))) return new WorkTypeParent();
        $ret = new WorkTypeParent($name);
        if($ret->id) return $ret;
        $ret->name = $name;
        $ret->save();
        return $ret;
    }

    public function save() {
        $t = new SqlTable('work_type_parents', $this);
        return $t->save($this);
    }

    public function getJson() { return $this; }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new WorkTypeParent($id);
        }
        return self::$cache[$id];
    }

    public static function getList($flt = [], $ord = 'name', $lim = '') {
        global $DB;
        self::$total = 0;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = 'id';
            }elseif(is_array($it)) {
                $cond = array_shift($it);
                if($cond) $add[] = $cond;
                $par[$it[0]] = $it[1];
            } else {
                $add[] = $it;
            }
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM work_type_parents $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        $total = count($rows);
        if($calc) {
            $total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $flds == '*' ? new WorkTypeParent($row) : ($flds == 'id' ? intval($row['id']) : $row);
        }
        self::$total = $total;
        return $ret;
    }
}