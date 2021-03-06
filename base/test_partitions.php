<?php
    require_once dirname(__DIR__) . '/html/sess.php';
    InfoPrefix(__FILE__);
    // $init = time();
    // $_REQUEST['obj'] = '{"p":1}';

    $PGA = connect_db::PostgreSql(null, PG_ADMIN, PG_ADMIN_PWD);
    if(!$PGA->valid()) die('PG Error ' . $PGA->error);

    $deep = '+1 month';

    $args = [];
    if($argc > 1) {
        $args = array_slice($argv, 1);
    }

    $loop = true;
    $init = '';
    $cmd = '';
    $m = [];
    while($args) {
        $arg = array_shift($args);
        if(preg_match('/^\-(\w)$/', $arg, $m)) {
            $cmd = $m[1];
            if($cmd == 'd') $deep = '';
        } else {
            if($cmd == 'd') {
                if(substr($arg, 0, 1) == 'm') $arg = '-' . substr($arg, 1);
                $deep = str_replace('m', ' month', $arg);
                Info("deep={$deep}");
                $cmd = '';
            }
            if($cmd == 'b') {
                if(preg_match('/^\d{4}-\d\d$/', $arg)) {
                    $deep = '';
                    $init = $arg . '-01';
                }
            }
        }
    }

    while($loop) {

        $week = $init ? new DateTime($init) : new DateTime();
        if($deep) $week->modify($deep);
        $part = $week->format('Y_m');

        $week->modify('first day of this month')
            ->modify('today');
        $tm_beg = intval($week->format('U'));
        $dt_beg = $week->format('Y-m-d H:i:s');

        $week->modify('+1 month')
            ->modify('first day of this month')
            ->modify('today')
            ->modify('-1 second');
        $tm_end = intval($week->format('U'));
        $dt_end = $week->format('Y-m-d H:i:s');

        $tables = ['gps_points', 'order_area', 'order_log_line'];

        foreach($tables as $tbl) {
            $partition = "{$tbl}_{$part}";

            $q = $PGA->prepare("SELECT 1
                            FROM pg_catalog.pg_class
                            WHERE relname = :part")
                    ->bind('part', $partition, PDO::PARAM_STR)
                    ->execute_scalar();

            if(!$q) {
                $ok = $PGA->prepare("CREATE TABLE $partition
                            PARTITION OF {$tbl} FOR
                            VALUES FROM ($tm_beg) TO ($tm_end)")
                        ->execute();
                $add = $ok ? 'OK' : $PGA->error;

                if($ok) {
                    $ok = $PGA->prepare("GRANT ALL ON TABLE $partition TO php")->execute();
                    $add = ', grant ' . ($ok ? 'OK' : $PGA->error);
                }

                Info("Create partition {$partition} {$dt_beg}-{$dt_end} ({$tm_beg}-{$tm_end}): {$add}");
            }
        }

        $loop = false;
    }