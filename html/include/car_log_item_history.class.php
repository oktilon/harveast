<?php
class CarLogItemHistory {
    public $item_id = 0;
    public $dt = null;
    public $spd = 0;
    public $user = null;
    public $reason = 0;
    public $note = '';

    public static $total = 0;

    public function __construct($arg, $spd = 0) {
        global $DB, $PM;
        if(is_object($arg) && is_a($arg, 'CarLogItem')) {
            $this->item_id = $arg->id;
            $this->dt = $spd ? new DateTime() : $arg->dt_last;
            $this->spd = $spd;
            $this->user = $PM->user;
            $this->reason = $spd ? 0 : $arg->reason;
            $this->note = $spd ? $arg->note_spd : $arg->note;
            return;
        }
        if(is_array($arg)) {
            foreach($arg as $key => $val) $this->$key = self::getProperty($key, $val);
        } else {
            $this->dt = new DateTime('2000-01-01');
            $this->user = User::get(0);
        }
    }

    public static function getProperty($k, $v) {
        switch($k) {
            case 'note': return $v;
            case 'dt': return new DateTime($v);
            case 'user': return User::get($v);
        }
        return intval($v);
    }

    public static function create(CarLogItem $it, $spd = 0) {
        $cli = new CarLogItemHistory($it, $spd);
        return $cli->save();
    }

    public function save() {
        $t = new SqlTable('gps_car_log_item_history', $this);
        return $t->save($this, true);
    }

    public function getSimple() {
        $ret = new stdClass();
        foreach($arg as $key => $val) {
            $k = substr($key, 0, 1);
            if(is_a($val, 'DateTime')) $val = $val->format('Y-m-d H:i:s');
            if(is_a($val, 'User')) $val = $val->getSimple();
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

    public static function getLast($iid, $spd) {
        global $DB;
        $r = $DB->prepare("SELECT *
                    FROM gps_car_log_item_history
                    WHERE item_id = :i
                        AND spd = :s
                    ORDER BY dt DESC
                    LIMIT 1")
                ->bind('i', $iid)
                ->bind('s', $spd)
                ->execute_row();
        return new CarLogItemHistory($r);
    }

    public static function getUsers() {
        global $DB;
        $q = $DB->prepare("SELECT DISTINCT user
                    FROM gps_car_log_item_history
                    ORDER BY user")
                ->execute_all();
        $ret = [];
        foreach ($q as $r) {
            $u = User::get($r['user']);
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
        $DB->prepare("SELECT $calc * FROM gps_car_log_item_history $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = new CarLogItemHistory($row);
        }
        return $ret;
    }
}