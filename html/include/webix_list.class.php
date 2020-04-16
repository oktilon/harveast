<?php
class WebixList {
    public $data = [];
    public $pos  = 0;

    private $cnt = 50;

    private static $cache = [];
    private $filter_raw = null;
    private $filter_obj = null;
    public static $filter_oper_cache = [];

    public function __construct($pos = -1) {
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $this->cnt = isset($_POST['count']) ? intval($_POST['count']) : 50;
        $this->pos = $pos >= 0 ? $pos : $start;
        if($this->pos < 0) $this->pos = 0;
        $this->filter_raw = new stdClass();

        $f = isset($_POST['filter']) ? $_POST['filter'] : '{}';
        $arr = json_decode($f);
        $this->filter_obj = is_object($arr) ? $arr : new stdClass();
    }

    public function forceLimits($cnt, $pos = 0) {
        $this->cnt = $cnt;
        $this->pos = $pos;
    }

    public function limits() {
        return $this->pos ? "{$this->pos},{$this->cnt}" : "{$this->cnt}";
    }

    public function authTest(CUser $u) {
        if($u->id > 0) return true;
        $this->status = 'auth';
        return false;
    }

    public function setData($lst, $total = 0, $args = []) {
        foreach($lst as $row) {
            if(is_object($row)) {
                if(method_exists($row, 'getJson')) $row = call_user_func_array([$row, 'getJson'], $args);
                elseif(method_exists($row, 'getSimple')) $row = call_user_func_array([$row, 'getSimple'], $args);
            }
            $this->data[] = $row;
        }
        if($total > 0 && $this->pos == 0) {
            $this->total_count = $total;
        }
    }

    public function parseOrder($class, $def = 'id DESC', $ord = '', $alias = []) {
        $ret = [];
        $o = $ord ? $ord : (isset($_POST['ord']) ? $_POST['ord'] : '[]');
        $arr = json_decode($o);
        if(!is_array($arr)) {
            if(is_object($arr)) {
                $arr = [$arr];
            } else {
                return $def;
            }
        }
        $example = self::getClassExample($class);
        $prefix = '';
        foreach($arr as $o) {
            $prop = property_exists($o, 'by') ? $o->by : (property_exists($o, 'id') ? $o->id : '');
            if($alias && array_key_exists($prop, $alias)) {
                $pp = $alias[$prop];
                if(is_array($pp)) {
                    $prefix = array_shift($pp);
                    $pp = array_shift($pp);
                }
                $prop = $pp;
            }
            $multy = explode('.', $prop);
            $obj = count($multy) > 1 && $multy[1] != 'id';
            $by = $obj ? $multy[0] : $prop;
            $tst = str_replace('`', '', $by);
            if(property_exists($class, $tst)) {
                if($obj && is_object($example->$by)) {
                    if(property_exists($example, "{$by}_ix")) {
                        $by .= '_ix';
                    } else {
                        $by = '';
                    }
                }
                if($by) $ret[] = $prefix . $by . ' ' . $o->dir;
            }
        }
        if(empty($ret)) $ret[] = $def;
        // $this->ord = $ret;
        return implode(',', $ret);
    }

    public function getRawFilter($one = false) {
        if($one === false) return $this->filter_raw;
        if(isset($this->filter_raw[$one])) return $this->filter_raw[$one];
        return false;
    }

    public function getFilter($one = false) {
        if($one === false) return $this->filter_obj;
        if(property_exists($this->filter_obj, $one)) return $this->filter_obj->$one;
        return false;
    }

    public static function replaceValInArray($arr, $val, $sec) {
        $ret = [];
        if(!is_array($arr)) return $arr;
        $tx_val = $val;
        if(is_array($val)) $tx_val = implode(',', $val);
        foreach($arr as $v) {
            if(is_array($v)) {
                $v = self::replaceValInArray($v, $val, $sec);
            } elseif(is_string($v)) {
                if($v == 'vv') {
                    $v = $tx_val;
                } elseif($v == 'ss') {
                    $v = $sec;
                } elseif($v == 'ar') {
                    $v = $val;
                } else {
                    $v = str_replace('{v}', $tx_val, $v);
                    $v = str_replace('{s}', $sec, $v);
                }
            }
            $ret[] = $v;
        }
        return $ret;
    }

