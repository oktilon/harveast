<?php
class User {
    public $id = 0;
    public $login = 'guest';
    public $password = 'password';
    public $inn = '';
    public $last_name = '';
    public $first_name = '';
    public $middle_name = '';
    public $email = '';
    public $phone = '';
    public $gphone = '';
    public $date_create = null;
    public $last_logon_time = null;
    public $state = 0;
    public $kod1c = '';
    public $author = 0;
    public $address = '';
    public $lette_reg = 0;
    public $flags = 0;
    public $theme = '';
    public $snd_lang = '';
    public $telegram_id = 0;
    public $upd = null;

    public $firms = [];
    public $all_firms = false;
    public $clusters = [];
    public $all_clusters = false;
    public $rights = [];

    private static $cache = [];
    public static $total = 0;
    public static $error = '';

    public static $skipFields= [
        'upd',
        'firms',
        'all_firms',
        'clusters',
        'all_clusters',
        'rights'
    ];


    const LOGIN_ERROR_MESSAGE = 'Помилка логіна або пароля!';
    const PASSWORD_EMPTY      = 'password';

    const FLAG_FRP      = 0x0001; // FINANCIAL_RESPONSIBLE_PERSON
    const FLAG_TMP_PWD  = 0x0002; // Password is temporary

    public function __construct($arg = 0) {
        $this->date_create = new DateTime();
        $this->last_logon_time = new DateTime();
        $this->upd = new DateTime();
        $readAcl = true;
        if(is_numeric($arg)) {
            $id = intval($arg);
            if($id == 0) return;
            $arg = self::getByCondition($id);
        }
        if(is_array($arg) || is_object($arg)) {
            foreach($arg as $key => $val) {
                if($key == 'firms') $readAcl = false;
                $this->$key = self::getProperty($key, $val);
            }
            if($readAcl) $this->readAcl();
        }
    }

    public static function getProperty($k, $v) {
        switch($k) {
            case 'date_create':
            case 'last_logon_time':
            case 'upd': return new DateTime($v);
            case 'telegram_id':
            case 'lette_reg':
            case 'author':
            case 'flags':
            case 'state':
            case 'id': return intval($v);
        }
        return $v;
    }

    public function readAcl() {
        global $DB;
        $rows = $DB->select("SELECT right_id FROM spr_users_rights WHERE user_id = {$this->id}");
        foreach($rows as $row) {
            $this->rights[] = intval($row['right_id']);
        }

        // $rows = $DB->select("SELECT cluster_id FROM spr_users_clusters WHERE user_id = {$this->id}");
        // foreach($rows as $row) {
        //     $id = intval($row['cluster_id']);
        //     if($id == 0) {
        //         $this->all_clusters = true;
        //         if($leaveAllFirms) $this->clusters[] = $id;
        //     } else {
        //         $this->clusters[] = $id;
        //     }
        // }

        // $rows = $DB->select("SELECT firm_id FROM spr_users_firms WHERE user_id = {$this->id}");
        // foreach($rows as $row) {
        //     $id = intval($row['firm_id']);
        //     if($id == 0) {
        //         $this->all_firms = true;
        //         if($leaveAllFirms) $this->firms[] = $id;
        //     } else {
        //         $this->firms[] = $id;
        //     }
        // }
    }

    public function delete() {
        global $DB;

        $r = $DB->prepare("DELETE FROM spr_users_rights WHERE user_id = :i")
                ->bind('i', $this->id)
                ->execute();
        // $f = $DB->prepare("DELETE FROM spr_users_firms WHERE user_id = :i")
        //         ->bind('i', $this->id)
        //         ->execute();
        // $c = $DB->prepare("DELETE FROM spr_users_clusters WHERE user_id = :i")
        //         ->bind('i', $this->id)
        //         ->execute();
        $u = $DB->prepare("DELETE FROM spr_users WHERE id = :i")
                ->bind('i', $this->id)
                ->execute();
        return $u;
    }

    public function isLocked() {
        return $this->state == 1;
    }

    private static function getByCondition($value = 0, $field = 'id', $oper = '=') {
        global $DB;
        $DB->prepare("SELECT * FROM spr_users WHERE $field $oper :arg LIMIT 1")
           ->bind('arg', $value);
        return $DB->execute_row();
    }

    public static function findByText($txt, $limit = 0, $implode = false) {
        $flt = [];
        $arr = explode(' ', $txt);
        $cap = ['last_name', 'first_name', 'middle_name'];
        $cnt = count($arr);
        if($cnt == 1) {
            $cap = ["CONCAT(last_name, first_name, middle_name)"];
        }
        foreach($arr as $i=>$t) {
            $fld = $i < 3 ? $cap[$i] : $cap[0];
            $flt[] =  ["$fld LIKE :n$i", "n$i", "%$t%"];
        }
        $ord = $implode ? 'id' : 'name';
        if($implode) $flt[] = 'id_only';
        //array_merge(PageManager::$dbg, $flt);
        $ret = self::getList($flt, $ord, $limit);
        if($implode) {
            $ret = implode(',', $ret);
        }
        return $ret;
    }

