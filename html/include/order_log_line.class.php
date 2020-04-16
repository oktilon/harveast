<?php
/* PostgreSQL server */
class OrderLogLine {
    public $id       = 0;
    public $log_id   = 0;
    public $dtb      = 0;
    public $dte      = 0;
    public $dst      = 0;
    public $rep      = 0;
    public $pts      = null;

    private static $cache = [];
    public static $total  = 0;

    public function __construct($arg = 0, $pnt = null) {
        global $PG;

        if(is_a($arg, 'OrderLog')) {
            $this->log_id = $arg->id;
            $this->pts = new StPolyline();
            $this->rep = $arg->rep_mode;
            //echo "New Line r:{$this->rep} (i:{$this->id}) ";
            if($pnt) {
                //echo $msg->t;
                $this->dtb = $pnt->dt;
                $this->dte = $pnt->dt;
                //$this->pts->append($msg->pos, $dst);
            }
            //echo "\n";
            return;
        }

        if(is_numeric($arg)) {
            if(!$arg) {
                $this->pts = new StPolyline();
                return;
            }
            $rows = $PG->select("SELECT *, ST_AsText(pts) ptst FROM order_log_line WHERE id = $arg");
            if($rows === FALSE) throw new Exception($PG->error);
            $arg = $rows[0];
        }
        if(is_array($arg)) {
            foreach($arg as $k => $v) {
                if($k == 'pts') continue;
                if($k == 'ptst') {
                    $v = new StPolyline($v);
                    $k = 'pts';
                } else {
                    $v = intval($v);
                }
                $this->$k = $v;
            }
        } else {
            $this->pts = new StPolyline();
        }
    }

    public function save() {
        global $PG;
        //echo "LN_save (i:$this->id) pts=" . $this->pts->pointsCount() . ", ";
        if($this->pts->pointsCount() < 2) return true;
        //echo $this->pts->toString() . PHP_EOL;
        $t = new SqlTable('order_log_line', $this, [], 'id', false, $PG);
        return $t->save($this);
    }

    public function addPoint($pnt, $dx) {
        $this->pts->append($pnt->pt);
        $this->dte = $pnt->dt;
        $this->dst += intval($dx);
        //printf("LN(%d:%d:%d)[%d]=%d\n", $this->id, $this->log_id, $this->rep, $this->dte, $this->dst);
    }

    public function count() { return $this->pts->pointsCount(); }

    public static function getList($flt = array(), $ord = '', $lim = '') {
        global $PG;
        self::$total = 0;
        $empty = true;
        //$lines = false;
        $obj = true;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*, ST_AsText(pts) ptst';
        $fld  = '';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = $fld = 'id';
                $obj  = false;
            } elseif($it == 'non_empty') {
                $empty = false;
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
        //$limit = $lim ? "LIMIT $lim" : '';
        //$calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $PG->prepare("SELECT $flds FROM order_log_line $add $order");
        foreach($par as $k => $v) {
            $PG->bind($k, $v);
        }
        $rows = $PG->execute_all();
        self::$total = count($rows);
        foreach($rows as $row) {
            $ret[] = $obj ? new OrderLogLine($row) : ($fld ? intval($row[$fld]) : $row);
        }
        if(!$ret && $fld && !$empty) $ret[] = [-1];
        return $ret;
    }
}