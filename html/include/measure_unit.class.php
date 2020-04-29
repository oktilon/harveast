<?php
class MeasureUnit {
    public $id     = 0;
    public $guid   = i1C::EMPTY_GUID;
    public $name   = '';
    public $active = 0;

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
            $q = $DB->prepare("SELECT * FROM measure_units WHERE $fld = :v")
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
            case 'active':
                if($val == 'true') $val = '1';
                if($val == 'false') $val = '0';
                return intval($val);
        }
        return intval($val);
    }

    public static function init($obj) {
        self::$m_upd = false;
        $guid = i1C::EMPTY_GUID;
        if(property_exists($obj, 'guid')) $guid = $obj->guid;
        $ret = new MeasureUnit($guid);
        if(!i1C::validGuid($guid)) return $ret;
        $ch = $ret->initFrom1C($obj);
        $upd = count(get_object_vars($ch)) > 0;
        if($ret->id == 0) {
            $upd = true;
        }
        if($upd) {
            self::$m_upd = true;
            $ret->save();
            Changes::write('measure_units', $ret, $ch);
        }
        return $ret;
    }

    private function initFrom1C($obj) {
        $ch = new stdClass();

        foreach($obj as $key => $val) {
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
        $t = new SqlTable('measure_units', $this);
        $ret = $t->save($this);
    }

    public function getSimple($webix = false) {
        $arr = ['id', 'name'];
        $ret = new stdClass();
        $ret->id = $this->id;
        $fld = $webix ? 'value' : 'name';
        $ret->$fld = $this->name;
        return $ret;
    }

    public static function getJsArray($empty = '') {
        $empty = new MeasureUnit($empty);
        $ret = [ $empty->getSimple() ];
        $lst = self::getList([], 'id');
        foreach($lst as $st) {
            $ret[] = $st->getSimple();
        }
        return json_encode($ret);
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
     * @return MeasureUnit
     */
    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new MeasureUnit($id);
        }
        return self::$cache[$id];
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
            switch($it) {
                case 'id_only':
                    $flds = 'id';
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
        $DB->prepare("SELECT $calc $flds FROM measure_units $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        $total = count($rows);
        if($calc) {
            $total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new MeasureUnit($row) : ($flds == 'id' ? intval($row['id']) : $row);
        }
        self::$total = $total;
        return $ret;
    }
}