    public static function byTelegram($tgid) {
        $row = self::getByCondition($tgid, 'telegram_id');
        if(!$row) $row = 0;
        $ret = new User($row);
        if($ret->id == 0) $ret->telegram_id = $tgid;
        return $ret;
    }

    public static function byLogin($login) {
        $row = self::getByCondition($login, 'login');
        if(!$row) $row = 0;
        $ret = new User($row);
        return $ret;
    }

    public static function byRight($rights) {
        global $DB;
        $ret = [];
        $lst = is_array($rights) ? implode(',', $rights) : $rights;
        $ids = $DB->prepare("SELECT user_id
                        FROM spr_users_rights
                        WHERE rght_id IN($lst)")
                ->execute_all();
        foreach($ids as $idr) {
            $ret[] = new User($idr['user_id']);
        }
        return $ret;
    }

    public static function getUsersUploadFolder($root = true) {
        return ($root ? PATH_ROOT : '') . DIRECTORY_SEPARATOR .
            'images' . DIRECTORY_SEPARATOR .
            'upload' . DIRECTORY_SEPARATOR;
    }

    public function getUserUploadFolder($file = '', $root = true) {
        return self::getUsersUploadFolder($root) .
            $this->id . DIRECTORY_SEPARATOR . $file;
    }

    public function initUploadFolder() {
        $upl  = self::getUsersUploadFolder();
        $path = $this->getUserUploadFolder();
        if(is_dir($upl) || is_writable($upl)) {
            if(!is_dir($path)) {
                mkdir($path);
            }
            if(is_dir($path) && is_writeable($path)) {
                return '';
            }
            return 'User folder ' . (is_dir($path) ? 'error' : 'absent');
        }
        return 'Upload folder ' . (is_dir($upl) ? 'error' : 'absent')  ;
    }

    public function save() {
        $t = new SqlTable('spr_users', $this, $skip);
        return $t->save($this);
    }

    public function update() {
        foreach($this as $key => $val) {
            if(in_array($key, self::$skipFields)) continue;
            if(!isset($_POST[$key])) continue;
            if(in_array($key, ['firms', 'rights', 'clusters'])) {
                $val = [];
                $txt = $_POST[$key];
                if($txt != '') {
                    $arr = explode(',', $txt);
                    foreach($arr as $s) {
                        $val[] = intval($s);
                    }
                }
            } else {
                $val = $_POST[$key];
            }
            $this->$key = self::getProperty($key, $val);
        }
    }

    public function saveTables($tbl, $field, $ids) {
        global $DB;
        $DB->prepare("DELETE FROM $tbl WHERE user_id = :i")
            ->bind('i', $this->id)
            ->execute();

        $ret = true;

        if(count($ids) > 0) {
            $val = [];
            $par = [];
            GlobalMethods::debug("saveTables($tbl, $field)", $ids);
            foreach($ids as $i => $v) {
                $val[] = "(:u, :v{$i})";
                $par["v{$i}"] = $v;
            }
            $val = implode(',', $val);
            $DB->prepare("INSERT INTO $tbl (user_id, $field) VALUES $val")
                ->bind('u', $this->id);
            foreach($par as $i => $v) $DB->bind($i, $v);
            $ret = $DB->execute();
        }
        return $ret ? 'ok' : $DB->error;
    }

    public function saveRights()   { return $this->saveTables('spr_users_rights',   'rght_id',    $this->rights); }
    public function saveFirms()    { return $this->saveTables('spr_users_firms',    'firm_id',    $this->firms); }
    public function saveClusters() { return $this->saveTables('spr_users_clusters', 'cluster_id', $this->clusters); }

    public function getSimple($login = false) {
        $ret = new stdClass();
        $ret->id = $this->id;
        $ret->name = $this->fio();
        $ret->phone = $this->phone;
        $ret->chat = $this->telegram_id;
        if($login) $ret->login = $this->login;
        return $ret;
    }

    public function getJson() {
        $ret = new stdClass();
        foreach($this as $k => $v) {
            if(is_array($v)) $v = implode(',', $v);
            if(is_a($v, 'DateTime')) $v = intval($v->format('U'));
            if($k=='password') $v = ($v != 'password');
            $ret->$k = $v;
        }
        return $ret;
    }

    public function full() { return $this->fio(false); }
    public function fi() { return $this->fio(false, true); }

    public function fio($short = true, $fi = false) {
        $l = $this->last_name;
        $f = $this->first_name  ? ($short ? (mb_substr($this->first_name, 0, 1)  . '.') : $this->first_name)  : '';
        $m = $this->middle_name ? ($short ? (mb_substr($this->middle_name, 0, 1) . '.') : $this->middle_name) : '';
        if($f)  $l = $l ? "$l $f" : "$f";
        if($m && !$fi)  $l = $l ? "$l $m" : "$m";
        return $l;
    }

    public static function getCurrentUser() {
        $cache = isset($_SESSION['user']) ? json_decode($_SESSION['user']) : 0;
        $u = new User($cache);
        return $u;
    }

    public function userHash($pwd) {
        return md5(sprintf(USER_HASH_FMT, $this->login, $pwd));
    }

    public function checkHash($pwd) {
        $pw = base64_decode($pwd);
        return $this->password == $this->userHash($pw);
    }

    public function setPassword($pwd, $tmp = false) {
        global $DB;

        $this->password = $this->userHash($pwd);
        if($tmp) {
            $this->flags |= self::FLAG_TMP_PWD;
        } else {
            $this->flags &= ~self::FLAG_TMP_PWD;
        }
        $ret = $DB->prepare("UPDATE spr_users SET password = :p, flags = :f WHERE id = :i")
                ->bind('p', $this->password)
                ->bind('f', $this->flags)
                ->bind('i', $this->id)
                ->execute();
        if($ret && !$tmp) $this->setSession();
        return $ret;
    }

    public function resetPassword($url) {
        global $PM, $DB;
        $pwd = self::generatePassword(8);
        $ok = $this->setPassword($pwd, true);
        syslog(LOG_ERR, "Password reset for {$this->id} = $pwd, [{$DB->error}]");
        if(!$ok) {
            return 'db_error ' . $DB->error;
        }
        $fio  = $this->fi();
        $data = [
            'fio' => $fio,
            'lnk' => $url,
            'pwd' => $pwd
        ];
        // $q = EmailTemplate::sendTemplate($this, 'reset-password', $data);
        // return $q ? 'ok' : ('error ' . EmailTemplate::$error);
    }

    public function setSession() {
        $_SESSION['user'] = $this->getJson();
    }

    public function hasValidPassword() {
        return $this->password != self::PASSWORD_EMPTY;
    }

    public function hasTemporaryPassword() {
        return (($this->flags & self::FLAG_TMP_PWD) > 0) ||
                $this->password != self::PASSWORD_EMPTY;
    }

    public function hasRights() {
        $ret = true;
        $all = func_get_args();
        if(empty($all)) $all[] = 1;
        foreach ($all as $right) {
            if(!in_array($right, $this->rights)) $ret = false;
        }
        return $ret;
    }

    public static function loginUser($usr, $pwd, $pm = null) {
        global $DB;
        $row = $DB->prepare("SELECT * FROM spr_users WHERE login = :login")
                    ->bind('login', $usr)
                    ->execute_row();
        if(!$row) {
            if($DB->error != '') return $DB->error;
            return self::LOGIN_ERROR_MESSAGE;
        }
        $u = new User($row);
        if($u->isLocked()) {
            return 'Доступ заблоковано';
        }
        if(!$u->hasValidPassword()) {
            $u->setSession();
            if($pm != null) {
                $pm->set('url', '/newpwd');
                return 'ok';
            } else {
                return 'pwd';
            }
        }
        if($u->checkHash($pwd)) {
            $u->setSession();
            return 'ok';
        }
        return self::LOGIN_ERROR_MESSAGE;
    }

    public static function generatePassword($length = 8) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $len   = strlen($chars);
        return substr(str_shuffle($chars), 0, min($length, $len));
    }

