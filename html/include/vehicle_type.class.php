<?php
class VehicleType {
    public $id     = 0;
    public $guid   = i1C::EMPTY_GUID;
    public $name   = '';

    private static $cache = [];
    public static $total = 0;
    public static $m_upd = false;

    public function __construct($arg = 0) {
        global $DB;
        $fld = '';
        $val = $arg;

        if(is_numeric($arg)) {
            $fld = 'id';
            $val = intval($arg);
            if($val == 0) return;
        }
        if(is_string($arg)) {
            if(!$fld && preg_match(i1C::GUID_REGEX, $arg)) {
                if($arg == i1C::EMPTY_GUID) return;
                $fld = 'guid';
            }
        }
        if($fld) {
            $q = $DB->prepare("SELECT * FROM vehicle_types WHERE $fld = :v")
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
            case 'id': return intval($val);
        }
        return $val;
    }

    public static function init($obj) {
        $guid = is_object($obj) && property_exists($obj, 'vechile_type_guid') ? $obj->vechile_type_guid :
                is_array($obj) && key_exists('vechile_type_guid', $obj) ? $obj['vechile_type_guid'] : '';
        $ret = new VehicleType($guid);
        if(!i1C::validGuid($guid)) return $ret;
        $ch = $ret->initFrom1C($obj);
        $upd = count(get_object_vars($ch)) > 0;
        if($ret->id == 0) {
            $upd = true;
        }
        if($upd) {
            self::$m_upd = true;
            $ret->save();
            Changes::write('vehicle_types', $ret, $ch);
        }
        return $ret;
    }

    private function initFrom1C($obj) {
        $ch = new stdClass();
        $m = [];
        foreach($obj as $k => $val) {
            if(preg_match('/^vechile_type_(\w+)$/', $k, $m) && property_exists($this, $m[1])) {
                $key = $m[1];
                $nv = self::getProperty($key, $val);
                if($this->$key != $nv) {
                    $this->$key = $nv;
                    $ch->$key = $nv;
                }
            }
        }
        return $ch;
    }

    public function save() {
        $t = new SqlTable('vehicle_types', $this);
        $ret = $t->save($this);
    }

    public function getJson() {
        $ret = new stdClass();
        $ret->id = $this->id;
        $ret->name = $this->name;
        return $ret;
    }

    public static function findByText($txt, $limit = '', $implode = false) {
        $flt = [
            ['name LIKE :n', 'n', "%$txt%"]
        ];
        $ord = $implode ? 'id' : 'name';
        if($implode) $flt[] = 'id_only';

        $ret = self::getList($flt, $ord, $limit);
        if($implode) {
            $ret = implode(',', $ret);
        }
        return $ret;
    }

    /**
     * Get cached Tech Operation Unit
     *
     * @param int $id unit Id
     *
     * @return VehicleType
     */
    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new VehicleType($id);
        }
        return self::$cache[$id];
    }

    public static function getList($flt = [], $ord = 'name', $lim = '') {
        global $DB;
        self::$total = 0;
        $fld = '';
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $fld = $flds = 'id';
                continue;
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
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM vehicle_types $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        $total = count($rows);
        if($calc) {
            $total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $flds == '*' ? new VehicleType($row) : ($fld ? intval($row[$fld]) : $row);
        }
        self::$total = $total;
        return $ret;
    }
}