    /**
     * $flts = [ FLT1, FLT2, ... ]
     *  FLT1 = [ 'FLT_NAME', 'PRE_FUNC', 'OPER', 'FIELD', [ FUN1, FUN2, ... ] ]
     *  FLT_NAME - name from JS
     *  PRE_FUNC - eval filter value, could be:
     *    - exp-int = explode + intval => array(int)
     *    - exp-or  = explode + intval + bin_or => int
     *    - date    = gets flt=flt.start, sec=flt.end
     *    - car     = gets flt=CCar::getList(ts_number:flt, ts_name:flts.OPER), sets OPER = IN
     *    - function(VAL, OPER, FIELD) returns: - VAL
     *                                          - [VAL OPER]
     *                                          - [VAL OPER FIELD]
     *                                          - [VAL OPER FIELD SKIP_EMPTY?]
     *
     *  OPER - DB operator, could be:
     *    - LIKE adds ["FIELD LIKE :p", p, "%{filter_val}%"]
     *    - IN   adds "FIELD IN(filter_val)"
     *    - =    adds ["FIELD = :p", p, "filter_val"]
     *    - DATE adds ["FIELD BETWEEN :p 00:00:00 AND ", p, "filter_val"]
     *    - CAR  adds "FIELD IN(carlist)", carlist gets [ts_number LIKE filter_val, ts_name LIKE filter=>PRE_FUNC]
     *    - AGG  adds "FIELD IN(aggregation)", carlist gets [car_mdl LIKE filter_val, trailer_mdl LIKE filter=>PRE_FUNC]
     *
     *  FIELD = field name in DB
     *
     *  FUNi = functions in execution order to pre-call before add to result
     *  FUNi = [ CLASS_NAME, METHOD_NAME, [ METHOD_ARGS_ARRAY ] ]
     *
     *  METHOD_ARGS_ARRAY can contain text {v} to insert filter value (or previous function result)
     *    if filter value/result = ARRAY it will be imploded
     *
     */
    public function parseCustomFilters($flts, $class = '', $user_filters = [], $restoreKey = '') {
        global $DB;

        $or = false;
        self::$filter_oper_cache = [];

        // FIRST - user_filters
        foreach($user_filters as $f_name => $f_func) {
            if(is_callable($f_func)) {
                $val = $this->getFilter($f_name);
                if($val !== false) {
                    $uf = call_user_func_array($f_func, [$val]);
                    if(is_array($uf)) {
                        foreach($uf as $k => $v) {
                            if($k == 'or') {
                                $or = true;
                            } else {
                                $this->filter_obj->$k = $v;
                            }
                        }
                    }
                }
            }
        }

        // SECOND - class filters
        $ret = $class ? $this->parseFilter($class, $restoreKey) : [];

        // THIRD - custom filters
        foreach($flts as $ix => $flt) {
            $par = sprintf('cp%02u', $ix+1);
            $sec = sprintf('sp%02u', $ix+1);
            $flt_name = $flt ? array_shift($flt) : '';
            $flt_pref = $flt ? array_shift($flt) : '';
            $flt_oper = $flt ? array_shift($flt) : '';
            $f_ok = property_exists($this->filter_raw, $flt_name);
            $s_ok = false;
            $ok   = $f_ok;
            switch($flt_pref) {
                case 'car':
                case 'agg':
                    $s_ok = property_exists($this->filter_raw, $flt_oper);
                    $ok   = $f_ok || $s_ok;
                    break;
            }
            if($ok) {
                $flt_val  = $f_ok ? $this->filter_raw->$flt_name : '';
                $flt_sec  = false;
                $flt_field = $flt ? array_shift($flt) : '';
                $flt_func  = $flt ? array_shift($flt) : [];
                $flt_ext   = $flt ? array_shift($flt) : '';

                $skip_empty = true;


                if(is_callable($flt_pref)) {
                    //PageManager::debug($flt_val, 'func(flt_val)');
                    $uf = call_user_func_array($flt_pref, [$flt_val, $flt_oper, $flt_field]);
                    //PageManager::debug($uf, 'ret');
                    if(is_array($uf)) {
                        $flt_val = array_shift($uf);
                        if($uf) $flt_oper = array_shift($uf);
                        if($uf) $flt_field = array_shift($uf);
                        if($uf) $skip_empty = array_shift($uf);
                    } else {
                        $flt_val = $uf;
                    }

                    if(!$flt_field) continue;

                } else {

                    if(!$flt_field) continue;

                    // Pre function value
                    switch($flt_pref) {
                        case 'int':
                            $flt_val = intval($flt_val);
                            break;

                        case 'exp-int':
                            $arr = explode(',', $flt_val);
                            $flt_val = [];
                            foreach($arr as $a) {
                                if($i = intval($a)) {
                                    if($i == PageManager::EMPTY_ID) $i = 0;
                                    $flt_val[] = $i;
                                }
                            }
                            break;

                        case 'exp-int-z':
                            $skip_empty = false;
                            $arr = explode(',', $flt_val);
                            $flt_val = [];
                            foreach($arr as $a) $flt_val[] = intval($a);
                            break;

                        case 'exp-or':
                            $arr = explode(',', $flt_val);
                            $flt_val = 0;
                            foreach($arr as $a) if($i = intval($a)) $flt_val |= $i;
                            break;

                        case 'dt':
                        case 'dt%':
                        case 'dtm':
                            $dt = new DateTime();
                            $tz = $dt->getTimezone();
                            $flt_val = new DateTime($flt_val ? $flt_val : '2000-01-01');
                            $flt_val->setTimezone($tz);
                            switch($flt_pref) {
                                case 'dt':
                                    $flt_val = $flt_val->format('Y-m-d');
                                    break;
                                case 'dt%':
                                    $flt_val = $flt_val->format('Y-m-d%');
                                    break;
                                default:
                                    $flt_val = $flt_val->format('Y-m-d H:i:s');
                                    break;
                            }
                            break;

                        case 'date24':
                        case 'date0':
                        case 'date7':
                            $dt = new DateTime();
                            $tz = $dt->getTimezone();
                            $flt_sec = new DateTime($flt_val->end   ? $flt_val->end   : '2999-12-31');
                            $flt_val = new DateTime($flt_val->start ? $flt_val->start : '2000-01-01');
                            $flt_val->setTimezone($tz);
                            $flt_sec->setTimezone($tz);
                            switch($flt_pref) {
                                case 'date7':
                                    $flt_val->setTime(7, 0, 0);
                                    $flt_sec->setTime(7, 0, 0);
                                    break;

                                case 'date0':
                                    $flt_val->setTime(0, 0, 0);
                                    $flt_sec->setTime(0, 0, 0);
                                    break;

                                default:
                                    $flt_val->setTime(0, 0, 0);
                                    if($flt_sec->format('Y') == '2999') {
                                        $flt_sec = new DateTime($flt_val->format('Y-m-d H:i:s'));
                                    }
                                    $flt_sec->setTime(23, 59, 59);
                                    break;
                            }
                            $flt_val = $flt_val->format('Y-m-d H:i:s');
                            $flt_sec = $flt_sec->format('Y-m-d H:i:s');
                            break;

                        case 'car':
                        case 'car_none':
                            $flt_sec = $s_ok ? $this->filter_raw->$flt_oper : '';
                            PageManager::debug($flt_sec, 'car_sec');
                            if($flt_val || $flt_sec) {
                                $fx = ['id_only'];
                                if($flt_val) $fx[] = [ 'ts_number LIKE :u', 'u', "%$flt_val%" ];
                                if($flt_sec) $fx[] = [ 'ts_name LIKE :m',   'm', "%$flt_sec%" ];
                                $flt_val = implode(',', Car::getList($fx, 'id'));
                                if(!$flt_val) $flt_val = '-1';
                                $flt_oper = 'IN';
                            }
                            break;

                        case 'agg':
                            $flt_sec = $s_ok ? $this->filter_raw->$flt_oper : '';
                            if($flt_val || $flt_sec) {
                                $fx = ['id_only', 'non_empty'];
                                $cars   = '';
                                $trails = '';
                                if($flt_val) $cars   = CarModel::findByFullText($flt_val);
                                if($flt_sec) $trails = TrailerModel::findByText($flt_sec, 0, true);

                                if($cars)   $fx[] = "car_mdl IN($cars)";
                                if($trails) $fx[] = "trailer_mdl IN($trails)";
                                $flt_val = implode(',', Aggregation::getList($fx, 'id'));
                                $flt_oper = 'IN';
                            }
                            break;

                        case 'storage':
                            $tp = 'storage_type';
                            $id = 'storage_id';
                            if(is_string($flt_ext) && $flt_ext) {
                                $fe = explode(',', $flt_ext);
                                $tp = $fe[0];
                                $id = $fe[1];
                            }
                            $fx = [];
                            $lst1 = Storage::findByText($flt_val, 0, true);
                            $lst2 = FixedAsset::findByText($flt_val, 0, true);
                            $lst3 = Contractor::findByText($flt_val, 0, true);
                            $lst4 = PersonPosition::findByText($flt_val, 0, true);
                            if($lst1) $fx[] = "($tp = 1 AND $id IN($lst1))";
                            if($lst2) $fx[] = "($tp = 2 AND $id IN($lst2))";
                            if($lst3) $fx[] = "($tp = 3 AND $id IN($lst3))";
                            if($lst4) $fx[] = "($tp = 4 AND $id IN($lst4))";
                            $flt_val = '(' . implode(' OR ', $fx) . ')';
                            break;

                        case 'frp':
                            $tp = 'frp_type';
                            $id = 'frp_id';
                            if(is_string($flt_ext) && $flt_ext) {
                                $fe = explode(',', $flt_ext);
                                $tp = $fe[0];
                                $id = $fe[1];
                            }
                            $fx = [];
                            $lst1 = Person::findByText($flt_val, 0, true);
                            $lst2 = Contractor::findByText($flt_val, 0, true);
                            if($lst1) $fx[] = "($tp = 1 AND $id IN($lst1))";
                            if($lst2) $fx[] = "($tp = 2 AND $id IN($lst2))";
                            $flt_val = '(' . implode(' OR ', $fx) . ')';
                            break;
                    }
                }

                if(!$flt_val && $skip_empty) continue;

                // Pre call functions
                if(is_array($flt_func)) {
                    foreach($flt_func as $fun) {
                        $fun_class = $fun ? array_shift($fun) : '';
                        $fun_name  = $fun ? array_shift($fun) : '';
                        $fun_args  = $fun ? array_shift($fun) : [];
                        $fun_after = $fun ? array_shift($fun) : '';
                        if($fun_class && $fun_name && is_array($fun_args)) {
                            $args = self::replaceValInArray($fun_args, $flt_val, $flt_sec);
                            //PageManager::debug($args, "$fun_class::$fun_name");
                            $flt_val = call_user_func_array([$fun_class, $fun_name] , $args);
                            //PageManager::debug($DB->sql, "$fun_class::$fun_name sql");
                            if($DB->error) PageManager::debug($DB->error, "$fun_class::$fun_name err");
                        }
                        switch($fun_after) {
                            case 'imp':
                                $flt_val = implode(',', $flt_val);
                                break;
                        }
                    }
                }

                $cnt = 1;
                // Add filters
                switch($flt_oper) {
                    case 'LIKE':
                        $ret[] = ["{$flt_field} LIKE :{$par}", $par, "%{$flt_val}%"];
                        break;
                    case 'BEGINS':
                        $ret[] = ["{$flt_field} LIKE :{$par}", $par, "{$flt_val}%"];
                        break;
                    case 'LIKE+':
                        $ret[] = ["{$flt_field} LIKE :{$par}", $par, $flt_val];
                        break;
                    case 'IN':
                        if(is_array($flt_val)) $flt_val = implode(',', $flt_val);
                        $ret[] = "{$flt_field} IN($flt_val)";
                        break;
                    case '=':
                        $ret[] = ["{$flt_field} = :{$par}", $par, $flt_val];
                        break;
                    case 'BEG-END':
                        list($f_beg, $f_end) = explode(',', $flt_field);
                        $ret[] = ["{$f_beg} >= :{$par}", $par, $flt_val];
                        $ret[] = ["{$f_end} <= :{$sec}", $sec, $flt_sec];
                        $cnt = 2;
                        break;
                    case 'BETWEEN':
                        $ret[] = ["({$flt_field} BETWEEN :{$par} AND :{$sec})", $par, $flt_val];
                        $ret[] = ['', $sec, $flt_sec];
                        $cnt = 2;
                        break;
                    case 'INTERSECT':
                        list($f_beg, $f_end) = explode(',', $flt_field);
                        $ret[] = ["{$f_beg} < :{$sec}", $sec, $flt_sec];
                        $ret[] = ["{$f_end} > :{$par}", $par, $flt_val];
                        $cnt = 2;
                        break;
                    case 'STRICT':
                        list($f_beg, $f_end) = explode(',', $flt_field);
                        $ret[] = ["{$f_beg} = :{$par}", $par, $flt_val];
                        $ret[] = ["{$f_end} = :{$sec}", $sec, $flt_sec];
                        $cnt = 2;
                        break;
                    case 'RAW':
                        if(is_array($flt_val)) {
                            $ret = array_merge($ret, $flt_val);
                            $cnt = count($flt_val);
                        } else {
                            $ret[] = $flt_val;
                        }
                        break;
                    default:
                        $cnt = 0;
                        break;
                }
                
                while($cnt > 0) {
                    self::$filter_oper_cache[] = $flt_name;
                    $cnt--;
                }
            }
        }
        if($or) {
            $ret[] = 'or';
            self::$filter_oper_cache[] = 'or';
        }
        return $ret;
    }

