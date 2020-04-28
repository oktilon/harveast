<?php
class CarCache {
    public $id  = 0;
    public $tm  = 0;
    public $dt  = 0;
    public $car = 0;
    public $ctp = 0;

    /** @var CarLog[] */
    public $logs = [];

    public static $api = null;
    public static $error = '';
    public static $debug = [];
    public static $is_debug = false;
    public static $points = 0;
    public $stop = false;

    public static $begH  = 7;
    public static $begM  = 0;
    public static $tmBeg = 420; // min = $begH * 60 + $begM

    public static $maxStep = 14400;  // sec = 4 hour
    public static $maxWait = 604800; // sec = 1 week
    public static $oneDay  = OrderLog::ONE_DAY;  // sec = 1 day

    public static $partialSize = 8;
    public static $oldPointsGap = 3600; // 1 hour

    public function __construct($row) {
        global $DB;
        if(is_array($row)) {
            foreach($row as $key => $val) {
                $this->$key = intval($val);
            }
        }
    }

    public function save() {
        global $DB;
        $DB->prepare("UPDATE gps_devices
                    SET tm = :tm,
                        dt = :dt
                    WHERE gps_id = :id")
            ->bind('tm', $this->tm)
            ->bind('dt', $this->dt)
            ->bind('id', $this->id)
            ->execute();
    }

    public static function getApi() {
        if(self::$api == null) self::$api = new WialonApi();
        return self::$api;
    }

    public static function restartApi() {
        return self::$api->restart();
    }

    public function debug_init() {
        self::$debug = [];
        return GlobalMethods::getMTime();
    }
    public function debug($tm, $msg, $arr = null) {
        $n = GlobalMethods::getMTime() - $tm;
        $c = $arr && is_array($arr) ? count($arr) : 0;
        $a = $c ? "(cnt=$c)" : '';
        self::$debug[] = sprintf("%05d > %s%s\n", $n, $msg, $a);
        return GlobalMethods::getMTime();
    }
    public function debug_time($tm) {
        $nn = GlobalMethods::getMTime();
        $dn = $nn - $tm;
        return [$dn, $nn];
    }

    public static function todayBegining() { return self::dateBegining(); }

    public static function dateBegining($tms = 0) {
        $d = new DateTime();
        if($tms) $d->setTimestamp($tms);
        $d->setTime(self::$begH, self::$begM, 0);
        return intval($d->format('U'));
    }

    public function isGoodCar() {
        if($this->ctp == 0) return false;
        return in_array($this->ctp, CarLog::getGoodList());
    }

    public function checkNewDay() {

        if(!$this->isGoodCar()) return false;
        if(self::$is_debug) echo "good car. Check day. Last day = " . date('Y-m-d H:i', $this->dt) . "\n";

        if($this->dt == 0) {
            $this->dt = self::todayBegining();
            return;
        }

        $delta = time() - $this->dt;

        if(self::$is_debug) echo "Delta=$delta\n";

        if($delta > 86500) { // 1 day + 100 sec

            if(CarLog::getCount($this) == 0) {
                $new = self::todayBegining();
                if(self::$is_debug) echo "Create empty day log for " . date('Y-m-d H:i', $new) . "\n";
                $it = new CarLog($this, $new);
                $it->save();
                $it->getPreviousNote();

                if($it->id > 0) {
                    $this->dt = $new;
                }

            }

        }
    }

    public function calcLog($msg = '', $loop = false) {
        global $DB;
        self::$points = 0;
        if($this->tm == 0) $this->tm = strtotime('today');
        if(self::$is_debug) $tms = self::debug_init();

        $now = time();
        $fin = $now;
        $old = false;
        if(($fin - $this->tm) > self::$maxStep) {
            $fin = $this->tm + self::$maxStep;
            $old = true;
            if(self::$is_debug) self::$debug[] = "old,fin=" . date('Y-m-d H:i:s', $fin);
        }

        $api = self::getApi();
        Info("{$this->id}, c:{$this->car} $msg" .
            ($loop ? '(rst_api)' : '') . ', ' .
            date('Y-m-d H:i:s', $this->tm) . '-' .
            date('Y-m-d H:i:s', $fin)
        );
        if(self::$is_debug) $tms = self::debug($tms, 'getApi');
        $this->stop = false;
        $tm_begin = $this->tm - self::$oldPointsGap;
        $ret = $api->getMessages($this->id, $tm_begin, $fin);
        if(self::$is_debug) $tms = self::debug($tms, "getMessages", $ret);
        if(!$ret) {
            if($api->err) {
                $this->stop = $api->needStop();
                if($api->needRestart() && !$loop) {
                    $ok = self::restartApi();
                    if($ok) {
                        return $this->calcLog($msg, true);
                    }
                }
                $e = $api->myErrorText();
                self::$error = $e . " ({$api->err})";
                if(self::$is_debug) echo "err $e\n";
                return false;
            }
            if($old) {
                $last_msg = $api->getLastMessage($this->id);
                $last_tm = WialonApi::fromWialonTime($last_msg->t);
                if($last_tm <= $this->tm) {
                    self::$error = ' silent';
                    if(self::$is_debug) echo "silent\n";
                    return false;
                }
                $fin = $last_tm + 1;
                if(self::$is_debug) self::$debug[] = "old(repeatGetMessages),fin=" . date('Y-m-d H:i:s', $fin);
                $ret = $api->getMessages($this->id, $tm_begin, $fin);
                if(!$ret) {
                    self::$error = 'no msgs (' .
                            date('Y-m-d H:i:s', $this->tm) . '-' .
                            date('Y-m-d H:i:s', $fin) . ')';
                    if(($fin - $this->tm) > self::$maxWait) {
                        $this->tm += self::$oneDay;
                        self::$error .= ' skip day';
                        if(self::$is_debug) echo "skip day\n";
                    }
                    return false;
                }
                $msg = $ret[0];
                $this->tm = WialonApi::fromWialonTime($msg->t) - 1;
                if(($fin - $this->tm) > self::$maxStep) {
                    $fin = $this->tm + self::$maxStep;
                }
                $ret = $api->getMessages($this->id, $tm_begin, $fin);
                if(!is_array($ret)) $ret = [];
            }
        }
        self::$points = count($ret);
        CarLogPoint::readCache($this->id, $tm_begin, $fin);

        echo self::$points . "pts, \033[0;34m" . date('Y-m-d H:i:s', $tm_begin) . "\033[0m > \033[0;32m" . date('Y-m-d H:i:s', $this->tm) . "\033[0m - \033[0;32m" . date('Y-m-d H:i:s', $fin) . "\033[0m: ";
        $pl = null;
        $pm = null;
        $ix = 0;
        $times = [];
        foreach ($ret as $msg) {
            $ttm = WialonApi::fromWialonTime($msg->t);
            if($ttm <= $this->tm) {
                CarLogPoint::calcPoint($msg, $this->id);
                continue;
            }
            $log = $this->getLog($msg);
            if(self::$is_debug) {
                list($dt, $tms) = self::debug_time($tms);
                $times[] = $dt;
            }
            if($log->id == 0) {
                echo "\033[1;31m(l:{$log->id}={$DB->error})\033[0m\n";
                self::$error = $DB->error ? $DB->error : 'Save car_log error';
                // calc geoPoint at all
                CarLogPoint::calcPoint($msg, $this->id);
                return false;
            }
            if($pl == null || $pl->id != $log->id) {
                echo "\033[1;32m(l:{$log->id})\033[0m";
            }
            $pl = $log;
            $ok = $log->append($msg, $pm, $this->id);
            if(CarLog::$mark) $ix = 0;
            if($ok) {
                echo CarLogItem::$lastGeo > 0 ? 'o' : '.';
                $this->tm = $ttm;
            } else {
                echo "-";
            }
            $ix++;
            if($ix && $ix % 100 == 0) {
                echo "\n\t";
                $ix = 0;
            }
            $pm = $msg;
        }
        $cntLogs = count($this->logs);
        if(self::$is_debug) {
            $msg = 'finMessages [' . implode(',', $times) . ']';
            $tms = self::debug($tms, $msg);
        }
        echo " save logs (cnt:$cntLogs)";
        $max = 0;
        foreach($this->logs as $log) {
            $log->save();
            $max = max($max, $log->getZeroTms());
        }
        if($this->dt < $max && $this->dt) {
            $this->dt = $max;
            if(self::$is_debug) echo "set dt by log = " . date('Y-m-d H:i', $max) . PHP_EOL;
        }
        echo "\n";
        if(self::$is_debug) $tms = self::debug($tms, "saveLogs", $this->logs);
        $this->tm++;
        return true;
    }

    public function setComplete() {
        CarLog::setComplete($this);
    }


    public function getLog(WialonMessage $msg) {
        if(self::$is_debug) $tms = GlobalMethods::getMTime();
        $ttm = WialonApi::fromWialonTime($msg->t);
        foreach ($this->logs as $log) {
            if($log->sameDate($ttm)) return $log;
        }
        if(self::$is_debug) $tms = self::debug($tms, "enumLogs", $this->logs);

        $tm = CarLog::getDateFromTms($ttm);
        $dt = $tm->format('Y-m-d');
        $lst = CarLog::getList(['all', ['gps = :g', 'g', $this->id], ['dt = :d', 'd', $dt]]);
        if($lst) {
            $ret = $lst[0];
            if(self::$is_debug) $tms = self::debug($tms, "findLog={$ret->id}", $lst);
        } else {
            $ret = new CarLog($this, $ttm);

            if(self::$is_debug) $tms = self::debug($tms, "createLog");

            $ret->save();
            $ret->getPreviousNote();

            if(self::$is_debug) $tms = self::debug($tms, "saveLog");
        }
        if($ret->id) $this->logs[] = $ret;
        return $ret;
    }

    /**
     * Get Cars Cache
     *
     * @param array $devs  only list
     * @param int $part partial load
     *
     * @return CarCache[]
     */
    public static function getCache($devs_list = '', $part = 0) {
        global $DB;
        $ret = [];

        $add = $devs_list ? "AND d.id IN({$devs_list}) " : '';

        if($part > 0) {
            $div = $part - 1;
            $del = self::$partialSize;
            $add .= "AND d.id % {$del} = $div ";
        }

        $DB->prepare("SELECT d.gps_id id
                        , d.tm
                        , d.dt
                        , IFNULL(c.id, 0) car
                        , IFNULL(c.ts_type, 0) ctp
                    FROM gps_devices d
                    LEFT JOIN gps_carlist c ON c.device = d.id
                    WHERE d.gps_id > 0 $add
                    GROUP BY d.gps_id");
        $rows = $DB->execute_all();
        foreach ($rows as $row) $ret[] = new CarCache($row);
        return $ret;
    }
}