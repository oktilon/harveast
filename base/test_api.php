<?php
require_once dirname(__DIR__) . '/html/sess.php';
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
echo "<pre>"; print_r($response);echo "</pre>";die();