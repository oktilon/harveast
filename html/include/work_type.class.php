<?php
class WorkType {
    public $id              = 0;
    public $guid            = i1C::EMPTY_GUID;
    public $name            = '';
    public $measure_unit    = null;
    public $parent          = null;
    public $processing_type = null;
    public $work_group      = null;
    public $techop         = null;
    public $active          = 0;
    public $upd             = null;

    private static $cache = [];
    public static $total = 0;
    public static $m_upd = false;

    public function __construct($arg = 0) {
        global $DB;
        foreach($this as $key => $val) {
            if($val === null) $this->$key = self::getProperty($key);
        }

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
            $q = $DB->prepare("SELECT * FROM work_types WHERE $fld = :v")
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
            case 'guid':
            case 'name': return $val;
            case 'parent': return WorkTypeParent::get($val);
            case 'processing_type': return ProcessingType::get($val);
            case 'work_group': return WorkGroup::get($val);
            case 'techop': return TechOperation::get($val);
            case 'measure_unit': return MeasureUnit::get($val);
            case 'upd': return new DateTime($val === 0 ? '2000-01-01' : $val);
            case 'active':
                $v = $val;
                if($val == 'true' || $val === true) $v = 1;
                if($val == 'false' || $val === false) $v = 0;
                return intval($v);
        }
        return intval($val);
    }

    public static function init($obj) {
        self::$m_upd = false;
        $guid = i1C::EMPTY_GUID;
        if(property_exists($obj, 'guid')) $guid = $obj->guid;
        $ret = new WorkType($guid);
        if(!i1C::validGuid($guid)) return $ret;
        $ch = $ret->initFrom1C($obj);
        $upd = count(get_object_vars($ch)) > 0;
        if($ret->id == 0) {
            $upd = true;
        }
        if($upd) {
            self::$m_upd = true;
            $ret->save();
            Changes::write('work_types', $ret, $ch);
        }
        return $ret;
    }

    private function initFrom1C($obj) {
        $ch = new stdClass();
        $init = [
            'work_group'      => ['WorkGroup', ''],
            'processing_type' => ['ProcessingType', ''],
            'measure_unit'    => ['MeasureUnit', ''],
            'parent_name'     => ['WorkTypeParent', 'parent'],
        ];

        foreach($obj as $key => $val) {
            if(key_exists($key, $init)) {
                list($cls, $fld) = $init[$key];
                $f = $fld ? $fld : $key;
                $it = $cls::init($val);
                if($this->$f->id != $it->id) {
                    $this->$f = $it;
                    $ch->$f = $it->id;
                }
            } elseif(property_exists($this, $key)) {
                $nv = self::getProperty($key, $val);
                if(is_object($nv)) {
                    if($this->$key->id != $nv->id) {
                        $this->$key = $nv;
                        $ch->$key = $nv->id;
                    }
                } else {
                    if($this->$key != $nv) {
                        $this->$key = $nv;
                        $ch->$key = $nv;
                    }
                }
            }
        }
        return $ch;
    }

    public function save() {
        $t = new SqlTable('work_types', $this, ['upd']);
        return $t->save($this);
    }

    public function getJson() {
        $ret = new stdClass();
        $ret->id = $this->id;
        $ret->name = $this->name;
        return $ret;
    }

    public static function findByText($txt, $limit = 0, $implode = false) {
        $flt = [['name LIKE :n', 'n', "%$txt%"]];
        $ord = $implode ? 'id' : 'name';
        if($implode) $flt[] = 'id_only';
        $ret = self::getList($flt, $ord, $limit);
        if($implode) {
            if(!$ret) $ret = [-1];
            $ret = implode(',', $ret);
        }
        return $ret;
    }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new WorkType($id);
        }
        return self::$cache[$id];
    }

    public static function getList($flt = [], $ord = 'name', $lim = '') {
        global $DB;
        self::$total = 0;
        $json = false;
        $all = false;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        $fld  = '';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = $fld = 'id';
            } elseif($it == 'json') {
                $json = true;
            } elseif($it == 'all') {
                $all = true;
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
        if(!$all) {
            // $add[] = "active = 1";
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM work_types $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $it = $flds == '*' ? new WorkType($row) : ($fld ? intval($row[$fld]) : $row);
            $ret[] = $json ? $it->getJson() : $it;
        }
        return $ret;
    }
}