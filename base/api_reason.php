<?php
require_once dirname(__DIR__) . '/html/sess.php';
file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\n\ndate ----- ".date("Y-m-d H:i:s"), FILE_APPEND);
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
    file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\ncount api give ----- ".print_r(count($response['reasons_list']),1), FILE_APPEND);
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
                file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\nnot find car ----- ".print_r($val['tractor_name'],1), FILE_APPEND);
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
                    file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\nnot find log ----- ".date("Y-m-d H:i:s", $val['message_send_timestamp'])." ------- for car ------  ".print_r($val['tractor_name'],1), FILE_APPEND);
                    echo "\nnot find log - ".date("Y-m-d H:i:s", $val['message_send_timestamp'])." for car - ".$val['tractor_name'];
                }
                else
                {
                    file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\nnot find log item  ----- ".date("Y-m-d H:i:s", $val['message_send_timestamp'])." ------- for car ------  ".print_r($val['tractor_name'],1)." ------ tm ---------- ".print_r($gpsCarLogItemTm,1), FILE_APPEND);
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

            $arr['reason'] = $val['reason_variant_id'];
            $arr['tm'] = $rows[0]['tm'];
            $arr['log_id'] = $rows[0]['log_id'];
            $arr['base_tm'] = $rows[0]['tm'];
            setReasonNext($arr);
            echo "\nlog item  - ".$rows[0]['id'];
            file_put_contents("/var/www/html/public/base/rez_api_".date("Y-m-d").".txt", "\nupdate id ----- ".print_r($rows[0]['id'],1)." ------ reason ---------- ".print_r($val['reason_variant_id'],1), FILE_APPEND);
        }
    }
}

function setReasonNext($dat) {
    global $DB;
    $arg = '';
    $arg = $DB->select_row("SELECT * FROM gps_car_log_item WHERE log_id = ".$dat['log_id']." and tm = (".$dat['tm']." + 15) and ROUND(tm_move / 60, 0) < 4");
    if(is_array($arg) && isset($arg['id'])) {
        $date = new DateTime();
        $DB->prepare("UPDATE gps_car_log_item SET note = :n, reason = :r, dt_last = :d WHERE id = :i");
        $DB->bind('r', $dat['reason'])
            ->bind('d', $date->format('Y-m-d H:i:s'))
            ->bind('i', $arg['id']);
        $r = $DB->execute();
        $dat['tm'] = $arg['tm'];
        setReasonNext($dat);
    }
    else
    {
        $dat['tm'] = $dat['base_tm'];
        setReasonPrev($dat);
    }
    return true;
}

function setReasonPrev($dat) {
    global $DB;
    $arg = '';
    $arg = $DB->select_row("SELECT * FROM gps_car_log_item WHERE log_id = ".$dat['log_id']." and tm = (".$dat['tm']." - 15) and ROUND(tm_move / 60, 0) < 4");
    if(is_array($arg) && isset($arg['id'])) {
        $date = new DateTime();
        $DB->prepare("UPDATE gps_car_log_item SET note = :n, reason = :r, dt_last = :d WHERE id = :i");
        $DB->bind('r', $dat['reason'])
            ->bind('d', $date->format('Y-m-d H:i:s'))
            ->bind('i', $arg['id']);
        $r = $DB->execute();
        $dat['tm'] = $arg['tm'];
        setReasonPrev($dat);
    }
    return true;
}
