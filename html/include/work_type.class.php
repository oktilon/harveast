<?php
class WorkType {
    public $id         = 0;
    public $guid       = i1C::EMPTY_GUID;
    public $name       = '';
    public $unit       = null;
    public $parent     = null;
    public $flags      = 0;
    public $upd        = null;
    public $unit_ix    = 0;
    public $parent_ix  = 0;
    public $ix         = 0;

    public $cond = null;

    private static $cache = [];
    public static $total = 0;
    public static $trailers = false;
    public static $m_upd = false;

    const FLAG_TO_ACTIVE   = 0x01; // Актуально

    const FLAG_TO_FIELDWORK = 0x10; // Fieldwork

    const TOP_FIELDWORK     = -1;
    const TOP_NOT_FIELDWORK = -2;

    public static $NAME_FLD     = 'Fieldwork';
    public static $NAME_NOT_FLD = 'Not fieldwork';

    private static $smallPercentOperations = [
        'Посів%',
        '%комбайнування%',
        '%либоке рихле%',
        "Внесення%",
        "Культивац%"
    ];
    private static $smallPercentOperationsNarrow = [ // max_width
        'Дискув%' => 4000
    ];

    public static $flags_nm = [
        // ['f' => self::FLAG_TO_DELETED,   'i'=>'far fa-trash text-danger',    'c'=>'', 'n'=>'Deleted'],
        // ['f' => self::FLAG_TO_GROUP,     'i'=>'far fa-folder text-warning',  'c'=>'', 'n'=>'Folder'],
        // ['f' => self::FLAG_TO_MTP,       'i'=>'far fa-tractor text-primary', 'c'=>'', 'n'=>'MTP'],
        ['f' => self::FLAG_TO_ACTIVE,    'i'=>'far fa-tractor text-primary', 'c'=>'', 'n'=>'Actual'],
        ['f' => self::FLAG_TO_FIELDWORK, 'i'=>'far fa-wheat text-success',   'c'=>'', 'n'=>'Fieldwork'],
        // ['f' => self::FLAG_TO_PRESET,    'i'=>'far fa-wheat text-success',   'c'=>'', 'n'=>'Preset'],
    ];


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
            } else {
                $this->name = $arg;
            }
        }
        if($fld) {
            $q = $DB->prepare("SELECT * FROM techops WHERE $fld = :v")
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
            case 'name': return $val === 0 ? '' : $val;
            case 'parent': return TechOperationFolder::get($val);
            case 'unit': return MeasureUnit::get($val);
            case 'upd': return $val === 0 ? new DateTime() : new DateTime($val);
        }
        return intval($val);
    }

    private static function translatorFunction() {
        return [
            _('Fieldwork')
            ,_('Not fieldwork')
        ];
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
            Changes::write('techops', $ret, $ch);
        }
        return $ret;
    }

    private function initFrom1C($obj) {
        $ch = new stdClass();
        $flg     = 0;

        foreach($obj as $key => $val) {
            if(property_exists($this, $key) || $key == 'measure_unit') {
                if($key == 'measure_unit') $key = 'unit';
                $nv = self::getProperty($key, $val);
                if($this->$key != $nv) {
                    $this->$key = $nv;
                    $ch->$key = $nv;
                }
            } elseif($key == 'parent_name') {
                $nv = TechOperationFolder::init($val);
                if($this->parent->id != $nv->id) {
                    $this->parent = $nv;
                    $ch->parent = $nv->id;
                }
            } elseif($key == 'active') {
                if($val == 'true') $flg |= self::FLAG_TO_ACTIVE;
            }
        }
        if($this->flags != $flg) {
            $this->flags = $flg;
            $ch->flags = $flg;
        }
        return $ch;
    }

    public function save() {
        $t = new SqlTable('techops', $this, ['cond']);
        return $t->save($this);
    }

    public function getFlag($flag) { return ($this->flags & $flag) > 0; }
    public function setFlag($flag, $val) {
        if($val) {
            $this->flags |= $flag;
        } else {
            $this->flags &= ~$flag;
        }
    }

    public function isActive() { return $this->getFlag(self::FLAG_TO_ACTIVE); }
    public function isFieldOperation() { return true; /* $this->getFlag(self::FLAG_TO_FIELDWORK); */ }

    public function getJson() {
        $ret = new stdClass();
        $ret->id = $this->id;
        $ret->name = $this->name;
        $ret->u = $this->unit->id;
        return $ret;
    }

    public static function findByText($txt, $limit = 0, $implode = false) {
        $flt = [['name = :n', 'n', $txt]];
        if($txt == '+') {
            $flt = ['`unit` = 1'];
        }
        $ord = $implode ? 'id' : 'name';
        if($implode) $flt[] = 'id_only';
        $ret = self::getList($flt, $ord, $limit);
        if(!$ret) {
            array_shift($flt);
            $arr = explode(' ', $txt);
            foreach($arr as $i=>$t) $flt[] =  ["name LIKE :n$i", "n$i", "%$t%"];
            $ret = self::getList($flt, $ord, $limit);
        }
        //array_merge(PageManager::$dbg, $flt);
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

    public static function getFieldworks($id_only = false, $implode = false, $inverse = false) {
        $flt = [['flags & :f', 'f', self::FLAG_TO_FIELDWORK]];
        if($inverse) {
            $flt[0][0] .= ' = 0';
        }
        $ord = $id_only ? 'id' : 'name';
        if($id_only) $flt[] = 'id_only';
        $r = WorkType::getList($flt, $ord);
        if($id_only && $implode) $r = implode(',', $r);
        return $r;
    }

    public static function applyParents() {
        global $DB;
        $DB->prepare("UPDATE techops t
                        LEFT JOIN techops p ON p.guid = t.parent_guid
                        SET t.parent_id = p.id
                        WHERE   t.parent_id = 0
                            AND t.parent_guid != ''
                            AND t.parent_guid != :ng
                            AND p.id IS NOT NULL")
            ->bind('ng', i1C::EMPTY_GUID)
            ->execute();
        // if needed set types
    }

    // Harvest and Sowing
    public static function isSmallPercentOperation($techop_id, $techop_cond) {
        global $DB;
        $cond = [];
        $pars = [
            'i' => $techop_id
        ];
        foreach(self::$smallPercentOperations as $ix => $txt) {
            $pn = "t{$ix}";
            $cond[] = "`name` LIKE :$pn";
            $pars[$pn] = $txt;
        }
        $cond = implode(' OR ', $cond);
        $DB->prepare("SELECT COUNT(*) FROM techops
                        WHERE id = :i AND ($cond)");
        foreach($pars as $k => $v) $DB->bind($k, $v);
        $isSmall = intval($DB->execute_scalar()) > 0;
        if($isSmall) return true;

        // Use width
        $cond = [];
        $pars = [
            'i' => $techop_id,
            'c' => $techop_cond
        ];
        $ix = 0;
        foreach(self::$smallPercentOperationsNarrow as $txt => $wdMax) {
            $ix++;
            $pn = "t{$ix}";
            $wn = "w{$ix}";
            $cond[] = "(t.`name` LIKE :$pn AND sal.hd_width <= :$wn)";
            $pars[$pn] = $txt;
            $pars[$wn] = $wdMax;
        }
        $cond = implode(' OR ', $cond);
        $DB->prepare("SELECT COUNT(*) FROM techops t
                        LEFT JOIN spr_aggregation_list sal ON sal.techops = t.id
                        WHERE t.id = :i AND sal.techop_cond = :c AND ($cond)");
        foreach($pars as $k => $v) $DB->bind($k, $v);
        return intval($DB->execute_scalar()) > 0;
    }

    public static function getChessDayList($beg, $end) {
        $flt = ['chess', 'js'];
        if($beg) $flt[] = ['chess_beg', substr($beg, 0, 10)];
        if($end) $flt[] = ['chess_end', substr($end, 0, 10)];
        return self::getList($flt);
    }

    public static function rowParse($row) {
        if(isset($row['id'])) $row['id'] = intval($row['id']);
        if(isset($row['u'])) $row['u'] = intval($row['u']);
        return $row;
    }

    public static function checkFieldOperation($name) {
        $exc1 = preg_match('/^(Пере).*/', $name);
        $exc2 = preg_match('/^(Підвез).*/', $name);
        $exc3 = preg_match('/^(Завант).*/', $name);
        $exc4 = preg_match('/^(Заправ).*/', $name);
        $exc5 = preg_match('/^(Перекач).*/', $name);
        $exc6 = preg_match('/^(Пригот).*/', $name);
        return !($exc1 || $exc2 || $exc3 || $exc4 || $exc5 || $exc6);
    }


    public static function getTranslatedFilterName($en) {
        return  '< ' . _($en) . ' >';
    }

    public static function getFilterOperations($js = true) {
        $ret = [];
        if($js) {
            $ret[] = self::rowParse([
                'id'   => self::TOP_FIELDWORK,
                'name' => self::getTranslatedFilterName(self::$NAME_FLD),
                'u'    => 1
            ]);
            $ret[] = self::rowParse([
                'id'   => self::TOP_NOT_FIELDWORK,
                'name' => self::getTranslatedFilterName(self::$NAME_NOT_FLD),
                'u'    => 0
            ]);
        }
        return $ret;
    }

    public static function filterOperations($lst) {
        $ids = [];
        $fld = false;
        $nfld = false;
        foreach($lst as $id) {
            if($id == MAX_ID) {
                $ids[] = 0;
            } else {
                if($id >= 0) $ids[] = $id;
                if($id == WorkType::TOP_FIELDWORK) $fld = true;
                if($id == WorkType::TOP_NOT_FIELDWORK) $nfld = true;
            }
        }

        if($fld) {
            $tops = WorkType::getFieldworks(true, false, false);
            $ids = array_merge($ids, $tops);
        }
        if($nfld) {
            $tops = WorkType::getFieldworks(true, false, true);
            $ids = array_merge($ids, $tops);
        }

        if(count($ids) == 0) return [-1];
        return $ids;
    }

    public static function getList($flt = [], $ord = 'name', $lim = '') {
        global $DB;
        self::$total = 0;
        $empty = true;
        $obj = true;
        $all = false;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        $fld  = '';
        $ch_beg = '2000-01-01';
        $ch_end = '2999-01-01';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = $fld = 'id';
                $obj  = false;
            } elseif($it == 'js') {
                $flds = 'id, name, unit as u';
                $obj = false;
            } elseif($it == 'non_empty') {
                $empty = false;
            } elseif($it == 'chess') {
                $atop = [];
                $tops = $DB->prepare("SELECT DISTINCT `top`
                                        FROM gps_car_log
                                        WHERE dt BETWEEN :b AND :e
                                        ORDER BY `top`")
                            ->bind('b', $ch_beg)
                            ->bind('e', $ch_end)
                            ->execute_all();
                foreach ($tops as $row) {
                    $atop[] = intval($row['top']);
                }
                $add[] = 'id IN(' . implode(',', $atop) . ')';
            } elseif($it == 'all') {
                $all = true;
            } elseif(is_array($it)) {
                $cond = array_shift($it);
                if($cond == 'chess_beg') {
                    $ch_beg = array_shift($it);
                } elseif($cond == 'chess_end') {
                    $ch_end = array_shift($it);
                } else {
                    if($cond) $add[] = $cond;
                    $par[$it[0]] = $it[1];
                }
            } else {
                $add[] = $it;
            }
        }
        if(!$all) {
            $add[] = "flags & :fd";
            $par['fd'] = self::FLAG_TO_ACTIVE;
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM techops $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        $sql = $DB->sql;
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new WorkType($row) : ($fld ? intval($row[$fld]) : self::rowParse($row));
        }
        if(!$ret && $fld && !$empty) $ret[] = -1;
        $DB->sql = $sql;
        return $ret;
    }
}