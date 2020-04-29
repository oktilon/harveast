<?php
require_once dirname(__DIR__) . '/html/sess.php';
InfoPrefix(__FILE__);
$time = time();
$_REQUEST['obj'] = '{"p":1}';

$args = [];
if($argc > 1) {
    $args = array_slice($argv, 1);
}

$flt = [];
while($args) {
    $cond = array_shift($args);
    if(preg_match('/^(equipment_models|work_types|techops|measure_units|category_tab|vehicle_models|field_years|crop_years|field_states|production_rates|fixed_assets)$/', $cond)) {
        $flt[] = $cond;
    }

}
if(!$flt) {
    Info('Empty run');
    die();
}
$add = implode(',', $flt);
Info("Started {$add}");

$last_id = 0;
$q = true;

while($q) {
    $q = $DB->prepare("SELECT id, obj FROM st_buffer_1c
                        WHERE parse = 0 AND id > :lid
                        LIMIT 1")
                ->bind('lid', $last_id)
                ->bindColumn(1, $last_id, PDO::PARAM_INT)
                ->bindColumn(2, $lob, PDO::PARAM_LOB)
                ->execute();

    if($q = $DB->fetchRow(PDO::FETCH_BOUND)) {
        try {
            // $txt = stream_get_contents($lob);
            $txt = $lob;
            $obj = json_decode($txt);
            $err = [];
            $parsed = false;
            if(!$obj) {
                throw new Exception('json_decode error ' . json_last_error_msg());
            }
            foreach($obj as $k => $lst) {
                if($flt && !in_array($k, $flt)) {
                    Info("Filtered out $k");
                    continue;
                }
                $cls = '';
                switch($k) {
                    case 'measure_units': $cls = 'MeasureUnit'; break;
                    case 'equipment_models': $cls = 'EquipmentModel'; break;
                    case 'vehicle_models': $cls = 'VehicleModel'; break;
                    case 'techops': $cls = 'TechOperation'; break;
                    case 'fixed_assets': $cls = 'FixedAsset'; break;
                }
                if($cls && method_exists($cls, 'init')) {
                    $cnt = count($lst);
                    Info("Load $k ($cnt rows) [$cls]");
                    $parsed = true;
                    foreach($lst as $ix => $it) {
                        $o = $cls::init($it);
                        $u = (property_exists($cls, 'm_upd') ? $cls::$m_upd : false) ? '+' : '.';
                        if($o && $o->id) {
                            echo $u;
                        } else {
                            Info($DB->error);
                            $guid = property_exists($it, 'guid') ? $it->guid : "no_guid_{$ix}";
                            $key = "{$k}_{$guid}";
                            $err[$key] = $DB->error;
                        }
                    }
                    echo "\n";
                }
            }
            if($parsed) {
                $DB->prepare("UPDATE st_buffer_1c
                                SET parse = 1
                                    , err = :err
                                WHERE id = :id")
                    ->bind('err', json_encode($err, JSON_UNESCAPED_UNICODE))
                    ->bind('id', $last_id)
                    ->execute();
            }
        }
        catch(Exception $ex) {
            Info("Exception [1cRow=$last_id]: " . $ex->getMessage());
            if($last_id) {
                $DB->prepare("UPDATE st_buffer_1c
                                SET err = :err
                                WHERE id = :id")
                    ->bind('err', $ex->getMessage())
                    ->bind('id', $last_id)
                    ->execute();
            }
        }
    }
}

$time = time() - $time;
Info("Finish within $time sec.");