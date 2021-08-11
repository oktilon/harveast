<?php
require_once dirname(__DIR__) . '/html/sess.php';
date_default_timezone_set('Europe/Kiev');
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://harveast.com.ua/bot/api_ap.php?get=users&key=E1A3332b5t274ga9U793',
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => array(
        'cache-control: no-cache',
        'content-type: application/json',
    ),
));
$responseJson = curl_exec($curl);
curl_close($curl);
$response = json_decode($responseJson, 1);
if(isset($response['users_list']) && is_array($response['users_list']) && count($response['users_list']) > 0)
{
    foreach ($response['users_list'] as $key=>$val)
    {
        $sel = " SELECT c.id
                    FROM gps_carlist c
                    where c.ts_gps_name = '".$val['user_name']."'";
        $rows = $DB->select($sel);
        if(isset($rows[0]['id']))
        {
            $sel = " SELECT c.id
                    FROM gps_car_phone c
                    where c.car_id =  ".$rows[0]['id'];
            $cars = $DB->select($sel);
            if(isset($cars[0]['id']))
            {
                $DB->prepare("UPDATE gps_car_phone SET phone = :p WHERE id = :i");
                $DB->bind('p', $val['phone'])
                   ->bind('i', $cars[0]['id']);
                $r = $DB->execute();
            }
            else
            {
                $DB->prepare("INSERT INTO gps_car_log_item (car_id, phone) VALUES (:c, :p)");
                $DB->bind('c', $rows[0]['id'])
                   ->bind('p', $val['phone']);
                $r = $DB->execute();
            }
        }
    }

}

