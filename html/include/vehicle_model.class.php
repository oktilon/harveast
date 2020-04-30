<?php
class VehicleModel {
    public $id = 0;
    public $guid = '';
    public $name = '';
    public $parent = null;
    public $nomen = null;
    public $flags = 0;
    public $car_type = '';
    public $vehicle_type = null;
    public $upd = null;

    const FLAG_VM_DELETED      = 0x0001;
    const FLAG_VM_ACTIVE       = 0x0002;
    const FLAG_VM_SPECIAL      = 0x0004;

    private static $cache = [];
    public static $total = 0;
    public static $m_upd = false;

    public function __construct($arg = 0) {
        global $DB;
        $this->nomen = self::getProperty('nomen', 0);
        $this->vehicle_type = self::getProperty('vehicle_type', 0);
        $this->parent = self::getProperty('parent', 0);
        $this->upd = self::getProperty('upd', '2000-01-01');
        $fld = '';
        $val = $arg;
        if(is_numeric($arg)) {
            $fld = 'id';
            $val = intval($arg);
            if($val == 0) return;
        }
        if(is_string($arg) && preg_match(i1C::GUID_REGEX, $arg)) {
            if($arg == i1C::EMPTY_GUID) return;
            $fld = 'guid';
        }
        if($fld) {
            $q = $DB->prepare("SELECT * FROM vehicle_models WHERE $fld = :v")
                    ->bind('v', $val)->execute_row();
            if($q) {
                $arg = $q;
            }
        }
        if(is_array($arg) || is_object($arg)) {
            foreach($arg as $k => $v) $this->$k = self::getProperty($k, $v);
        }
    }

    private static function getProperty($key, $val) {
        switch($key) {
            case 'id':
            case 'flags': return intval($val);
            case 'upd': return new DateTime($val);
            case 'nomen': return Nomenclature::get($val);
            case 'parent': return VehicleModelParent::get($val);
            case 'vehicle_type': return VehicleType::get($val);
        }
        return $val;
    }

    public static function init($obj) {
        self::$m_upd = false;
        $guid = i1C::EMPTY_GUID;
        if(property_exists($obj, 'guid')) $guid = $obj->guid;
        $ret = new VehicleModel($guid);
        if(!i1C::validGuid($guid)) return $ret;
        $ch = $ret->initFrom1C($obj);
        $upd = count(get_object_vars($ch)) > 0;
        if($ret->id == 0) {
            $upd = true;
        }
        if($upd) {
            self::$m_upd = true;
            $ret->save();
            Changes::write('vehicle_models', $ret, $ch);
        }
        return $ret;
    }

    private function initFrom1C($obj) {
        $ch = new stdClass();
        $flg     = 0;

        $flags = [
            'active' => self::FLAG_VM_ACTIVE,
            'is_special' => self::FLAG_VM_SPECIAL,
        ];

        $extra = [
            'nomen_guid' => '',
            'nomen_name' => '',
            'vechile_type_guid' => '',
            'vechile_type_name' => '',
        ];

        foreach($obj as $key => $val) {
            if(property_exists($this, $key)) {
                $nv = self::getProperty($key, $val);
                if($this->$key != $nv) {
                    $this->$key = $nv;
                    $ch->$key = $nv;
                }
            } elseif(key_exists($key, $flags)) {
                if($val === 'true') $val = true;
                if($val === 'false') $val = false;
                if($val) $flg |= $flags[$key];
            } elseif(key_exists($key, $extra)) {
                $extra[$key] = $val;
            } elseif($key == 'parent_name') {
                $par = VehicleModelParent::init($val);
                if($this->parent->id != $par->id) {
                    $this->parent = $par;
                    $ch->parent = $par->id;
                }
            }
        }
        $nom = Nomenclature::init($extra);
        if($this->nomen->id != $nom->id) {
            $this->nomen = $nom;
            $ch->nomen = $nom->id;
        }

        $vt = VehicleType::init($extra);
        if($this->vehicle_type->id != $vt->id) {
            $this->vehicle_type = $vt;
            $ch->vehicle_type = $vt->id;
        }

        if($this->flags != $flg) {
            $this->flags = $flg;
            $ch->flags = $flg;
        }
        return $ch;
    }

    public function save() {
        $t = new SqlTable('vehicle_models', $this, ['upd']);
        return $t->save($this);
    }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new VehicleModel($id);
        }
        return self::$cache[$id];
    }

    public function getSimple() { return $this->getJson(); }

    public static function getJson($simple = true) {
        $ret = new stdClass();
        $arr = ['id', 'name'];
        foreach($this as $k => $v) {
            $val = is_object($v) ? (method_exists($v, 'getJson') ? $v->getJson() : property_exists($v, 'id') ? $v->id : 'obj') : $v;
            $ret->$k = $val;
        }
        return $ret;
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

    public static function getLastVersion() {
        global $DB;
        $r = $DB->prepare("SELECT UNIX_TIMESTAMP(MAX(upd)) FROM vehicle_models")
                ->execute_scalar();
        return intval($r);
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
        $DB->prepare("SELECT $calc $flds FROM vehicle_models $add $order $limit");
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
            $ret[] = $flds == '*' ? new VehicleModel($row) : ($fld ? intval($row[$fld]) : $row);
        }
        return $ret;
    }
}