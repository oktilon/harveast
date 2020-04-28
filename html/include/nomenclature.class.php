<?php
class Nomenclature {
    public $id     = 0;
    public $guid   = i1C::EMPTY_GUID;
    public $name   = '';

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
        if(is_string($arg)) {
            if(!$fld && preg_match(i1C::GUID_REGEX, $arg)) {
                if($arg == i1C::EMPTY_GUID) return;
                $fld = 'guid';
            }
        }
        if($fld) {
            $q = $DB->prepare("SELECT * FROM gps_nomens WHERE $fld = :v")
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
            case 'name':
            case 'guid': return $val;
        }
        return intval($val);
    }

    public static function init($obj) {
        $guid = '';
        $prefix = false;
        if(property_exists($obj, 'nomen_guid')) {
            $guid = $obj->nomen_guid;
            $prefix = true;
        } elseif(property_exists($obj, 'guid')) {
            $guid = $obj->guid;
        }
        if(!i1C::validGuid($guid)) $guid = i1C::EMPTY_GUID;
        $ret = new Nomenclature($guid);
        if($guid == i1C::EMPTY_GUID) return $ret;
        $ch = $ret->initFrom1C($obj, $prefix);
        $upd = count(get_object_vars($ch)) > 0;
        if($ret->id == 0) {
            $upd = true;
        }
        if($upd) {
            $ret->save();
            Changes::write('gps_nomens', $ret, $ch);
        }
        return $ret;
    }

    private function initFrom1C($obj, $prefix) {
        $ch = new stdClass();
        $m = [];
        foreach($obj as $key => $val) {
            if(preg_match('/^nomen_(\w+)$/', $key, $m)) {
                $key = $m[1];
            } else {
                if($prefix) continue;
            }
            if(property_exists($this, $key)) {
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
        $t = new SqlTable('gps_nomens', $this);
        $ret = $t->save($this);
    }

    public function getSimple() {
        $ret = new stdClass();
        $ret->id = $this->id;
        $ret->name = $this->name;
        return $ret;
    }

    public function getJson() { return $this->getSimple(); }

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
     * @return Nomenclature
     */
    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new Nomenclature($id);
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
                continue;
            }
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
        $DB->prepare("SELECT $calc $flds FROM gps_nomens $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        $total = count($rows);
        if($calc) {
            $total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $flds == '*' ? new Nomenclature($row) : ($flds == 'id' ? intval($row['id']) : $row);
        }
        self::$total = $total;
        return $ret;
    }
}