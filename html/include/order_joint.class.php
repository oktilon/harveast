<?php
class OrderJoint {
    public $id       = 0;
    public $geo      = 0;
    public $techop   = 0;
    public $top_cond = 0;

    public $d_beg    = null;
    public $d_end    = null;

    public $flags    = 0;
    public $field    = 0;

    public $area     = 0.0;
    public $tot      = 0.0;

    public $cluster  = null;

    public $close_dt = null;
    public $close_user = null;

    /**
     * @var OrderJointItem[]
     */
    public $list = [];

    //private static $cache = [];
    public static $total  = 0;
    public static $log    = '';
    public static $dbg_on = false;
    public static $error = '';

    const FLAG_NEED_RECALC    = 0x01;
    const FLAG_IS_CLOSED      = 0x02;
    const FLAG_IS_DBL_TRACK   = 0x04;

    const FLAG_BY_TOTAL       = 0x10;
    const FLAG_BY_USER        = 0x20;

    const FLAG_IS_FUTURE_YEAR = 0x100;

    public static $ordersList = [];
    public static $purgeList  = [];

    public static $varMaxIslandArea       = 0.3;  // Размер островка под удаление (Га)
    public static $varAdditionalPercent   = 5;    // Add this percent to calculation (%)
    public static $varAdditionalPercentSm = 2;    // Add this percent to calculation (%) harvest and sowing

    public static $flags_nm = [
        ['f'=>self::FLAG_NEED_RECALC,    'n'=>'Need recalc',   'i'=>'fas fa-exclamation-triangle', 'c'=>'#CE5C00'],
        ['f'=>self::FLAG_IS_CLOSED,      'n'=>'Closed',        'i'=>'fas fa-flag-checkered',       'c'=>'#034605'],

        ['f'=>self::FLAG_BY_TOTAL,       'n'=>'Full field',    'i'=>'far fa-square',               'c'=>'#204A87'],
        ['f'=>self::FLAG_BY_USER,        'n'=>'User defined',  'i'=>'far fa-user',                 'c'=>'#5C3566'],

        ['f'=>self::FLAG_IS_FUTURE_YEAR, 'n'=>'Next year',     'i'=>'far fa-hand-point-up',        'c'=>'#3277a8'],
    ];

    public function __construct($arg = 0, $items = false) {
        global $DB;
        $this->d_beg = new DateTime();
        $this->d_end = new DateTime();
        $this->cluster = Cluster::get(0);
        $this->close_dt = new DateTime('2000-01-01');
        $this->close_user = CUser::get(0);

        if(is_object($arg) && is_a($arg, 'OrderJoint')) {
            $this->geo = $arg->geo;
            $this->techop = $arg->techop;
            $this->cluster = $arg->cluster;
            $this->flags = $arg->flags & 0x05;
            if(is_object($items) && is_a($items, 'OrderJointItem')) {
                $this->d_beg = $items->cloneBeg();
                $this->d_end = $items->cloneEnd();
                $this->list[] = $items;
            }
            return;
        }
        if(is_object($arg) && is_a($arg, 'OrderLog')) {
            $this->geo = $arg->geo;
            $this->techop = $arg->techop;
            $this->top_cond = $arg->top_cond;
            $this->d_beg = $arg->dt_beg;
            $this->d_end = $arg->dt_end;
            $this->flags = 0;
            return;
        }

        if(is_numeric($arg)) {
            if(!$arg) return;
            $rows = $DB->select("SELECT * FROM gps_joint WHERE id = $arg");
            if($rows === FALSE) throw new Exception($DB->error);
            $arg = $rows[0];
        }
        if(is_array($arg)) {
            if(isset($arg['dt_beg'])) {
                $this->initFromLog($arg);
            } else {
                foreach($arg as $k => $v) $this->$k = self::getProperty($k, $v);
            }
        }
        if($items) $this->readItems();
    }

    private static function getProperty($key, $val) {
        switch($key) {
            case 'd_beg':
            case 'd_end':
            case 'close_dt': return new DateTime($val);
            case 'area':
            case 'tot': return floatval($val);
            case 'logs': return $val;
            case 'cluster': return Cluster::get($val);
            case 'close_user': return CUser::get($val);
        }
        return intval($val);
    }

