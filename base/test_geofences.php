<?php
    require_once dirname(__DIR__) . '/html/sess.php';
    InfoPrefix(__FILE__);

    echo "DB : " . ($DB->valid() ? "{$DB->addr} OK" : "{$DB->error}") . "\n";
    echo "PG : " . ($PG->valid() ? "{$PG->addr} OK" : "{$PG->error}") . "\n";

    $w = new WialonApi();

    $lst = $DB->prepare("SELECT id
                            , res_id
                            , gf_id
                            , mt
                            , del
                        FROM gps_geofence
                        ORDER BY id")
                ->execute_all();

    $pref = '';
    foreach($lst as $row) {
        $id = intval($row['id']);
        $gf = intval($PG->prepare("SELECT COUNT(*)
                        FROM geofences
                        WHERE id = :i")
                ->bind('i', $id)
                ->execute_scalar());
        if(!$gf) {
            $res_id = intval($row['res_id']);
            $gf_id = intval($row['gf_id']);
            $lst = $w->getGeofenceData($res_id, $gf_id);
            if(is_array($lst) && count($lst)) {
                $gf = $lst[0];
                $pl = GeoFence::createPoly($gf);
                $p = $PG->prepare("INSERT INTO geofences(id, own, tp, upd, poly, pr, ar, del, flags, min_lng, max_lng, min_lat, max_lat)
                        VALUES (:i, :o, :t, :u, ST_GeomFromText(:p), :pr, :ar, :d, :f, :min_lng, :max_lng, :min_lat, :max_lat);")
                    ->bind('i', $id)
                    ->bind('o', $res_id)
                    ->bind('t', $gf->t)
                    ->bind('u', $row['mt'])
                    ->bind('p', $pl->toString())
                    ->bind('pr', $gf->pr)
                    ->bind('ar', $gf->ar)
                    ->bind('d', $row['del'])
                    ->bind('f', $gf->f)
                    ->bind('min_lng', $gf->b->min_x)
                    ->bind('max_lng', $gf->b->max_x)
                    ->bind('min_lat', $gf->b->min_y)
                    ->bind('max_lat', $gf->b->max_y)
                    ->execute();
                if($p) {
                    echo "$id.";
                } else {
                    echo "$id [R:$res_id, G:$gf_id] = $PG->error \n";
                }
            }
        }
    }

    Info('Fin');
