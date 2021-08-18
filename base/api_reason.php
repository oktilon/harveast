<?php
require_once dirname(__DIR__) . '/html/sess.php';
date_default_timezone_set('Europe/Kiev');
//file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\n\ndate ----- ".date("Y-m-d H:i:s"), FILE_APPEND);
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://harveast.com.ua/bot/api_ap.php?get=all&key=E1A3332b5t274ga9U793',
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => array(
        'cache-control: no-cache',
        'content-type: application/json',
    ),
));
$responseJson = curl_exec($curl);
curl_close($curl);
$response = json_decode($responseJson, 1);
if(isset($response['reasons_list']) && is_array($response['reasons_list']) && count($response['reasons_list']) > 0)
{
    //file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\ncount api give ----- ".print_r(count($response['reasons_list']),1), FILE_APPEND);
    foreach ($response['reasons_list'] as $key=>$val)
    {
        $min = date("i",$val['message_send_timestamp']);
        if($min < 15)
            $minVal = 0;
        elseif($min < 30)
            $minVal = 15;
        elseif($min < 30)
            $minVal = 30;
        else
            $minVal = 45;

        $hor = date("H",$val['message_send_timestamp']);
        $horArray = array('07' => 0, '08' => 1, '09' => 2, '10' => 3, '11' => 4, '12' => 5, '13' => 6, '14' => 7, '15' => 8, '16' => 9, '17' => 10, '18' => 11, '19' => 12, '20' => 13, '21' => 14, '22' => 15, '23' => 16, '00' => 17, '01' => 18, '02' => 19, '03' => 20, '04' => 21, '05' => 22, '06' => 23);
        $gpsCarLogItemTm = $horArray[$hor] * 4 * 15 + $minVal;
        $sel = " SELECT c.id as car_id, c.ts_number, c.ts_gps_name, l.dt, li.*
                    FROM gps_carlist c
                        JOIN gps_car_log l ON c.id = l.car AND l.dt = DATE_FORMAT('".date("Y-m-d H:i:s", $val['message_send_timestamp'])."', '%Y-%m-%d')
                        JOIN gps_car_log_item li ON li.log_id = l.id AND li.tm = ".$gpsCarLogItemTm."
                    where c.ts_gps_name =  '".$val['tractor_name']."'";
        $rows = $DB->select($sel);
        if(!isset($rows[0]['car_id']))
        {
            $sel = " SELECT c.id
                    FROM gps_carlist c
                    where c.ts_gps_name =  '".$val['tractor_name']."'";
            $cars = $DB->select($sel);
            if(!isset($cars[0]['id']))
            {
                echo "\nnot find car - ".$val['tractor_name'];
                //file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\nnot find car ----- ".print_r($val['tractor_name'],1), FILE_APPEND);
            }
            else
            {
                $sel = " SELECT l.id
                    FROM gps_carlist c
                    JOIN gps_car_log l ON c.id = l.car AND l.dt = DATE_FORMAT('".date("Y-m-d H:i:s", $val['message_send_timestamp'])."', '%Y-%m-%d')
                    where c.ts_gps_name =  '".$val['tractor_name']."'";
                $log = $DB->select($sel);
                if(!isset($log[0]['id']))
                {
                    //file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\nnot find log ----- ".date("Y-m-d H:i:s", $val['message_send_timestamp'])." ------- for car ------  ".print_r($val['tractor_name'],1), FILE_APPEND);
                    echo "\nnot find log - ".date("Y-m-d H:i:s", $val['message_send_timestamp'])." for car - ".$val['tractor_name'];
                }
                else
                {
                    //file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\nnot find log item  ----- ".date("Y-m-d H:i:s", $val['message_send_timestamp'])." ------- for car ------  ".print_r($val['tractor_name'],1)." ------ tm ---------- ".print_r($gpsCarLogItemTm,1), FILE_APPEND);
                    echo "\nnot find log item - ".date("Y-m-d H:i:s", $val['message_send_timestamp'])." for car - ".$val['tractor_name']." tm - ".$gpsCarLogItemTm;
                }
            }
        }
        else
        {
            $sel = "UPDATE gps_car_log_item
                    SET reason = ".$val['reason_variant_id']."
                    where id = ".$rows[0]['id'];
            $log = $DB->select($sel);

            $reas = $DB->select_row("SELECT * FROM gps_car_reasons WHERE car_id = ".$rows[0]['car_id']." AND gps_car_log_item_id = ".$rows[0]['id']." AND tm = ".$gpsCarLogItemTm);
            if(!isset($reas['id'])) {
                $date = new DateTime();
                $DB->prepare("INSERT INTO gps_car_reasons 
                                (car_id, reason_id, date, gps_car_log_item_id, tm, user_name, from_set)
                                VALUES (:car_id, :reason_id, :date, :gps_car_log_item_id, :tm, :user_name, :from_set)");
                $DB->bind('car_id', $rows[0]['car_id'])
                   ->bind('reason_id', $val['reason_variant_id'])
                    ->bind('date', date("Y-m-d H:is:s", $val['message_send_timestamp']))
                   ->bind('gps_car_log_item_id', $rows[0]['id'])
                   ->bind('tm', $gpsCarLogItemTm)
                   ->bind('user_name', $val['fio'])
                   ->bind('from_set', 1);
                $r = $DB->execute();
            }

            $arr['reason'] = $val['reason_variant_id'];
            $arr['message_send_timestamp'] = $val['message_send_timestamp'];
            $arr['tm'] = $rows[0]['tm'];
            $arr['log_id'] = $rows[0]['log_id'];
            $arr['base_tm'] = $rows[0]['tm'];
            setReasonNext($arr, $rows[0]);
            echo "\nlog item  - ".$rows[0]['id'];
            //file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\nupdate id ----- ".print_r($rows[0]['id'],1)." ------ reason ---------- ".print_r($val['reason_variant_id'],1), FILE_APPEND);
        }
    }

    /*$min = date("i",time());
    if($min < 15)
        $minVal = 0;
    elseif($min < 30)
        $minVal = 15;
    elseif($min < 30)
        $minVal = 30;
    else
        $minVal = 45;
    $hor = date("H",time());
    $horArray = array('07' => 0, '08' => 1, '09' => 2, '10' => 3, '11' => 4, '12' => 5, '13' => 6, '14' => 7, '15' => 8, '16' => 9, '17' => 10, '18' => 11, '19' => 12, '20' => 13, '21' => 14, '22' => 15, '23' => 16, '00' => 17, '01' => 18, '02' => 19, '03' => 20, '04' => 21, '05' => 22, '06' => 23);
    $gpsCarLogItemTm = $horArray[$hor] * 4 * 15 + $minVal;*/
    /*$sel = " SELECT c.id as car_id, c.ts_number, c.ts_gps_name, l.dt, li.*
                    FROM gps_carlist c
                        JOIN gps_car_log l ON c.id = l.car AND l.dt = DATE_FORMAT('".date("Y-m-d H:i:s", $val['message_send_timestamp'])."', '%Y-%m-%d')
                        JOIN gps_car_log_item li ON li.log_id = l.id AND li.tm = ".$gpsCarLogItemTm."
                    WHERE reason IN (43, 23, 39)";*/
    $sel = "SELECT l.dt, li.log_id, MAX(li.tm) AS tm, li.reason, l.id, l.car
                    FROM gps_car_log l 
                        JOIN gps_car_log_item li ON li.log_id = l.id
                    WHERE li.reason IN (43, 23, 39, 44) AND l.dt = '".date("Y-m-d")."'
                    GROUP BY li.log_id";
    $rows = $DB->select($sel);
    if(isset($rows[0]['car_id']))
    {
        foreach ($rows as $row)
        {
            $arr['reason'] = $row['reason'];
            $arr['message_send_timestamp'] = $val['message_send_timestamp'];
            $arr['tm'] = $row['tm'];
            $arr['log_id'] = $row['log_id'];
            $arr['base_tm'] = $row['tm'];
            setReasonNext($arr, $rows[0], ' AND reason = 0 ');
        }
    }
}

function setReasonNext($dat, $row, $dop = '') {
    global $DB;
    $arg = '';
    $arg = $DB->select_row("SELECT * FROM gps_car_log_item WHERE log_id = ".$dat['log_id']." AND tm = (".$dat['tm']." + 15) AND ROUND(tm_move / 60, 0) < 4".$dop);
    if(is_array($arg) && isset($arg['id'])) {
        $date = new DateTime();
        $DB->prepare("UPDATE gps_car_log_item SET reason = :r, dt_last = :d WHERE id = :i");
        $DB->bind('r', $dat['reason'])
            ->bind('d', $date->format('Y-m-d H:i:s'))
            ->bind('i', $arg['id']);
        $r = $DB->execute();
        $reas = $DB->select_row("SELECT * FROM gps_car_reasons WHERE car_id = ".$row['car_id']." AND gps_car_log_item_id = ".$row['id']." AND tm = ".($dat['tm'] + 15));
        if(!isset($reas['id'])) {
            $DB->prepare("INSERT INTO gps_car_reasons 
                                (car_id, reason_id, date, gps_car_log_item_id, tm, is_auto, from_set)
                                VALUES (:car_id, :reason_id, :date, :gps_car_log_item_id, :tm, :is_auto, :from_set)");
            $DB->bind('car_id', $row['car_id'])
               ->bind('reason_id', $dat['reason'])
                ->bind('date', date("Y-m-d H:is:s", $dat['message_send_timestamp']))
               ->bind('gps_car_log_item_id', $row['id'])
               ->bind('tm', ($dat['tm'] + 15))
               ->bind('is_auto', 1)
               ->bind('from_set', 1);
            $r = $DB->execute();
        }

        $dat['tm'] = $arg['tm'];
        setReasonNext($dat, $row, $dop);
    }
    else
    {
        $dat['tm'] = $dat['base_tm'];
        if($dop == '')
            setReasonPrev($dat, $row);
    }
    return true;
}

function setReasonPrev($dat, $row) {
    global $DB;
    $arg = '';
    $arg = $DB->select_row("SELECT * FROM gps_car_log_item WHERE log_id = ".$dat['log_id']." AND tm = (".$dat['tm']." - 15) AND ROUND(tm_move / 60, 0) < 4 AND reason = 0");
    if(is_array($arg) && isset($arg['id'])) {
        $date = new DateTime();
        $DB->prepare("UPDATE gps_car_log_item SET reason = :r, dt_last = :d WHERE id = :i");
        $DB->bind('r', $dat['reason'])
            ->bind('d', $date->format('Y-m-d H:i:s'))
            ->bind('i', $arg['id']);
        $r = $DB->execute();

        $reas = $DB->select_row("SELECT * FROM gps_car_reasons WHERE car_id = ".$row['car_id']." AND gps_car_log_item_id = ".$row['id']." AND tm = ".($dat['tm'] + 15));
        if(!isset($reas['id'])) {
            $DB->prepare("INSERT INTO gps_car_reasons 
                                (car_id, reason_id, date, gps_car_log_item_id, tm, is_auto, from_set)
                                VALUES (:car_id, :reason_id, :date, :gps_car_log_item_id, :tm, :is_auto, :from_set)");
            $DB->bind('car_id', $row['car_id'])
               ->bind('reason_id', $dat['reason'])
                ->bind('date', date("Y-m-d H:is:s", $dat['message_send_timestamp']))
               ->bind('gps_car_log_item_id', $row['id'])
               ->bind('tm', ($dat['tm'] + 15))
               ->bind('is_auto', 1)
               ->bind('from_set', 1);
            $r = $DB->execute();
        }

        $dat['tm'] = $arg['tm'];
        setReasonPrev($dat);
    }
    return true;
}