    public function initFromLog($arg) {
        $this->geo    = intval($arg['geo']);
        $this->techop = intval($arg['techop']);
        $this->d_beg  = new DateTime($arg['dt_beg']);
        $this->d_end  = new DateTime($arg['dt_end']);
        if(isset($arg['dbl_tr'])) {
            $this->setDoubleTrack(intval($arg['dbl_tr']) == 1);
        }
    }

    public function addLog($oji) {
        $this->list[] = $oji;
        if($this->d_beg > $oji->beg) $this->d_beg = $oji->beg;
        if($this->d_end < $oji->end) $this->d_end = $oji->end;
    }

    public function addLogRow($row) {
        $dbl_track = intval($row['dbl_tr']) == 1;
        if($this->isDoubleTrack() && !$dbl_track) $this->setDoubleTrack(false);
        $this->addLog(new OrderJointItem($row));
    }

    public function addLogObj(OrderLog $ol, $dbl_track) {
        if($this->isDoubleTrack() && !$dbl_track) $this->setDoubleTrack(false);
        $this->addLog(new OrderJointItem($ol, $this->id));
    }

    public function readItems() {
        $this->list = OrderJointItem::readJoint($this->id, false);
    }

    public function save() {
        $t = new SqlTable('gps_joint', $this, ['list']);
        $ret = $t->save($this);
        OrderJointItem::save($this->id, $this->list);
        return $ret;
    }

    public function delete() {
        global $DB, $PG;
        WorkOrder::$err[] = "joint_to_del($this->id)";
        if(!$this->list) $this->readItems();
        foreach($this->list as $oji) $oji->delete();

        $q = $PG->prepare("DELETE FROM order_joint WHERE id = :i")
                    ->bind('i', $this->id)
                    ->execute();
        WorkOrder::$err[] = $q ? 'PG:ok' : "PG:{$PG->error}";

        $q = $DB->prepare("DELETE FROM gps_joint WHERE id = :i")
                    ->bind('i', $this->id)
                    ->execute();
        WorkOrder::$err[] = $q ? 'delJNT:ok' : "delJNT:{$DB->error}";
        return $q;
    }

    public function getFlag($flag) { return ($this->flags & $flag) > 0; }
    public function setFlag($flag, $val) {
        if($val) {
            $this->flags |= $flag;
        } else {
            $this->flags &= ~$flag;
        }
    }

    public function needRecalc() { return $this->getFlag(self::FLAG_NEED_RECALC); }
    public function isClosed() { return $this->getFlag(self::FLAG_IS_CLOSED); }
    public function isDoubleTrack() { return $this->getFlag(self::FLAG_IS_DBL_TRACK); }
    public function isFutureYear() { return $this->getFlag(self::FLAG_IS_FUTURE_YEAR); }
    public function setRecalc($on = true) { $this->setFlag(self::FLAG_NEED_RECALC, $on); }
    public function setClosed($on = true) { $this->setFlag(self::FLAG_IS_CLOSED, $on); }
    public function setDoubleTrack($on = true) { $this->setFlag(self::FLAG_IS_DBL_TRACK, $on); }

    /**
     * Get operation year
     * @return int
     */
    public function getYear() {
        $ret = intval($this->d_beg->format('Y'));
        if($this->isFutureYear()) $ret++;
        return $ret;
    }

    public function getPoly() {
        global $PG;
        $r = $PG->prepare("SELECT ST_AsText(poly) FROM order_joint WHERE id=:i")
                ->bind('i', $this->id)
                ->execute_scalar();
        return StMultiPolygon::fastParse($r);
    }

