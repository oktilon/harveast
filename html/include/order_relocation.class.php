<?php
class OrderRelocation {
    public $id       = 0;
    public $ord      = 0;
    public $geo_src  = 0;
    public $geo_dst  = 0;
    public $dt_beg   = null;
    public $dt_end   = null;
    public $flags    = 0;
    public $dst      = 0;

    /**
     * @var StPolyline
     */
    public $line    = null;

    public static $total  = 0;

    public static $varRelocationMinPoints   = 3;
    public static $varRelocationMinDistance = 20;
    public static $varRelocationFar         = 20000;

    const FLAG_RELOCATION_IS_FAR = 0x1;

    public function __construct($arg = 0, $with_line = false) {
        global $DB;
        $this->dt_beg = new DateTime('2000-01-01');
        $this->dt_end = new DateTime('2000-01-01');
        $this->line = new StPolyline();

        if(is_numeric($arg)) {
            if(!$arg) return;
            $row = $DB->prepare("SELECT * FROM gps_order_relocations WHERE id = :i")
                        ->bind('i', $arg)
                        ->execute_row();
            if($DB->error) throw new Exception($DB->error);
            $arg = $row;
        }
        if(is_array($arg)) {
            foreach($arg as $k => $v) $this->$k = self::getProperty($k, $v);
        }
        if($with_line) $this->readLine();
    }

    private static function getProperty($key, $val) {
        switch($key) {
            case 'dt_beg':
            case 'dt_end': return new DateTime($val);
        }
        return intval($val);
    }

    public function readLine() {
        global $PG;
        $poly = $PG->prepare("SELECT ST_AsText(pts) pts FROM order_relocations WHERE id = :id")
                    ->bind('id', $this->id)
                    ->execute_scalar();
        $this->line = new StPolyline($poly);
    }

    public function readFastLine() {
        global $PG;
        $poly = $PG->prepare("SELECT ST_AsText(pts) pts FROM order_relocations WHERE id = :id")
                    ->bind('id', $this->id)
                    ->execute_scalar();
        return StPolyline::fastParse($poly);
    }


