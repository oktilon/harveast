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
    if(i1C::validItem($cond)) {
        $flt[] = $cond;
    }

}

if(!$flt) {
    $lckFile = str_replace('.php', '.lck', $_SERVER['SCRIPT_FILENAME']);
    if(!GlobalMethods::pidLock($lckFile, 10800)) die();
}


$add = $flt ? implode(',', $flt) : 'all';
Info("Started {$add}");

$tot_cnt = 0;
$tot_upd = 0;

foreach(i1C::$item_list as $it_key => $cls) {
    $last_id = 0;
    $q = true;
    $dto = '';
    if($flt && !in_array($it_key, $flt)) continue;
    Info(sprintf('Load: %s [$s]', $it_key, $cls));
    $it_upd = 0;
    $it_cnt = 0;

    while($q) {
        $q = $DB->prepare("SELECT id, obj, dt FROM st_buffer_1c
                            WHERE parse = 0 AND id > :lid
                            LIMIT 1")
                    ->bind('lid', $last_id)
                    ->bindColumn(1, $last_id, PDO::PARAM_INT)
                    ->bindColumn(2, $lob, PDO::PARAM_LOB)
                    ->bindColumn(3, $dto, PDO::PARAM_STR)
                    ->execute();

        if($q = $DB->fetchRow(PDO::FETCH_BOUND)) {
            try {
                // $txt = stream_get_contents($lob);
                $txt = $lob;
                $obj = json_decode($txt);
                $odt = new DateTime($dto);
                Info(sprintf('Rec: %d, at $s', $last_id, $odt->format('Y-m-d H:i:s')));
                $err = [];
                $parsed = false;
                if(!$obj) {
                    throw new Exception('json_decode error ' . json_last_error_msg());
                }
                foreach($obj as $k => $lst) {
                    if($k !== $it_key) continue;

                    if($cls && method_exists($cls, 'init')) {
                        $cnt = count($lst);
                        $it_cnt += $cnt;
                        Info("Load: $k ($cnt rows)");
                        $parsed = true;
                        foreach($lst as $ix => $it) {
                            $o = $cls::init($it);
                            $u = property_exists($cls, 'm_upd') ? $cls::$m_upd : false;
                            if($o && $o->id) {
                                echo $u ? '+' : '.';
                                if($u) $it_upd++;
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
    Info(sprintf('Finish: %s, cnt: %d, upd: %d', $it_key, $it_cnt, $it_upd));
    $tot_cnt += $it_cnt;
    $tot_upd += $it_upd;
}

$valid = '';
if(!$flt) {
    $cnt = TechOperation::validateOperations();
    $valid = ", validated $cnt TechOp.";
}

$time = time() - $time;
Info(sprintf("Finish within %d sec., total: %d, updated: %d%s", $time, $tot_cnt, $tot_upd, $valid));