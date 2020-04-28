<?php
class AggregationList {
    public $id = 0;
    public $upd = null;
    public $aggregation = null;
    public $techop = null;
    public $techop_cond = null;
    public $hd_width = 0;
    public $spd_min = 0;
    public $spd_max = 0;


    private static $cache = [];
    public  static $total = 0;

    public function __construct($arg = 0) {
        global $DB;
        foreach($this as $k => $v) if($v === null) $this->$k = self::getProperty($k, 0);
        // $this->techop->cond = $this->techop_cond;

        if(is_numeric($arg)) {
            $id = intval($arg);
            if($id == 0) return;
            $arg = $DB->select_row("SELECT * FROM production_rates WHERE id = $id");
        }
        if(is_array($arg) || is_object($arg)) {
            foreach($arg as $key => $val) {
                $this->$key = self::getProperty($key, $val);
            }
        }
        $this->techop->cond = $this->techop_cond;
    }

    private static function getProperty($key, $val) {
        switch($key) {
            case 'upd': return new DateTime($val === 0 ? '1970-01-01' :$val);
            // case 'aggregation': return Aggregation::get($val);
            // case 'techop': return TechOperation::get($val);
            // case 'techop_cond': return TechOperationCondition::get($val);
            default: return intval($val);
        }
        return false;
    }

    public static function init(Aggregation $ag, TechOperation $to, TechOperationCondition $toc, $obj, $by = false) {
        global $DB;
        $spd_min = 0;
        $spd_max = 0;
        $upd = false;
        if($obj && is_object($obj)) {
            $spd_min = property_exists($obj, 'СкоростьМин') ? intval($obj->СкоростьМин) : 0;
            $spd_max = property_exists($obj, 'СкоростьМакс') ? intval($obj->СкоростьМакс) : 0;
        }
        $row = $DB->prepare("SELECT * FROM production_rates
                            WHERE aggregation   = :ag
                                AND techop      = :to
                                AND techop_cond = :toc")
                ->bind('ag',  $ag->id)
                ->bind('to',  $to->id)
                ->bind('toc', $toc->id)
                ->execute_row();
        if($row) {
            $ret = new AggregationList($row);
            if($ret->spd_min != $spd_min) {
                $ret->spd_min = $spd_min;
                $upd = true;
            }
            if($ret->spd_max != $spd_max) {
                $ret->spd_max = $spd_max;
                $upd = true;
            }
            if($ret->hd_width != $toc->width) {
                $ret->hd_width = $toc->width;
                $upd = true;
            }
        } else {
            $ret = new AggregationList();
            $ret->aggregation = $ag;
            $ret->techop      = $to;
            $ret->techop_cond = $toc;
            $ret->hd_width    = $toc->width;
            $ret->spd_min     = $spd_min;
            $ret->spd_max     = $spd_max;
            $upd = true;
        }
        if($upd) {
            $ret->save();
        }
        return $ret;
    }

    public function save() {
        $t = new SqlTable('production_rates', $this, ['upd']);
        return $t->save($this);
    }

    public function getSimple($webix = false, $js = false) {
        $ret = new stdClass();
        $arr = ['id', 'aggregation', 'techop', 'hd_width'];
        foreach($arr as $key) {
            $val = $this->$key;
            if(is_object($val)){
                if($js && method_exists($val, 'getJson')) $val = $val->getJson();
                elseif(method_exists($val, 'getSimple')) $val = $val->getSimple();
            }
            $ret->$key = $val;
        }
        return $ret;
    }

    public function getJson() {
        $ret =  $this->getSimple(false, true);
        $ret->hd_width = $this->hd_width;
        $ret->spd_min = $this->spd_min;
        $ret->spd_max = $this->spd_max;
        $ret->upd = $this->upd->format('Y-m-d H:i:s');
        return $ret;
    }

    public function equal(AggregationList $al) {
        return $this->techop == $al->techop &&
               $this->techop_cond == $al->techop_cond;
    }

    public function delete() {
        global $DB;
        return $DB->prepare("DELETE FROM production_rates
                        WHERE id = :i")
                ->bind('i', $this->id)
                ->execute();
    }

    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new AggregationList($id);
        }
        return self::$cache[$id];
    }

    public static function getWebixArray($flt = [], $ord = 'id DESC', $lim = '') {
        $ret = [ ];
        $lst = self::getList($flt, $ord, $lim);
        foreach($lst as $it) {
            $ret[$it->id] = $it->getJson();
        }
        return $ret;
    }