    public function save($with_line = true) {
        global $PG;
        $t = new SqlTable('gps_order_relocations', $this, ['line']);
        $ret = $t->save($this);
        if($with_line) {
            $q = $PG->prepare("INSERT INTO order_relocations (id, ord, pts)
                                    VALUES (:id, :ord, ST_GeomFromText(:pts))
                                ON CONFLICT (id) DO UPDATE
                                    SET pts = ST_GeomFromText(:pts)
                                      , ord = :ord")
                    ->bind('id',  $this->id)
                    ->bind('ord', $this->ord)
                    ->bind('pts', $this->line->toWKText())
                    ->execute();
        }
        return $ret;
    }

    public function isFar() { return $this->getFlag(self::FLAG_RELOCATION_IS_FAR); }

    public function getFlag($flag) { return ($this->flags & $flag) > 0; }
    public function setFlag($flag, $val) {
        if($val) {
            $this->flags |= $flag;
        } else {
            $this->flags &= ~$flag;
        }
    }

    public function addPoint(OrderLogPoint $pnt) {
        $this->line->addPoint($pnt->pt);
        $this->dst = $this->line->length;
    }


    public function delete() {
        global $PG, $DB;
        $DB->prepare('DELETE FROM gps_order_relocations WHERE id = :i')
            ->bind('i', $this->id)
            ->execute();
        $PG->prepare("DELETE FROM order_relocation_line WHERE log_id = :id")
            ->bind('id', $this->id)
            ->execute();
        $PG->prepare("DELETE FROM order_area WHERE _id = :id")
            ->bind('id', $this->id)
            ->execute();
    }

    public function getSimple() {
        return $this->getJson();
    }



    public function getJson($fast = false) {
        $ret = new stdClass();
        $ret->id  = $this->id;
        $ret->src = GeoFence::getFieldName($this->geo_src);
        $ret->dst = GeoFence::getFieldName($this->geo_dst);
        $ret->b   = intval($this->dt_beg->format('U'));
        $ret->e   = intval($this->dt_end->format('U'));
        $ret->f   = $this->isFar() ? 1 : 0;
        $ret->d   = $this->dst;
        $ret->ln  = $fast ? $this->readFastLine() : $this->line->toGridString();
        return $ret;
    }

    public function getExportJson() {
        $ret = new stdClass();
        // $ret->src = GeoFence::getFieldName($this->geo_src);
        // $ret->dst = GeoFence::getFieldName($this->geo_dst);
        $ret->beg = $this->dt_beg->format('Y-m-d H:i:s');
        $ret->end = $this->dt_end->format('Y-m-d H:i:s');
        // $ret->f   = $this->isFar() ? 1 : 0;
        $ret->dst = $this->dst / 1000.0;
        // $ret->ln  = $fast ? $this->readFastLine() : $this->line->toGridString();
        return $ret;
    }

    /**
     * Finish relocation
     * @param OrderLogPoint $pnt Current point
     * @return bool
     */
    public function finish(OrderLogPoint $pnt = null) {
        if($this->line->pointsCount() < self::$varRelocationMinPoints) return false;
        if ($this->dst < self::$varRelocationMinDistance) return false;
        if($pnt) $this->geo_dst = $pnt->geo_id;
        if($this->dst >= self::$varRelocationFar) $this->setFlag(self::FLAG_RELOCATION_IS_FAR, true);
        return true;
    }

    /**
     * Init or use relocation
     * @param int $oid Work order ID
     * @param OrderLogPoint $pnt Current point
     * @param OrderLogPoint $prev_pnt? Previous point
     * @return OrderRelocation
     */
    public static function init($oid, OrderLogPoint $pnt, OrderLogPoint $prev_pnt = null) {
        $ret = new OrderRelocation();
        $ret->ord      = $oid;
        $ret->geo_src  = $prev_pnt ? $prev_pnt->geo_id : 0;
        $ret->dt_beg   = new DateTime(date('Y-m-d H:i:s', $prev_pnt ? $prev_pnt->dt : $pnt->dt));
        $ret->dt_end   = new DateTime(date('Y-m-d H:i:s', $pnt->dt));
        if($pnt->spd > 0) $ret->addPoint($pnt);
        return $ret;
    }

    public static function reset($oid) {
        global $DB, $PG;
        $pq = $PG->prepare("DELETE FROM order_relocations WHERE ord = :oid")
                    ->bind('oid', $oid)
                    ->execute();
        $dq = $DB->prepare("DELETE FROM gps_order_relocations WHERE ord = :oid")
                    ->bind('oid', $oid)
                    ->execute();
        return $pq && $dq;
    }

    /**
    * Список контролей скорости
    *
    * @param string[] массив условий
    * @param string сортировка
    * @param string LIMIT
    * @return OrderRelocation[]
    */
    public static function getList($flt = array(), $ord = '', $lim = '') {
        global $DB;
        self::$total = 0;
        $with_line = false;
        $fast_json = false;
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
            } elseif($it == 'with_line') {
                $with_line = true;
            } elseif($it == 'fast_json') {
                $fast_json = true;
            } elseif(is_array($it)) {
                $cond = array_shift($it);
                if($cond) $add[] = $cond;
                $par[$it[0]] = $it[1];
            } else {
                $add[] = $it;
            }
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc $flds FROM gps_order_relocations $add $order $limit");
        // echo PHP_EOL . $DB->sql . PHP_EOL;
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
            // echo "$k = $v\n";
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $it = $obj ? new OrderRelocation($row, $with_line) : ($fld ? intval($row[$fld]) : $row);
            $ret[] = ($fast_json && $obj) ? $it->getJson($fast_json) : $it;
        }
        return $ret;
    }
}