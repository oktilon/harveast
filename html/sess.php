<?php
    @session_start();
    $DS = DIRECTORY_SEPARATOR;
    $config = dirname(dirname(__FILE__)) . $DS . 'base' . $DS . 'config.php';
    $vendor = dirname(dirname(__FILE__)) . $DS . 'vendor' . $DS . 'autoload.php';
    if(!is_file($config)) die('Config!');
    require_once $config;
    define('PATH_ROOT',			dirname(__FILE__));
    define('PATH_INC',			PATH_ROOT . '/include');
	define('PATH_CLAS',			PATH_ROOT . '/classes');

    require_once PATH_INC . '/autoload.php';

    if(!is_file($vendor)) die('Composer!');
    require_once $vendor;

    $DB = new connect_db();
    $PG = connect_db::PostgreSql();

 	if(!$DB->valid() || !$PG->valid()) {
        if(!$DB->valid()) {
            echo "<h1>Ошибка подключения к БД {$DB->db_drv}:{$DB->db_name}</h1><h2>{$DB->error}</h2><pre>";
            print_r($DB->errInfo);
            echo "</pre>";
        }
 	    if(!$PG->valid()) {
            echo "<h1>Ошибка подключения к БД {$PG->db_drv}:{$PG->db_name}</h1><h2>{$PG->error}</h2><pre>";
            print_r($PG->errInfo);
            echo "</pre>";
        }
        die();
    }

    GlobalMethods::initText(dirname(dirname(__FILE__)) . $DS . 'base' . $DS . 'locale.php');

    $infoPrefix = '';

    function InfoPrefix($txt, $add = '') {
        global $infoPrefix;
        $t = $txt;
        if(strpos($txt, '.') !== FALSE) {
            $t = basename($txt, '.php');
        }
        $infoPrefix = "{$t}{$add}: ";
    }

    function Info($txt, $suffix = PHP_EOL) {
        global $infoPrefix;
        $out = $infoPrefix . $txt;
        syslog(LOG_WARNING, $out);
        echo $out . $suffix;
    }