    public static function countByCarModelId($mid) {
        $ret = [0];
        $agr = '';
        if($mid) {
            $agr = implode(',', Aggregation::getList([['car_mdl = :cm', 'cm', $mid], 'id_only'], 'id'));
            if($agr) {
                $flt = ['count', "aggregation IN($agr)"];
                $ret = self::getList($flt);
            }
        }
        return $ret[0];
    }

    public static function countByCar(Car $car) {
        $ret = 0;
        if($car->id) {
            if($car->model->id) {
                $ret = self::countByCarModelId($car->model->id);
            }
        }
        return $ret;
    }

    /**
     * @return AggregationList
     */
    public static function byOrderLine(WorkOrder $wo, WorkOrderLine $wol) {
        $mid = $wo->car->model->id;
        $tid = $wol->trailer->id;
        $ti2 = 0; // $wol->trailer2->id;

        $toid = $wol->tech_op->id;
        $tcid = $wol->tech_cond->id;

        $agr = Aggregation::getList([
            "car_mdl = $mid",
            "trailer_mdl = $tid",
            "trailer2_mdl = $ti2",
            'id_only'
        ], 'id');
        $agr = $agr ? $agr[0] : 0;

        $lst = self::getList(["aggregation = $agr", "techop = $toid", "techop_cond = $tcid"], 'id');
        return $lst ? $lst[0] : AggregationList::get(0);
    }

