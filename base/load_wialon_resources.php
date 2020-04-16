<?php
require_once dirname(__DIR__) . '/html/sess.php';
InfoPrefix(__FILE__);
$init = time();
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$flt = '!';
$dbg = false;
if($args) {
    $cmd = array_shift($args);
    if($cmd == '-f') {
        if($args) {
            $flt = array_shift($args);
            $m = [];
            if(preg_match('/^[\'\"](.+)[\'\"]$/', $flt, $m)) {
                $flt = $m[1];
            }
            $dbg = true;
            GeoFence::$debug = true;
            Field::$debug = true;
        }
    }
}

Info('Started' . ($dbg ? " with filter {$flt}" : ''));

$w = new WialonApi();

$all = [];
$ret = $w->listResources();
if($ret) {
    $beg = '';
    foreach($ret as $res) {
        if(is_object($res) && property_exists($res, 'id')) {
            $q = $DB->prepare("SELECT name FROM wialon_resources WHERE id = :i")
                    ->bind('i', $res->id)
                    ->execute_scalar();
            if(!$q) {
                $q = $DB->prepare("INSERT INTO wialon_resources (id, name) VALUES (:i, :n)")
                        ->bind('i', $res->id)
                        ->bind('n', $res->nm)
                        ->execute();
                echo "{$beg}{$res->id} save " . ($q ? "\033[1;32mok\033[0m" : "\033[1;31m{$DB->error}\033[0m");
            } else {
                if($q == $res->nm) {
                    echo "{$beg}{$res->id} \033[1;36mexists\033[0m";
                } else {
                    $q = $DB->prepare("UPDATE wialon_resources SET name = :n WHERE id = :i")
                            ->bind('n', $res->nm)
                            ->bind('i', $res->id)
                            ->execute();
                    echo "{$beg}{$res->id} updated " . ($q ? "\033[1;32mok\033[0m" : "\033[1;31m{$DB->error}\033[0m");
                }
            }
            $beg = ', ';
            $all[$res->id] = $res->nm;
        }
    }
    echo "\n";
}
$cnt = is_array($ret) ? count($ret) : 0;
Info("Resources count $cnt");
echo("New \033[1;34ml\033[0mine, \033[1;34mp\033[0moly, \033[1;34mc\033[0mircle, \033[1;32mF\033[0mield\n");
echo("Old \033[0;34ml\033[0mine, \033[0;34mp\033[0moly, \033[0;34mc\033[0mircle, \033[0;32mF\033[0mield\n");
echo("Field \033[0;31mAbsent\033[0m, \033[0;35mBad\033[0m, \033[0;33mAbsent+Bad\033[0m\n");

$tot = 0;
$tot_del = 0;
foreach($all as $res_id => $nm) {
    echo "Get $nm [$res_id] :\n";
    $cnt = 0;
    $del = [];
    $err = false;
    $ret = $w->searchGeofences($flt, $res_id);

    $cii = is_object($ret) ? count(get_object_vars($ret)) : 0;
    if($cii > 0 && !$dbg) {
        $del = GeoFence::beforeLoad($res_id);
    }
    if(!is_object($ret) && !is_array($ret)) continue;
    foreach($ret as $ix => $geo) {
        $lst = $w->getGeofenceData($res_id, $geo->id);
        if($lst) {
            if(is_array($lst)) {
                $geox = $lst[0];
                if($dbg) {
                    echo json_encode($geox, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                }
                $gf = GeoFence::create($res_id, $geox);
                $new = $gf->id == 0;
                $q = $gf->save();
                if($q) {
                    Changes::writeFromCache('gps_geofence', $gf);
                    $ix = array_search($gf->id, $del);
                    if($ix !== FALSE) array_splice($del, $ix, 1);
                }
                $ch = '+';
                $br = $new ? '1' : '0';
                switch($gf->t) {
                    case GeoFence::TYPE_LINE: $ch = "\033[{$br};34ml\033[0m"; break;
                    case GeoFence::TYPE_POLYGON: $ch = "\033[{$br};34mp\033[0m"; break;
                    case GeoFence::TYPE_CIRCLE: $ch = "\033[{$br};34mc\033[0m"; break;
                }
                if($gf->isField()) {
                    $clr = $gf->fld == 0 ? 1 : 2;
                    $clr = $gf->isInvalid() ? 5 : $clr;
                    if($gf->fld == 0 && $gf->isInvalid()) {
                        $clr = 3;
                    }
                    $ch = "\033[{$br};3{$clr}mF\033[0m";
                }

                echo $q ? $ch : "\033[1;31m{$DB->error} \033[1;36m{$gf->ar}\033[0m\n";
                if($dbg) {
                    echo 'Field debug : ' . json_encode(Field::$debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    echo 'GeoFence debug : ' . json_encode(GeoFence::$debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                }
            } else {
                $err = true;
                echo "\n";
                Info("$nm:[$geo->id] Wialon Err : " . WialonApi::$m_err . ', ' . WialonApi::errorText(WialonApi::$m_err));
                Info("Wialon Ret : " . WialonApi::$m_res);
                break;
            }
        } else {
            echo "\033[1;31mX\033[0m";
        }
        $cnt++;
        if($cnt % 50 == 0) echo PHP_EOL;
    }

    if(!$err && count($del) && !$dbg) {
        $tot_del += GeoFence::afterLoad($del);
    }

    echo "\n\n\n";
    $tot += $cnt;
    $del = [];
    if($err) break;
}

$loaded = implode(',', array_keys($all));
if($loaded) {
    $missed = $DB->prepare("SELECT id, name FROM wialon_resources WHERE id NOT IN($loaded)")
                ->execute_all();
    foreach($missed as $m_row) {
        $ids = GeoFence::getList([
            'id_only',
            ['res_id = :r', 'r', intval($m_row['id'])],
            'del = 0',
        ], 'id');
        $cnt = count($ids);
        if($cnt) {
            $ids = implode(',', $ids);
            $ok_m = $DB->prepare("UPDATE gps_geofence SET del=1 WHERE id IN($ids)")->execute() ? 'ok' : $DB->error;
            $ok_p = $PG->prepare("UPDATE geofences SET del=1 WHERE _id IN($ids)")->execute() ? 'ok' : $PG->error;
            Info("Mark as obsolete {$cnt} geofences of resource {$m_row['name']}...MySql:{$ok_m}...PGSql:{$ok_p}");
        }
    }
}


$pref = $err ? "Error " : '';
Info("{$pref}Total $tot rows, Deleted $tot_del, Spent " . (time()-$init));