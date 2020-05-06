<?php
class FixedAsset {
    public $id = 0;
    public $guid = '';
    public $name = '';
    public $parent = 0;
    public $flags = 0;
    public $factory_num = '';
    public $licplate = '';
    public $vin = '';
    public $chassis = '';
    public $prop_num = '';
    public $capacity = 0;
    public $inv_org = '';
    public $inv = '';
    public $category = null;
    public $org = '';
    /** @var VehicleModel */
    public $model_vehicle = null;
    /** @var EquipmentModel */
    public $model_equip = null;
    public $upd = null;
    public $ix = 0;

    const FLAG_FA_DELETED      = 0x0001;
    const FLAG_FA_ACTIVE       = 0x0002;
    const FLAG_FA_VEHICLE      = 0x0004;
    const FLAG_FA_FOLDER        = 0x0008;

    private static $cache = [];
    public static $total = 0;
    public static $m_upd = false;

    public function __construct($arg = 0) {
        global $DB;
        $this->category = self::getProperty('category', 0);
        $this->upd = self::getProperty('upd', '2000-01-01');
        $this->model_vehicle = self::getProperty('model_vehicle', 0);
        $this->model_equip = self::getProperty('model_equip', 0);
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
            $q = $DB->prepare("SELECT * FROM spr_fixed_assets WHERE $fld = :v")
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
            case 'ix':
            case 'flags':
            case 'parent':
            case 'capacity': return intval($val);
            case 'upd': return new DateTime($val);
            case 'category': return FixedAssetCategory::get($val);
            case 'model_equip': return EquipmentModel::get($val);
            case 'model_vehicle': return VehicleModel::get($val);
        }
        return $val;
    }

    public static function init($obj) {
        self::$m_upd = false;
        $guid = i1C::EMPTY_GUID;
        if(property_exists($obj, 'guid')) $guid = $obj->guid;
        $ret = new FixedAsset($guid);
        if(!i1C::validGuid($guid)) return $ret;
        $ch = $ret->initFrom1C($obj);
        $upd = count(get_object_vars($ch)) > 0;
        if($ret->id == 0) {
            $upd = true;
        }
        if($upd) {
            self::$m_upd = true;
            $ret->save();
            Changes::write('spr_fixed_assets', $ret, $ch);
        }
        return $ret;
    }

    private function initParent($extra) {
        $par = FixedAsset::get(0);
        if(i1C::validGuid($extra['parent_guid'])) {
            $par = FixedAsset::get($extra['parent_guid']);
            if($par->id == 0) {
                $par->guid = $extra['parent_guid'];
                $par->name = $extra['parent_name'];
                $ok = $par->save();
                if($ok) {
                    $ch = (object)[
                        'guid' => $par->guid,
                        'name' => $par->name,
                        'parent' => 0
                    ];
                    Changes::write('spr_fixed_assets', $par, $ch);
                }
            }
        }
        return $par;
    }

    private function initFrom1C($obj) {
        $ch = new stdClass();
        $flg     = 0;

        $flags = [
            'active' => self::FLAG_FA_ACTIVE,
            'is_folder' => self::FLAG_FA_FOLDER,
            'is_vehicle' => self::FLAG_FA_VEHICLE,
        ];

        $extra = [
            'parent_name' => '',
            'parent_guid' => '',
            'category_guid' => '',
            'category_name' => '',
        ];

        foreach($obj as $key => $val) {
            if(property_exists($this, $key)) {
                $nv = self::getProperty($key, trim($val));
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
            } elseif($key == 'model' && i1C::validGuid($val)) {
                $eq = EquipmentModel::get($val);
                if($this->model_equip->id != $eq->id) {
                    $this->model_equip = $eq;
                    $ch->model_equip = $eq->id;
                }
                $veh = VehicleModel::get($val);
                if($this->model_vehicle->id != $veh->id) {
                    $this->model_vehicle = $veh;
                    $ch->model_vehicle = $veh->id;
                }
            }
        }
        $par = self::initParent($extra);
        if($this->parent != $par->id) {
            $this->parent = $par->id;
            $ch->parent = $par->id;
        }

        $cat = FixedAssetCategory::init($extra);
        if($this->category->id != $cat->id) {
            $this->category = $cat;
            $ch->category = $cat->id;
        }

        if($this->flags != $flg) {
            $this->flags = $flg;
            $ch->flags = $flg;
        }
        return $ch;
    }

    public function save() {
        $t = new SqlTable('spr_fixed_assets', $this, ['upd']);
        return $t->save($this);
    }

    public function getSimple() { return $this->getJson(); }

    public function getJson($simple = true) {
        $ret = new stdClass();
        $arr = ['id', 'name'];
        foreach($this as $k => $v) {
            if($simple && !in_array($k, $arr)) continue;
            $val = is_object($v) ? (method_exists($v, 'getJson') ? $v->getJson() : property_exists($v, 'id') ? $v->id : 'obj') : $v;
            $ret->$k = $val;
        }
        return $ret;
    }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new FixedAsset($id);
        }
        return self::$cache[$id];
    }

    public static function searchEquipment($txt, $id_only = true, $implode = false) {
        $flt = [
            ['flags & :f = 0', 'f', self::FLAG_FA_FOLDER],
            'model_equip > 0'
        ];
        if($txt) {
            $a_txt = explode(' ', $txt);
            foreach($a_txt as $ix => $txp) {
                $flt[] = ["name LIKE :n{$ix}", "n{$ix}", "%{$txp}%"];
            }
        }
        $ord = $id_only ? 'id' : 'name';
        if($id_only) $flt[] = 'id_only';
        $r = self::getList($flt, $ord);
        if($id_only && $implode) $r = implode(',', $r);
        return $r;
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
        $r = $DB->prepare("SELECT UNIX_TIMESTAMP(MAX(upd)) FROM spr_fixed_assets")
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
        $DB->prepare("SELECT $calc $flds FROM spr_fixed_assets $add $order $limit");
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
            $ret[] = $flds == '*' ? new FixedAsset($row) : ($fld ? intval($row[$fld]) : $row);
        }
        return $ret;
    }
}