    public static function getOperations($txt = '', Car $car, $ord = 'name', $lim = '') {
        global $DB;

        $mdl = 0;
        $ret = [];

        if($car && $car->id && $car->model->id) {
            $mdl = $car->model->id;
        }

        if($mdl) {
            $lst = $DB->prepare("SELECT t.*, tc.id cond, tm.id trl_mdl, tc.width
                    FROM spr_aggregations a
                        LEFT JOIN production_rates al ON al.`aggregation` = a.`id`
                        JOIN techop t ON t.`id` = al.`techop`
                        LEFT JOIN techop_condition tc ON tc.`id` = al.`techop_cond`
                        LEFT JOIN spr_trailer_models tm ON tm.`id` = a.`trailer_mdl`
                    WHERE car_mdl = :cm
                        and (t.flags & :topf) = 0
                    ORDER BY t.`name`, tc.`name`,tm.`name`")
                ->bind('cm', $mdl)
                ->bind('topf', TechOperation::FLAG_TO_DELETED)
                ->execute_all();
            $prev = null;
            $last = null;

            foreach($lst as $row) {
                $top = new TechOperation($row);
                if($top->evalBigId($prev)) {
                    $top = $prev;
                } else {
                    $last = $top->getSimple(true);
                    $ret[] = $last;
                }
                $prev = $top;
                $last->trailers[] = TrailerModel::forOperation($row);
            }
        } else {
            // No techOp if no model! 03.08.19
            // $flt = [];
            // if($grp) $flt[] = "type IN($grp)";
            // $lst = TechOperation::getList($flt, $ord, $lim);
            // foreach ($lst as $it) $ret[] = $it->getSimple(true);
        }

        return $ret;
    }

    public static function getOperations2($txt = '', Car $car, $ord = 'name', $lim = '') {
        global $DB;
        $ret = [];
        $to_cond  = '';
        $to_ids   = '';
        $to_agr   = '';
        $to_types = '';
        $to_list  = '';
        if($txt) {
            $min = 0;
            $max = 0;
            if(preg_match('/\D*(\d+)\-(\d+)\D*/', $txt, $m)) {
                $min = intval($m[1]);
                $max = intval($m[2]);
            } elseif(preg_match('/\D*\-(\d+)\D*/', $txt, $m)) {
                $min = 0;
                $max = intval($m[1]);
            } elseif(preg_match('/\D*(\d+)\D*/', $txt, $m)) {
                $min = intval($m[1]);
                $max = 0;
            }
            $to_cond = TechOperationCondition::findByText($txt, '', true, $min, $max);
            $to_ids = TechOperation::findByText($txt, '', true);
        }

        if($car->id) {
            if($car->model->id) {
                $to_agr = implode(',', Aggregation::getList([['car_mdl = :cm', 'cm', $car->model->id], 'id_only'], 'id'));
            }
            if(empty($to_agr)) {
                $grp = $car->ts_type->techop_group;
                if($grp) {
                    $to_types = implode(',', TechOperationType::getList(["`group`=$grp", 'id_only'], 'id'));
                }
            }
        }

        $flt = [];

        if(!empty($to_agr)) {
            $flt = ["aggregation IN($to_agr)"];
            if($to_ids) $flt[] = "techop IN($to_ids)";
            if($to_cond) $flt[] = "techop_cond IN($to_cond)";
            $flt_self = array_merge($flt, ['techop_list']);
            $to_list = implode(',', self::getList($flt_self, 'techop'));
        }

        if($to_list) { // By aggregations
            $lst = TechOperation::getList(["id IN($to_list)"], $ord, $lim);
            foreach ($lst as $it) {
                $flt_self = array_merge($flt, ["techop={$it->id}", 'cond_list']);
                $cond = self::getList($flt_self, 'techop_cond');
                if($cond) {
                    foreach($cond as $cid) {
                        $trailers = [];
                        $flt_ag = array_merge($flt_self, ["techop_cond=$cid", 'aggregation']);
                        $ags = self::getList($flt_ag, 'aggregation');
                        if($ags) $trailers = Aggregation::getList(['id IN(' . implode(',', $ags) . ')', 'trailers'], 'trailer_mdl');

                        $ex_it = new TechOperation($it->id);
                        $ex_it->cond = TechOperationCondition::get($cid);
                        $ex_it->id = $cid * 1000000 + $it->id;

                        $et = $ex_it->getSimple(true, $trailers);
                        $ret[] = $et;
                    }
                } else {
                    $trailers = [];
                    $ags = self::getList($flt_self, 'aggregation');
                    if($ags) $trailers = Aggregation::getList(['id IN(' . implode(',', $ags) . ')', 'trailers'], 'trailer_mdl');

                    $et = $it->getSimple(true, $trailers);
                    $ret[] = $et;
                }
            }
        } else { // by group and name
            $flt = [];
            if($to_ids) $flt[] = "id IN($to_ids)";
            if($to_types) $flt[] = "type IN($to_types)";
            $lst = TechOperation::getList($flt, $ord, $lim);
            foreach ($lst as $it) $ret[] = $it->getSimple(true);
        }

        return $ret;
    }

    public static function getWidthFilter($wd = 0) {
        global $DB;
        $ret = [];
        $q = $DB->prepare("SELECT techop, techop_cond, trailer_mdl
                        FROM production_rates al
                        LEFT JOIN spr_aggregations a ON al.aggregation = a.id
                        WHERE hd_width = :w
                        GROUP BY techop, techop_cond, trailer_mdl
                        ORDER BY techop, techop_cond, trailer_mdl")
                ->bind('w', $wd * 1000)
                ->execute_all();
        foreach($q as $row) {
            $ret[] = [
                intval($row['techop']),
                intval($row['techop_cond']),
                intval($row['trailer_mdl'])
            ];
        }
        return $ret;
    }

    public static function getList($flt = [], $ord = 'aggregation', $lim = '') {
        global $DB;
        self::$total = 0;
        $obj = true;
        $fld = '';
        $ret = [];
        $par = [];
        $add = [];
        $grp = '';
        $flds = '*';
        foreach($flt as $it) {
            switch($it) {
                case 'id_only':
                    $flds = 'id';
                    $obj  = false;
                    $fld  = $flds;
                    break;

                case 'techop_list':
                    $flds = 'techop';
                    $grp  = 'techop';
                    $obj  = false;
                    $fld  = $flds;
                    break;

                case 'cond_list':
                    $flds = 'techop_cond';
                    $grp  = 'techop_cond';
                    $obj  = false;
                    $fld  = $flds;
                    break;

                case 'aggregation':
                    $flds = 'aggregation';
                    $grp  = 'aggregation';
                    $obj  = false;
                    $fld  = $flds;
                    break;

                case 'count':
                    $flds = 'COUNT(id) cnt';
                    $obj  = false;
                    $fld  = 'cnt';
                    break;

                default:
                    if(is_array($it)) {
                        $cond = array_shift($it);
                        switch($cond) {
                            case 'fields':
                                $flds = implode(',', $it);
                                break;
                            case 'group':
                                $grp = implode(',', $it);
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
        $group = $grp ? "GROUP BY $grp" : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM production_rates $add $group $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        $total = count($rows);
        if($calc) {
            $total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new AggregationList($row) : ($fld ? intval($row[$fld]) : $row);
        }
        self::$total = $total;
        return $ret;
    }
}
