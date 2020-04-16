<?php
class GlobalMethods {
    public static $dbg = [];
    public $debug = '';
    public static $pidFile = '';

    public static function debug($obj, $name = '') {
        $v = $obj;
        $v = json_encode($obj, JSON_UNESCAPED_UNICODE);
        if($name) $v = "$name=$v";
        self::$dbg[] = $v;
    }

    public static function popDebug() {
        $ret = self::$dbg;
        self::$dbg = [];
        return $ret;
    }

    public static function getMTime() {
        return intval(microtime(true) * 1000);
    }

    public static function pidLock($lckFile = 'alert.loc', $skip = 3600, $admin_tg = 0, $greps = [], $next = 3600) {
        if(!$lckFile) return true;
        if(file_exists($lckFile)) {
            $crt = filectime($lckFile);
            $fp = fopen($lckFile, 'r');
            if($fp) {
                $pid = intval(trim(fgets($fp)));
                $tms = intval(trim(fgets($fp)));
                fclose($fp);
            } else {
                $pid = 0;
                $tms = 0;
            }
            if($pid && file_exists("/proc/$pid")) {
                $crt = filectime("/proc/$pid");
                $tm = time() - $crt;
                $nx = time() - $tms;
                $dt = date('Y-m-d H:i:s', $crt);
                $txt = '';

                if($greps) {
                    ob_start();
                    $greps[] = '-v Log:';
                    $add = ' | grep ' . implode(' | grep ', $greps);
                    $txt = system('cat /var/log/syslog' . $add . ' | tail -n 1');
                    ob_end_clean();
                    if($txt) $txt = " Log: $txt";
                }

                $msg = "Previous script ($pid) works since $dt ($tm sec.)$txt";

                if($tm > $skip) {
                    if($nx > $next) {
                        $file = basename($lckFile);
                        $id = Telegram::sendMessage($admin_tg, "📝 $file ($pid)\n⏳ works $tm sec.\nℹ️ $txt");
                        $msg .= ", sms_id = $id";
                        if($id) {
                            $fp = fopen($lckFile, 'w');
                            if($fp) {
                                $tms = time();
                                fwrite($fp, "$pid\n$tms");
                                fclose($fp);
                            }
                        }
                    }
                }
                Info($msg);
                return false;
            }
        }
        $pid = getmypid();
        $fp = fopen($lckFile, 'w');
        if($fp) {
            fwrite($fp, "$pid\n0");
            fclose($fp);
            self::$pidFile = $lckFile;
            return true;
        }
        $msg = "$lckFile ($pid) can't create lock";
        $id = Telegram::sendMessage($admin_tg, $msg);
        $msg .= ", sms_id = $id";
        Info($msg);
        return false;
    }

    public static function pidUnLock() {
        if(self::$pidFile && file_exists(self::$pidFile)) unlink(self::$pidFile);
    }
}