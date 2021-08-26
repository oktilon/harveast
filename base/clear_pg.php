<?php
    require_once dirname(__DIR__) . '/html/sess.php';

    $PGA = connect_db::PostgreSql(null, PG_ADMIN, PG_ADMIN_PWD);
    if(!$PGA->valid()) die('PG Error ' . $PGA->error);

    $ok = $PGA->prepare("DELETE FROM gps_points where dt < ".time()-60*60*60*24*180)
            ->execute();
    $add = $ok ? 'OK' : $PGA->error;