    public function parseFilter($class, $restoreKey = '') {
        $ret = [];
        $this->filter_raw = new stdClass();
        $example = self::getClassExample($class);
        $restore = $restoreKey && method_exists($class, $restoreKey) ? $restoreKey : false;
        $ix = 0;
        foreach($this->filter_obj as $prop => $val) {
            $ix++;
            if(is_object($val)) {
                if(property_exists($val, 'start')) {
                    $min = $val->start;
                    $max = $val->end;
                    if(empty($min) && empty($max)) continue;
                    $this->filter_raw->$prop = $val;
                }
            } else {
                if(trim($val) === '') continue;
                $this->filter_raw->$prop  = $val;
                $fld_name = $prop;
                if($restore) $fld_name = $class::$restore($prop);
                if(property_exists($class, $fld_name)) {
                    $num = is_numeric($example->$fld_name);
                    if(is_object($example->$fld_name)) {
                        if(is_a($example->$fld_name, 'DateTime')) {
                            $num = false;
                        } else {
                            $num = true;
                        }
                        if(property_exists($example->$fld_name, "{$fld_name}_ix")) {}
                    }
                    $op  = $num ? '=' : 'LIKE';
                    $txt = $num ? $val : "%{$val}%";
                    $par = "p{$ix}";
                    $ret[] = ["$fld_name $op :$par", $par, $txt];
                    self::$filter_oper_cache[] = $prop;
                }
            }
        }
        // $this->flt = $ret;
        return $ret;
    }

    public static function getClassExample($class) {
        $ix = $class;
        if(!isset(self::$cache[$ix])) {
            $cls = new $class();
            self::$cache[$ix] = $cls;
        }
        return self::$cache[$ix];
    }

}
