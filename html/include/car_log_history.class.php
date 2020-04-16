<?php
class CarLogHistory {
    public $log_id = 0;
    public $dt = null;
    public $user = null;
    public $note = '';

    public static $total = 0;

    public function __construct($arg) {
        global $DB, $PM;
        if(is_object($arg) && is_a($arg, 'CarLog')) {
            $this->log_id = $arg->id;
            $this->dt = new DateTime();
            $this->user = $PM->user;
            $this->note = $arg->note;
            return;
        }
        if(is_array($arg)) {
            foreach($arg as $key => $val) $this->$key = self::getProperty($key, $val);
        } else {
            $this->dt = new DateTime('2000-01-01');
            $this->user = CUser::get(0);
        }
    }

    public static function getProperty($k, $v) {
        switch($k) {
            case 'note': return $v;
            case 'dt': return new DateTime($v);
            case 'user': return CUser::get($v);
        }
        return intval($v);
    }

    public static function create(CarLog $log, $copy_user = 0) {
        global $DB;
        $clh = new CarLogHistory($log);
        if($copy_user) {
            $uid = intval($DB->prepare("SELECT user
                                    FROM gps_car_log_history
                                    WHERE log_id = {$copy_user}
                                    ORDER BY dt DESC
                                    LIMIT 1")->execute_scalar());
            if($uid) $clh->user = new CUser($uid);
        }
        return $clh->save();
    }

    public function save() {
        $t = new SqlTable('gps_car_log_history', $this);
        return $t->save($this, true);
    }

    public function getSimple() {
        $ret = new stdClass();
        foreach($this as $key => $val) {
            $k = substr($key, 0, 1);
            if(is_a($val, 'DateTime')) $val = $val->format('Y-m-d H:i:s');
            if(is_a($val, 'CUser')) $val = $val->getSimple();
            $ret->$k = $val;
        }
        return $ret;
    }

    public function tms() {
        if($this->dt->format('Y') < 2001) return 0;
        return intval($this->dt->format('U'));
    }

    public function jsonArray() {
        return [
            $this->note,
            $this->user->id,
            $this->tms()
        ];
    }

    public static function getLast($lid) {
        global $DB;
        $r = $DB->prepare("SELECT *
                    FROM gps_car_log_history
                    WHERE log_id = :i
                    ORDER BY dt DESC
                    LIMIT 1")
                ->bind('i', $lid)
                ->execute_row();
        return new CarLogHistory($r);
    }

    public static function getUsers($exclude) {
        global $DB;
        $excl = [];
        foreach ($exclude as $it) { $excl[] = $it['i']; }
        $wh = count($excl) ? ('WHERE user NOT IN(' . implode(',', $excl) . ')') : '';
        $q = $DB->prepare("SELECT DISTINCT user
                    FROM gps_car_log_history $wh
                    ORDER BY user")
                ->execute_all();
        $ret = [];
        foreach ($q as $r) {
            $u = CUser::get($r['user']);
            $ret[] = [
                'i' => $u->id,
                'n' => $u->fio()
            ];
        }
        return $ret;
    }

    public static function getList($flt = [], $ord = 'dt DESC', $lim = '') {
        global $DB;
        self::$total = 0;
        $ret = [];
        $par = [];
        $add = [];
        foreach($flt as $it) {
            if(is_array($it)) {
                $cnd = array_shift($it);
                if($cnd) $add[] = $cnd;
                $par[$it[0]] = $it[1];
            } else {
                $add[] = $it;
            }
        }
        $add = $add ? ('WHERE ' . implode(' AND ', $add)) : '';
        $order = $ord ? "ORDER BY $ord" : '';
        $limit = $lim ? "LIMIT $lim" : '';
        $calc  = $lim ? "SQL_CALC_FOUND_ROWS" : '';
        $DB->prepare("SELECT $calc * FROM gps_car_log_history $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = new CarLogHistory($row);
        }
        return $ret;
    }
}