    public static function loginUserApp($usr, $pwd) {
        global $DB;
        self::$error = 'wrong';
        $row = $DB->prepare("SELECT * FROM spr_users WHERE login = :login")
                    ->bind('login', $usr)
                    ->execute_row();
        if(!$row) {
            self::$error = 'bad';
            $row = 0;
        }
        $u = new User($row);
        if($u->checkHash(base64_encode($pwd))) {
            self::$error = 'ok';
        }
        if($u->isLocked()) {
            self::$error = 'locked';
        }
        $u->setSession();
        return $u;
    }

    /**
     * Gets user by id using cache
     *
     * @param int $id User id
     * @return User
     */
    public static function get($id) {
        if(!isset(self::$cache[$id])) {
            self::$cache[$id] = new User($id);
        }
        return self::$cache[$id];
    }

    public static function getList($flt = [], $ord = 'id', $lim = '') {
        global $DB;
        self::$total = 0;
        $leaveAllFirms = false;
        $obj = true;
        $int = false;
        $ret = [];
        $par = [];
        $add = [];
        $flds = '*';
        $fld  = '';
        foreach($flt as $it) {
            if($it == 'id_only') {
                $flds = $fld = 'id';
                $obj  = false;
                $int  = true;
            } elseif($it == 'leaveAllFirms') {
                $leaveAllFirms = true;
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
        $DB->prepare("SELECT $calc $flds FROM spr_users $add $order $limit");
        foreach($par as $k => $v) {
            $DB->bind($k, $v);
        }
        $rows = $DB->execute_all();
        self::$total = count($rows);
        if($calc) {
            self::$total = intval($DB->select_scalar("SELECT FOUND_ROWS()"));
        }
        foreach($rows as $row) {
            $ret[] = $obj ? new User($row, $leaveAllFirms) : ($int ? intval($row[$fld]) : $row);
        }
        return $ret;
    }
}


