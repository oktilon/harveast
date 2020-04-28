<?php
class EquipmentModel {
    public $id = 0;
    public $guid = '';
    public $name = '';
    public $name_eng = '';
    public $parent = null;
    public $nomen = null;
    public $wd = 0;
    public $active = 0;


    private static $cache = [];
    public static $total = 0;
    public static $m_upd = false;

    public function __construct($arg = 0) {
        global $DB;
        $this->parent = self::getProperty('parent');
        $this->nomen  = self::getProperty('nomen');
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
                $val = $arg;
            }
        }
        if($fld) {
            $q = $DB->prepare("SELECT * FROM equipment_models WHERE $fld = :v")
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

    private static function getProperty($key, $val = 0) {
        switch($key) {
            case 'id':
            case 'wd':
            case 'active': return intval($val);
            case 'parent': return EquipmentParent::get($val);
            case 'nomen': return Nomenclature::get($val);
        }
        return $val;
    }

    public static function init($obj) {
        self::$m_upd = false;
        $guid = i1C::EMPTY_GUID;
        if(property_exists($obj, 'guid')) $guid = $obj->guid;
        $ret = new EquipmentModel($guid);
        if($guid == i1C::EMPTY_GUID) return $ret;
        $ch = $ret->initFrom1C($obj);
        $upd = count(get_object_vars($ch)) > 0;
        if($ret->id == 0) {
            $upd = true;
        }
        if($upd) {
            self::$m_upd = true;
            $ret->save();
            Changes::write('equipment_models', $ret, $ch);
        }
        return $ret;
    }

    private function initFrom1C($obj) {
        $ch = new stdClass();

        foreach($obj as $key => $val) {
            if(property_exists($this, $key)) {
                if($key == 'wd') $val = 1000 * $val;
                $nv = self::getProperty($key, $val);
                if($this->$key != $nv) {
                    $this->$key = $nv;
                    $ch->$key = $nv;
                }
            } elseif($key == 'nomen_guid') {
                $nv = Nomenclature::init($obj);
                if($this->nomen->id != $nv->id) {
                    $this->nomen = $nv;
                    $ch->nomen = $nv->id;
                }
            } elseif($key == 'parent_name') {
                $nv = EquipmentParent::init($val);
                if($this->parent->id != $nv->id) {
                    $this->parent = $nv;
                    $ch->parent = $nv->id;
                }
            } elseif($key == 'active') {
                $nv = $val == 'true' ? 1 : 0;
                if($this->active != $nv) {
                    $this->active = $nv;
                    $ch->active = $nv;
                }
            }
        }
        return $ch;
    }

    public function save() {
        $t = new SqlTable('equipment_models', $this);
        return $t->save($this);
    }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new EquipmentModel($id);
        }
        return self::$cache[$id];
    }

    public function getSimple() {
        $arr = ['id', 'name', 'wd'];
        $ret = new stdClass();
        foreach($arr as $key) {
            $val = $this->$key;
            $ret->$key = $val;
        }
        return $ret;
    }

    public static function getJson() {
        return $this->getSimple();
    }

    public static function findByText($txt, $limit = 0, $implode = false) {
        $flt = [
            ['name LIKE :n', 'n', "%$txt%"]
        ];
        $ord = $implode ? 'id' : 'name';
        if($implode) $flt[] = 'id_only';
        //array_merge(PageManager::$dbg, $flt);
        $ret = self::getList($flt, $ord, $limit);
        if($implode) {
            $ret = implode(',', $ret);
        }
        return $ret;
    }

    public static function getList($flt = [], $ord = 'name', $lim = '') {
        global $DB;
        self::$total = 0;
        $obj = true;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = 'id';
                $obj  = false;
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
        $DB->prepare("SELECT $calc $flds FROM equipment_models $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        //PageManager::$dbg[] = $DB->sql;
        $rows = $DB->execute_all();
        //PageManager::$dbg[] = $DB->error;
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new EquipmentModel($row) : intval($row['id']);
        }
        return $ret;
    }
}