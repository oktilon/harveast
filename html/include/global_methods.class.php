<?php
class GlobalMethods {
    public static $dbg = [];
    public static $pidFile = '';
    /** @var User */
    public static $user = null;

    const EMPTY_ID = MAX_ID;

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

    public static function getUser() {
        if(self::$user === null)  {
            self::$user = User::getCurrentUser();
        }
        return self::$user;
    }

    public static function assertRead() {
        $u = self::getUser();
        //$u->hasRights()
        return $u->id > 0;
    }

    public static function assertEdit() {
        $u = self::getUser();
        //$u->hasRights()
        return $u->id > 0;
    }

    public static function assertDelete() {
        $u = self::getUser();
        //$u->hasRights()
        return $u->id > 0;
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

    public static function altLoad($m, $q) {
        global $DB, $PG;
        if(!defined('ALT_MODULES')) return $q;
        $filePath = ALT_MODULES . 'mdl' . DIRECTORY_SEPARATOR . $m . '.php';
        if(file_exists($filePath)) {
            $wxObjTMP = isset($_REQUEST['obj']) ? json_decode($_REQUEST['obj']) : false; $p = $wxObjTMP && property_exists($wxObjTMP, 'p') ? $wxObjTMP->p : false;
            require $filePath;
            return [];
        }
        return $q;
    }

    public static function altLoadJs($m, $dt) {
        global $DB, $PG;
        if(!defined('ALT_MODULES')) return;
        $filePath = ALT_MODULES . 'mdl' . DIRECTORY_SEPARATOR . $m . '.js';
        $stylePath = ALT_MODULES . 'mdl' . DIRECTORY_SEPARATOR . $m . '.css';
        $ret = [
            'status' => 'error',
            'data' => '',
            'dt' => 0,
        ];
        $dts = file_exists($stylePath) ? filemtime($stylePath) : 0;
        if(file_exists($filePath)) {
            $ret['status'] = 'ok';
            $dtf = filemtime($filePath);
            if($dtf > $dt || $dts > $dt) {
                $js = file_get_contents($filePath);
                $css = $dts ? file_get_contents($stylePath) : '';
                if($css) $js .= "\nwebix.html.addStyle(`{$css}`);\n";
                $ret['data'] = base64_encode($js);
                $ret['dt'] = max($dtf, $dts);
            }
            echo json_encode($ret);
            die();
        }
    }
}