    public function evalTotalArea() {
        global $DB;
        $r = '0.0';
        if($this->list) {
            $logs = implode(',', $this->getLogList());
            $r = $DB->prepare("SELECT SUM(ord_area)
                                FROM gps_order_log
                                WHERE id IN({$logs})")
                    ->execute_scalar();
        }
        $this->tot = floatval($r);
    }

    public function includeLog(OrderLog $ol) {
        if($ol->dt_beg < $this->d_beg) $this->d_beg = $ol->dt_beg;
        if($ol->dt_end > $this->d_end) $this->d_end = $ol->dt_end;
    }

    public function close($area, $flag) {
        global $PM;
        if($flag) {
            $this->evalJointArea($area);
        }
        $this->flags |= $flag | self::FLAG_IS_CLOSED;
        $this->close_user = $PM->user;
        $this->close_dt = new DateTime();
        foreach ($this->list as $oji) $oji->closeLog();
        return $this->save();
    }

    public function getCluster() {
        global $DB;
        $logs = implode(',', $this->getLogList());
        $q = $DB->prepare("SELECT f.`cluster`, COUNT(*) AS cnt
                            FROM gps_order_log ol
                            LEFT JOIN spr_firms f ON f.id = ol.`firm`
                            WHERE ol.id IN($logs)
                            GROUP BY f.`cluster`
                            ORDER BY cnt DESC")
                ->execute_row();
        if($q) $this->cluster = Cluster::get($q['cluster']);
    }

    public function removeIslands($min_size = 0, $isUserTable = false) {
        global $PG;

        if(!$this->id) return;

        $tbl = $isUserTable ? 'user_joint' : 'order_joint';

        $q = $PG->prepare("SELECT ST_NumGeometries(poly) FROM $tbl WHERE id = :i")
            ->bind('i', $this->id)
            ->execute_scalar();
        PageManager::debug($q, 'ST_NumGeometries');
        if($q > 1) {
            $PG->prepare("UPDATE $tbl
                    SET poly = (SELECT ST_Collect(gg) FROM (
                        SELECT filter_rings((ST_Dump(o2.poly)).geom, :s) AS gg
                        FROM $tbl o2 WHERE o2.id = :i) as subq)
                    WHERE id = :i")
                ->bind('i', $this->id)
                ->bind('s', $min_size)
                ->execute();
            PageManager::debug($PG->error, 'DumpMpErr');
        } else {
            $PG->prepare("UPDATE $tbl
                    SET poly = filter_rings(poly, :s)
                    WHERE id = :i")
                ->bind('i', $this->id)
                ->bind('s', $min_size)
                ->execute();
            PageManager::debug($PG->error, 'DumpErr');
        }
    }

    /**
     * Order list by dates
     *
     * @return OrderJointDay[]
     */
    public function getDays() {
        $byDate = [];
        foreach ($this->list as $oji) {
            if(!isset($byDate[$oji->dt])) $byDate[$oji->dt] = new OrderJointDay();
            $byDate[$oji->dt]->addItem($oji);
        }

        ksort($byDate);
        return $byDate;
    }

    public function getAdditionalPercent() {
        $small = TechOperation::isSmallPercentOperation($this->techop, $this->top_cond);
        if($small) echo '(sm)';
        return $small ? self::$varAdditionalPercentSm : self::$varAdditionalPercent;
    }

    public function getLogPrefix($msg = '') {
        $ret = "J{$this->id} [{$this->geo}-{$this->techop}] ";
        $ret .= self::$log . $msg;
        return $ret;
    }

    public function evalJointArea($area = 0, $fast = false) {
        global $DB,$PG;
        self::$log = '';

        if($this->id == 0) return;

        $this->area = 0;
        $this->evalTotalArea();

        $fa = $DB->prepare("SELECT ar FROM gps_geofence WHERE id = :i")
                ->bind('i', $this->geo)
                ->execute_scalar();
        $fa = round((intval($fa) / 10000.0), 2);

        if($this->isDoubleTrack()) {
            $fa *= 2;
            self::$log .= "dbl_track ";
        }

        if($this->tot == 0) {
            self::$log .= "zero_total";
            Info($this->getLogPrefix());
            return;
        }

        $addPercent = $this->getAdditionalPercent();

        $byDate = $this->getDays();

        $cnt = count($byDate);
        self::$log .= "steps=$cnt";

        Info($this->getLogPrefix());
        self::$log = '';

        $all  = [];
        $prev = 0;
        $ix   = 0;
        foreach($byDate as $tm => $ojd) {
            $ix++;
            self::$log = "Step $ix " . date('d.m', $tm);
            $all = array_merge($all, $ojd->ids);

            $lst = implode(',', $all);

            //if(!$ojd->isLocked()) {
            $q = $PG->prepare("INSERT INTO order_joint (id, poly)
                                SELECT :i, ST_Union(poly::geometry)
                                FROM order_area
                                WHERE _id IN($lst)
                            ON CONFLICT (id)
                            DO UPDATE SET poly = excluded.poly;")
                    ->bind('i', $this->id)
                    ->execute();
            if($q) {
                self::$log .= " union_ok";
                $this->removeIslands(self::$varMaxIslandArea, false);
                self::$log .= " purge_ok";
                $q = $PG->prepare("SELECT ST_Area(poly::geography)
                                    FROM order_joint
                                    WHERE id = :i")
                        ->bind('i', $this->id)
                        ->execute_scalar();
                $this->area = $q ? round((intval($q) / 10000.0), 2) : 0.0;

                if($this->isDoubleTrack()) {
                    self::$log .= " [DBL]";
                    $this->area *= 2;
                }

                // addition
                $bigger = round($this->area * (1 + ($addPercent / 100)), 2);
                if($bigger >= $fa) {
                    self::$log .= " [$addPercent %]";
                    $this->area = $fa;
                }

                // Override on close
                if($ix == $cnt && $area) {
                    self::$log .= " [AR]";
                    $this->area = $area;
                }
            } else {
                self::$log .= " [ERR:{$PG->error}]";
            }

            //$fixed = $ojd->getArea();
            $dayTot = $ojd->tot; // - $fixed;

            $parseArea = $this->area - $prev; // - $fixed;
            if($parseArea < 0) $parseArea = 0;
            $k = $dayTot > 0 ? ($parseArea / $dayTot) : 0;
            $inf = sprintf(" t:%0.4f, a:%0.4f, k:%0.4f", $dayTot, $parseArea, $k);
            Info($this->getLogPrefix($inf));

            foreach ($ojd->logs as $iy => $ol) {
                //if(!$ojd->isLockedItem($iy)) {
                $can = $ol->setJoint($k, $fast);
                $sav = $ol->orderReadyToSave();
                if($can && $sav) {
                    self::appendOrderToList($ol->ord);
                }
                if(!$fast && !$sav) {
                    self::appendOrderToPurgeList($ol->ord);
                }
                //}
            }

            // } else {
            //     $this->area += $ojd->getArea();
            //     printf("locked a:%0.4f\n", $this->area);
            // }
            $prev = $this->area;
        }

        $total = 0;
        $last  = null;
        foreach ($byDate as $tm => $ojd) {
            foreach ($ojd->logs as $ol) {
                $total += $ol->jnt_area;
                $last = $ol;
            }
        }
        if($total != $this->area && $last) {
            $da = $this->area - $total;
            $last->setLastJoint($da);
        }
    }

    public function evalJointAreaByLogs() {
        global $DB,$PG;

        $this->area = 0;
        $ret = 0;
        $this->evalTotalArea();
        $now = mktime(0, 0, 0);

        $fa = $DB->prepare("SELECT ar FROM gps_geofence WHERE id = :i")
                ->bind('i', $this->geo)
                ->execute_scalar();
        $fa = round((intval($fa) / 10000.0), 2);

        if($this->isDoubleTrack()) {
            $fa *= 2;
            echo "dbl_track ";
        }

        if($this->tot == 0) {
            echo "zero_total\n";
            return;
        }

        $byDate = $this->getDays();

        $cnt = count($byDate);
        echo "steps=$cnt\n";

        $all  = [];
        $prev = 0;
        $ix   = 0;
        foreach($byDate as $tm => $ojd) {
            $ix++;
            echo "Step $ix " . date('d.m H:i', $tm) . ', now=' . date('d.m H:i', $now);
            $all = array_merge($all, $ojd->ids);

            $lst = implode(',', $all);

            if(!$ojd->isLocked()) {
                $q = $PG->prepare("INSERT INTO user_joint (id, poly)
                                    SELECT :i, ST_Union(poly::geometry)
                                    FROM order_area
                                    WHERE _id IN($lst)
                                ON CONFLICT (id)
                                DO UPDATE SET poly = excluded.poly;")
                        ->bind('i', $this->id)
                        ->execute();
                if($q) {
                    echo " union_ok ";
                    $this->removeIslands(self::$varMaxIslandArea, true);
                    echo " purge_ok ";
                    $q = $PG->prepare("SELECT ST_Area(poly::geography)
                                        FROM user_joint
                                        WHERE id = :i")
                            ->bind('i', $this->id)
                            ->execute_scalar();
                    $this->area = $q ? round((intval($q) / 10000.0), 2) : 0.0;

                    if($this->isDoubleTrack()) {
                        echo " [DBL] ";
                        $this->area *= 2;
                    }

                    // addition
                    $bigger = round($this->area * (1 + (self::$varAdditionalPercent / 100)), 2);
                    if($bigger >= $fa) {
                        $this->area = $fa;
                    }

                } else {
                    echo " {$PG->error} ";
                }

                $fixed = $ojd->getArea();
                $dayTot = $ojd->tot - $fixed;

                $parseArea = $this->area - $prev - $fixed;
                $k = $dayTot > 0 ? ($parseArea / $dayTot) : 0;
                printf("t:%0.4f, a:%0.4f, k:%0.4f\n", $dayTot, $parseArea, $k);

                foreach ($ojd->logs as $ix => $ol) {
                    if(!$ojd->isLockedItem($ix)) {
                        $ol->setJoint($k, false);
                    }
                }

            } else {
                $this->area += $ojd->getArea();
            }
            if($tm >= $now) {
                $ret = $this->area - $prev;
                if($ret < 0) $ret = 0;
            }
            $prev = $this->area;
        }

        $total = 0;
        $last  = null;
        foreach ($byDate as $tm => $ojd) {
            foreach ($ojd->logs as $ol) {
                $total += $ol->jnt_area;
                $last = $ol;
            }
        }
        if($total != $this->area && $last) {
            $da = $this->area - $total;
            $last->setLastJoint($da);
        }
        return $ret;
    }

    public function getLogList() {
        $ret = array_map( function($x){ return $x->log_id; }, $this->list);
        sort($ret);
        return $ret;
    }

    public function mergeList(OrderJoint $oj) {
        $add = array_udiff($oj->list, $this->list, ['OrderJointItem', 'compareLog']);
        if(count($add) == 0) return;
        usort($add, ['OrderJointItem', 'compareDt']);
        $lst = [];
        $new = reset($add);
        foreach ($this->list as $old) {
            while($new !== false) {
                $cmp_dt = OrderJointItem::compareDt($new, $old);
                if($cmp_dt < 0) {
                    $lst[] = $new;
                    $new = next($add);
                    continue;
                } elseif($cmp_dt == 0) {
                    $cmp_lg = OrderJointItem::compareLog($new, $old);
                    if($cmp_lg < 0) {
                        $lst[] = $new;
                        $new = next($add);
                        continue;
                    }
                }
                break;
            }
            $lst[] = $old;
        }
        while($new  !== false) {
            $lst[] = $new;
            $new = next($add);
        }
        $this->list = $lst;
    }

    public function uncalculatedOrdersCount() {
        global $DB;
        $oids = [];
        foreach($this->list as $oji) $oids[] = $oji->ord_id;
        if(!$oids) return 0;
        $chk = WorkOrder::FLAG_ORDER_AREA;
        $tst = $chk | WorkOrder::FLAG_ORDER_JOINT;
        $oids = implode(',', $oids);
        $cnt = intval(
            $DB->prepare("SELECT count(*)
                        FROM gps_orders WHERE
                        id IN($oids)
                        AND (flags & $tst) = $chk")
                ->execute_scalar()
        );
        return $cnt;
    }

    public function verify($oj) { // Newest
        $recalc = false;
        if($oj->d_beg < $this->d_beg) {
            $recalc = true;
            $this->d_beg = $oj->d_beg;
            $this->mergeList($oj);
        }
        if($oj->d_end > $this->d_end) {
            $recalc = true;
            $this->d_end = $oj->d_end;
            $this->mergeList($oj);
        }
        if(!$recalc) {
            $me = $this->getLogList();
            $it = $oj->getLogList();
            $diff = array_diff($it, $me);
            if(count($diff) > 0) {
                $this->mergeList($oj);
                $recalc = true;
            }
        }
        if(!$recalc) {
            $recalc = $this->uncalculatedOrdersCount() > 0;
        }
        if($recalc) $this->setRecalc($recalc);
        return;
    }

    /**
     * Find existing joint or create new one
     *
     * @param $oj OrderJoint from findJoints function
     *
     * @return OrderJoint
     */
    public static function checkJoint($oj) {
        global $DB;
        $flt = [
            'list',
            ['(flags & :f) = 0', 'f', self::FLAG_IS_CLOSED],
            ['geo = :g',      'g', $oj->geo],
            ['techop = :t',   't', $oj->techop],
            ['top_cond = :c', 'c', 0],
            ['d_beg <= :e',   'e', $oj->d_end->format('Y-m-d H:i:s')],
            ['d_end >= :b',   'b', $oj->d_beg->format('Y-m-d H:i:s')]
        ];
        $lst = self::getList($flt);
        printf("%d-%d [%s - %s] (%d)",
            $oj->geo,
            $oj->techop,
            $oj->d_beg->format('Y-m-d H:i:s'),
            $oj->d_end->format('Y-m-d H:i:s'),
            count($lst)
        );

        if($lst) {
            $ojo = $lst[0];
            $ojo->verify($oj);
            return $ojo;
        }
        $oj->getCluster();
        $oj->setRecalc(true);
        $oj->save();
        echo " new (cl:{$oj->cluster->id})";
        if($DB->error) $oj->id = -1;
        return $oj;
    }

    public static function findJoints($dt_eval = '', $geo_only) {
        global $DB;
        $dt = $dt_eval ? new DateTime($dt_eval) : new DateTime();

        $flagArea = WorkOrder::FLAG_ORDER_AREA;
        $flagLog  = WorkOrder::FLAG_ORDER_LOG;

        $ord_flg_test = WorkOrder::allErrors() |
                        WorkOrder::FLAG_ORDER_DEL |
                        $flagLog | $flagArea;

        $ord_flg_val  = $flagLog | $flagArea;

        $add = '';
        $dn  = '';
        if($dt_eval) {
            $add = ' AND l.dt_beg < :dn ';
            $dn  = $dt->format('Y-m-d');
        }

        if($geo_only) {
            $add .= " AND l.geo = {$geo_only} ";
        }

        $dt->modify('-1 MONTHS');
        $DB->prepare("SELECT l.geo, l.techop,
                        l.dt_beg, l.dt_end, l.id AS log_id, l.ord AS ord_id
                        , max(if(o.flags & :db, 1, 0)) AS dbl_tr
                    FROM gps_order_log l
                    LEFT JOIN gps_orders o ON o.id = l.ord
                    WHERE l.dt_beg > :d $add
                        AND l.geo > 0
                        AND (l.flags & :f) = 0
                        AND (o.flags & :ot) = :ov
                        -- AND o.id IN(81,82,428,475)
                    GROUP BY l.id, l.geo, l.techop, l.dt_beg, l.dt_end, l.ord
                    ORDER BY l.geo, l.techop, l.dt_beg, l.id")
            ->bind('d', $dt->format('Y-m-d'))
            ->bind('f', OrderLog::FLAG_IS_REMOVED | OrderLog::FLAG_IS_CLOSED) //  0xc
            ->bind('db', WorkOrder::FLAG_ORDER_DBL_TRACK)  // 0x200
            ->bind('ot', $ord_flg_test) // 0xf007
            ->bind('ov', $ord_flg_val);  // 0x6
        if($dt_eval) $DB->bind('dn', $dn);
        $rows = $DB->execute_all();
        /** @var OrderJoint[] */
        $lst = [];
        foreach($rows as $row) {
            $ix = $row['geo'] . '-' . $row['techop'];
            if(!isset($lst[$ix])) {
                $lst[$ix] = new OrderJoint($row);
            }
            $lst[$ix]->addLogRow($row);
        }
        $ret = [];
        foreach($lst as $oj) {
            $ret = array_merge($ret, $oj->selfParser());
        }
        return $ret;
    }

    /**
     * @return OrderJoint
     */
    public static function makeJoint($logs) {
        global $PG;
        if(count($logs) == 0) return new OrderJoint();
        $log = $logs[0];
        $jnt = new OrderJoint($log);
        $jnt->id = intval($PG->prepare('INSERT INTO user_joint (poly) VALUES (null) returning id;')
                            ->execute_scalar());
        foreach($logs as $log) {
            $ord = WorkOrder::get($log->ord);
            $jnt->addLogObj($log, $ord->isDoubleTrack());
        }
        return $jnt;
    }

    public function myLog() {
        $x = [];
        foreach ($this->list as $y) $x[] = $y->ttLog();
        return '[{' . implode('},{', $x) . '}]';
    }

    public function ttLog($oji, $last, $top) {
        $ls = $last == null ? 'null' : $last->ttLog();
        $tp = $top == null ? '{null}' : $top->myLog();
        echo "l:{$oji->log_id}, t:{$oji->dt}, last:{{$ls}}, top:$tp\n";
    }

    public function selfParser() {
        $last = null;
        $top  = null;
        $ret  = [];
        $log  = false;
        foreach($this->list as $oji) {
            if($log) $this->ttLog($oji, $last, $top);
            if($last == null) {
                $top = new OrderJoint($this, $oji);
                $last = $oji;
                continue;
            }
            if($oji->nearTo($last)) {
                if($log) echo $oji->ntLog($last);
                $top->addLog($oji);
            } else {
                if($log) echo $oji->ntLog($last);
                $ret[] = $top;
                $top = new OrderJoint($this, $oji);
            }
            $last = $oji;
        }
        $ret[] = $top;
        if(count($ret) > 1) {
            $pref = "Parse {$this->geo}-{$this->techop} (";
            foreach($ret as $ix => $ooo) Info($pref . ($ix+1) . ') ' . $ooo->myLog());
        }
        return $ret;
    }

    public function getSimple() {
        $ret = new stdClass();
        $arr = ['id', 'geo', 'techop', 'd_beg', 'd_end'];
        foreach($arr as $key) {
            $val = $this->$key;
            if(is_a($val, 'DateTime')) $val = $val->format('Y-m-d H:i:s');
            elseif(is_object($val)) $val = $val->getSimple();
            $ret->$key = $val;
        }
        return $ret;
    }

    public function getJson($crop = true) {
        $ret = new stdClass();
        if($crop) {
            $field = GeoFence::getField($this->geo);
            $crop_by = $field->getCropByYear($this->getYear());
            $ret->crop = $crop_by->getSimple();
        }
        foreach($this as $key => $val) {
            if(is_a($val, 'DateTime')) $val = $val->format('Y-m-d H:i:s');
            if($key == 'cluster') $val = $val->getSimple();
            if($key == 'list') {
                $val = [];
                //if($this->lines) foreach($this->lines as $line) $val[] = $line->getSimple();
            }
            $ret->$key = $val;
        }
        return $ret;
    }

    public static function resetOrdersList() {
        self::$ordersList = [];
        self::$purgeList  = [];
    }

    public static function appendOrderToList($oid) {
        if(!in_array($oid, self::$ordersList)) {
            self::$ordersList[] = $oid;
        }
    }

    public static function appendOrderToPurgeList($oid) {
        if(!in_array($oid, self::$purgeList)) {
            self::$purgeList[] = $oid;
        }
    }

    public static function markOrdersFromList($fast) {
        global $DB;
        if(count(self::$ordersList) > 0) {
            $oids = implode(',', self::$ordersList);
            $flag = $fast ? WorkOrder::FLAG_ORDER_JOINT_FAST : WorkOrder::FLAG_ORDER_JOINT;
            $DB->prepare("UPDATE gps_orders
                        SET flags = flags | :f
                        WHERE id IN ($oids)")
                ->bind('f', $flag)
                ->execute();

            // Log
            $q = $DB->prepare("SELECT id, flags FROM gps_orders WHERE id IN ($oids)")
                    ->execute_all();
            foreach($q as $r) {
                Info(sprintf('fin joint O:%d [%X]'
                    , intval($r['id'])
                    , intval($r['flags'])
                ));
            }

        }
    }

    public static function getWebixArray($flt = [], $ord = 'd_beg DESC', $lim = '') {
        $ret = [ ];
        $lst = self::getList($flt, $ord, $lim);
        foreach($lst as $it) {
            $it->readItems();
            $obj = $it->getJson();

            // $obj->top_cond = TechOperationCondition::get($it->top_cond);
            $top = TechOperation::get($it->techop);
            // $top->cond = $obj->top_cond;

            $geo = GeoFence::get($it->geo, true, true);

            $obj->geo = $geo->getSimple();
            $obj->techop = $top->getSimple();

            $ret[] = $obj;
        }
        return $ret;
    }

    /**
     * Get all users who closed joints
     *
     * @param int $mode = 0-CUser, 1-getSimple, 2-getJson
     * @return CUser[]
     */
    public static function getUsersList($mode = 1) {
        $ret = [];
        $uids = self::getList(['users']);
        foreach($uids as $uid) {
            if($uid > 0) {
                $u = CUser::get($uid);
                switch($mode) {
                    case 1: $ret[] = $u->getSimple(); break;
                    case 2: $ret[] = $u->getJson(); break;
                    default: $ret[] = $u;
                }
            }
        }
        return $ret;
    }

    /**
     * Get all fields having joints
     */
    public static function getFieldsList() {
        $ret = [];
        $ids = self::getList(['fields']);
        foreach($ids as $id) {
            if($id > 0) {
                $f = GeoFence::read($id);
                $ret[] = $f;
            }
        }
        return $ret;
    }

    /**
     * Get all fieldworks operations from joints
     */
    public static function getFieldWorkdsList() {
        $ret = [];
        $ids = self::getList(['techops']);
        foreach($ids as $id) {
            if($id > 0) {
                $t = new TechOperation($id);
                $ret[] = $t->getSimple();
            }
        }
        return $ret;
    }

    public static function cacheTechOperationsAndFields($flt, $flt_cache, $flt_top, $flt_field) {
        global $DB;

        $top = [];
        $fld = [];
        $par_top = [];
        $par_fld = [];
        $add_top = [];
        $add_fld = [];

        foreach($flt as $ix => $it) {
            $cache_item = isset($flt_cache[$ix]) ? $flt_cache[$ix] : '';
            $ok_top = $cache_item != $flt_top;
            $ok_fld = $cache_item != $flt_field;

            if($it == 'id_only') {
                // dummy
            } elseif($it == 'list') {
                // dummy
            } elseif(is_array($it)) {
                $cond = array_shift($it);
                switch($cond) {
                    case 'fields':
                        break;

                    default:
                        if($cond) {
                            if($ok_top) $add_top[] = $cond;
                            if($ok_fld) $add_fld[] = $cond;
                        }
                        if($ok_top) $par_top[$it[0]] = $it[1];
                        if($ok_fld) $par_fld[$it[0]] = $it[1];
                        break;
                }
            } else {
                if($ok_top) $add_top[] = $it;
                if($ok_fld) $add_fld[] = $it;
            }
        }
        $add_top[] = 'techop > 0';
        $add_fld[] = 'geo > 0';

        // Techops
        $add = implode(' AND ', $add_top);
        $DB->prepare("SELECT techop FROM gps_joint
                        WHERE $add
                        GROUP BY techop
                        ORDER BY techop");
        foreach($par_top as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        foreach($rows as $row) {
            $top[] = intval($row['techop']);
        }

        // Fields
        /*$add = implode(' AND ', $add_fld);
        $DB->prepare("SELECT geo FROM gps_joint
                        WHERE $add
                        GROUP BY geo
                        ORDER BY geo");
        foreach($par_fld as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        foreach($rows as $row) {
            $fld[] = intval($row['geo']);
        }*/

        return [$top, $fld];
    }

    /**
     * Get Order Joints list
     *
     * @param $flt mixed[]
     * @param $ord string ORDER BY expression
     * @param $lim string LIMIT expression
     * @return OrderJoint[]
     */
    public static function getList($flt = array(), $ord = '', $lim = '') {
        global $DB;
        self::$total = 0;
        $lst = false;
        $obj = true;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        $fld  = '';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = $fld = 'id';
                $obj  = false;
            } elseif($it == 'list') {
                $lst = true;
            } elseif($it == 'users') {
                $flds = 'DISTINCT close_user';
                $lim = '';
                $ord = 'close_user';
                $fld = 'close_user';
                $obj = false;
                $add[] = 'close_user > 0';
            } elseif($it == 'fields') {
                $flds = 'DISTINCT geo';
                $lim = '';
                $ord = 'geo';
                $fld = 'geo';
                $obj = false;
                $add[] = 'geo > 0';
            } elseif($it == 'techops') {
                $flds = 'DISTINCT techop';
                $lim = '';
                $ord = 'techop';
                $fld = 'techop';
                $obj = false;
                $add[] = 'techop > 0';
            } elseif(is_array($it)) {
                $cond = array_shift($it);
                switch($cond) {
                    case 'fields':
                        $flds = implode(',', $it);
                        $obj = false;
                        break;

                    default:
                        if($cond) $add[] = $cond;
                        $par[$it[0]] = $it[1];
                        break;
                }
            } else {
                $add[] = $it;
            }
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM gps_joint $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$error = $DB->error;
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new OrderJoint($row, $lst) : ($fld ? intval($row[$fld]) : $row);
        }
        return $ret;
    }
}