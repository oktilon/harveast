<?php
class OrderJointItem {
    public $jnt_id = 0;
    public $log_id = 0;
    public $ord_id = 0;
    public $dt = 0;
    //
    public $beg = null;
    public $end = null;

    const MAX_NEAR_TO = 15; // days

    public function __construct($arg = null, $jnt = 0) {
        if(is_object($arg) && is_a($arg, 'OrderLog')) {
            $this->log_id = $arg->id;
            $this->ord_id = $arg->ord;
            $this->beg = $arg->dt_beg;
            $this->end = $arg->dt_end;
            $this->initDate($arg->dt_beg);
        }
        if(is_array($arg)) {
            foreach($arg as $k => $v) {
                if(isset($this->$k)) $this->$k = intval($v);
            }
            if(isset($arg['dt_beg'])) {
                $this->initDate($arg['dt_beg']);
                $this->beg = new DateTime($arg['dt_beg']);
                $this->end = new DateTime($arg['dt_end']);
            }
        }
        if($this->jnt_id == 0 && $jnt && is_numeric($jnt)) $this->jnt_id = $jnt;
    }

    public function initDate($arg) {
        $hm = 420; // 07:00 = 7*60 min
        if(is_a($arg, 'DateTime')) {
            $hm = 60 * intval($arg->format('H')) + intval($arg->format('i'));
            $this->dt = strtotime($arg->format('Y-m-d 00:00:00'));
        }
        if(is_string($arg)) {
            $hm = 60 * intval(substr($arg, 11, 2)) + intval(substr($arg, 14, 2));
            $this->dt = strtotime(substr($arg, 0, 10) . ' 00:00:00');
        }
        if($hm < 420) {
            $this->dt -= 86400;
        }
    }

    public function nearTo(OrderJointItem $prev) {
        return ($this->dt - $prev->dt) <= (self::MAX_NEAR_TO * OrderLog::ONE_DAY);
    }

    public function equalTo(OrderJointItem $oji) {
        return $this->log_id == $oji->log_id;
    }

    public function ttLog() { $d = date('Y-m-d H:i:s', $this->dt); return "l:{$this->log_id}, d:{$d}"; }
    public function ntLog($o) {
        $dt = $this->dt - $o->dt;
        $dm = self::MAX_NEAR_TO * OrderLog::ONE_DAY;
        $eq = $dt <= $dm ? '<=' : '>';
        $re = $dt <= $dm ? 'near' : 'far';
        return "$dt $eq $dm ($re)\n"; }

    public function getVal($jnt) {
        if($this->jnt_id == 0 && $jnt) $this->jnt_id = $jnt;
        return sprintf("(%d,%d,%d,%d)",
            $this->jnt_id,
            $this->log_id,
            $this->ord_id,
            $this->dt);
    }

    public function getFull($simple = true) {
        $log = OrderLog::get($this->log_id);
        $ord = WorkOrder::get($this->ord_id);

        unset($this->dt);
        unset($this->beg);
        unset($this->end);

        $this->i = $this->log_id;
        $this->o = $this->ord_id;
        $this->f = $ord->flags;
        $this->b = $simple ? $log->dt_beg->format("d.m H:i:s") : $log->dt_beg;
        $this->e = $simple ? $log->dt_end->format("d.m H:i:s") : $log->dt_end;
        $this->a = $log->jnt_area;
        $this->w = $log->getWorkingArea();
        $this->t = $log->getTrack();

        $this->c = $ord->car->getSimple();
        return $this;
    }

    public function closeLog() {
        OrderLog::close($this->log_id);
    }

    public function cloneDt($k) {
        if($this->$k == null) return new DateTime('2000-01-01');
        return new DateTime($this->$k->format('Y-m-d H:i:s'));
    }
    public function cloneBeg() { return $this->cloneDt('beg'); }
    public function cloneEnd() { return $this->cloneDt('end'); }

    public static function save($jnt = 0, $lst = []) {
        global $DB;
        if($jnt == 0) return false;
        $DB->prepare("DELETE FROM gps_joint_items
                    WHERE jnt_id = :j")
            ->bind('j', $jnt)
            ->execute();
        if(!$lst) return true;
        $vals = [];
        foreach($lst as $it) $vals[] = $it->getVal($jnt);
        $vals = implode(',', $vals);
        return $DB->prepare("INSERT INTO gps_joint_items VALUES $vals")
                ->execute();
    }

    public function delete() {
        global $DB, $PG;

        $ord = new WorkOrder($this->ord_id);
        $ord->resetJoint(); // reset flags

        $log = new OrderLog($this->log_id);
        $log->resetJoint(); // reset jnt_area

        $r = $DB->prepare("DELETE FROM gps_joint_items WHERE
                        jnt_id = :j AND ord_id = :o AND log_id = :l")
                    ->bind('j', $this->jnt_id)
                    ->bind('o', $this->ord_id)
                    ->bind('l', $this->log_id)
                    ->execute();
        WorkOrder::$err[] = $r ? 'delJI:ok' : "delJI:{$DB->error}";
        return $r;
    }

    public static function read($val = 0, $fld = 'jnt_id', $ord = '', $full = true) {
        global $DB;
        $ret = [];
        $order = $ord ? "ORDER BY $ord" : '';
        // JOINS for ordering
        $rows = $DB->prepare("SELECT i.* FROM gps_joint_items i
                            LEFT JOIN gps_order_log l ON l.id = i.log_id
                            LEFT JOIN gps_orders o ON o.id = i.ord_id
                            WHERE i.$fld = :v
                            $order")
                ->bind('v', $val)
                ->execute_all();
        foreach ($rows as $row) {
            $oji = new OrderJointItem($row);
            $ret[] = $full ? $oji->getFull() : $oji;
        }
        return $ret;
    }

    public static function readJoint($jnt = 0, $full = true) { return self::read($jnt, 'jnt_id', 'l.dt_beg, o.id', $full); }
    public static function readLog($log = 0) { return self::read($log, 'log_id'); }
    public static function readOrder($ord = 0) { return self::read($ord, 'ord_id'); }

    public static function findJoints($list = [], $fld = 'log_id', $items = true) {
        global $DB;
        $ret = [];
        $lst = [];
        if(is_string($list)) $list = explode(',', $list);
        foreach ($list as $id) {
            $lst[] = intval($id);
        }
        if(!$lst) return $ret;
        $lst = implode(',', $lst);
        $rows = $DB->prepare("SELECT j.* FROM gps_joint_items i
                            LEFT JOIN gps_joint j ON j.id = i.jnt_id
                            LEFT JOIN gps_order_log l ON l.id = i.log_id
                            LEFT JOIN gps_orders o ON o.id = i.ord_id
                            WHERE i.$fld IN ($lst)")
                ->execute_all();
        WorkOrder::$err[] = "findJnt[$fld IN($lst)]=" . ($DB->error ? $DB->error : 'ok');
        foreach ($rows as $row) {
            $ret[] = new OrderJoint($row, $items);
        }
        return $ret;
    }

    public static function compareLog($a, $b) {
        return $a->log_id - $b->log_id;
    }

    public static function compareDt($a, $b) {
        return $a->dt - $b->dt;
    }
}