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
        $sel = " SELECT c.id as car_id, c.ts_number, c.firm, c.ts_gps_name, l.dt, li.*
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

            if($val['reason_variant_id'] == 40 && $val['reason_variant_id'] == 41)
            {
                $sel = "SELECT u.email, GROUP_CONCAT(f.firm_id) AS ar_firms
                            FROM spr_users_send_email e
                            JOIN spr_users u ON u.id = e.user_id
                            LEFT JOIN spr_users_firms f ON e.user_id = f.user_id
                            WHERE e.is_send = 1 AND u.email IS NOT NULL
                            GROUP BY e.id";
                $rowsUsers = $DB->select($sel);
                if(isset($rowsUsers[0]['email']))
                {
                    foreach ($rowsUsers as $rowUsers)
                    {
                        if($rowUsers['ar_firms'] != '')
                        {
                            $ar_firms = explode(",", $rowUsers['ar_firms']);
                            if(in_array($val['firm'], $ar_firms))
                                smtpmail($rowUsers['email'], $rowUsers['email'], 'Простой из за неисправности транспортного средства или прицепного оборудования', 'Добрый день.<br>Информирую о простое '.$rows[0]['ts_gps_name'].' из за неисправности техники или прицепного');
                        }
                        else
                            smtpmail($rowUsers['email'], $rowUsers['email'], 'Простой из за неисправности транспортного средства или прицепного оборудования', 'Добрый день.<br>Информирую о простое '.$rows[0]['ts_gps_name'].' из за неисправности техники или прицепного');
                    }
                }
            }

            $reas = $DB->select_row("SELECT * FROM gps_car_reasons WHERE car_id = ".$rows[0]['car_id']." AND gps_car_log_item_id = ".$rows[0]['id']." AND tm = ".$gpsCarLogItemTm);
            if(!isset($reas['id'])) {
                $date = new DateTime();
                $DB->prepare("INSERT INTO gps_car_reasons 
                                (car_id, reason_id, date, gps_car_log_item_id, tm, user_name, from_set)
                                VALUES (:car_id, :reason_id, :date, :gps_car_log_item_id, :tm, :user_name, :from_set)");
                $DB->bind('car_id', $rows[0]['car_id'])
                   ->bind('reason_id', $val['reason_variant_id'])
                    ->bind('date', date("Y-m-d H:i:s", $val['message_send_timestamp']))
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
                ->bind('date', date("Y-m-d H:i:s", $dat['message_send_timestamp']))
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
                ->bind('date', date("Y-m-d H:i:s", $dat['message_send_timestamp']))
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


$configMail['smtp_username'] = 'polomka.techniki@harveast.com';  //Смените на адрес своего почтового ящика.
$configMail['smtp_port'] = '587'; // Порт работы.
$configMail['smtp_host'] =  'mail.harveast.com';  //сервер для отправки почты
$configMail['smtp_password'] = 'OS$H856m0P81Umif729';  //Измените пароль
$configMail['smtp_debug'] = true;  //Если Вы хотите видеть сообщения ошибок, укажите true вместо false
$configMail['smtp_charset'] = 'utf-8';	//кодировка сообщений. (windows-1251 или utf-8, итд)
$configMail['smtp_from'] = 'Agroportal'; //Ваше имя - или имя Вашего сайта. Будет показывать при прочтении в поле "От кого"

function smtpmail($to='', $mail_to, $subject, $message, $headers='') {
    global $configMail;
    $SEND =	"Date: ".date("D, d M Y H:i:s") . " UT\r\n";
    $SEND .= 'Subject: =?'.$configMail['smtp_charset'].'?B?'.base64_encode($subject)."=?=\r\n";
    if ($headers) $SEND .= $headers."\r\n\r\n";
    else
    {
        $SEND .= "Reply-To: ".$configMail['smtp_username']."\r\n";
        $SEND .= "To: \"=?".$configMail['smtp_charset']."?B?".base64_encode($to)."=?=\" <$mail_to>\r\n";
        $SEND .= "MIME-Version: 1.0\r\n";
        $SEND .= "Content-Type: text/html; charset=\"".$configMail['smtp_charset']."\"\r\n";
        $SEND .= "Content-Transfer-Encoding: 8bit\r\n";
        $SEND .= "From: \"=?".$configMail['smtp_charset']."?B?".base64_encode($configMail['smtp_from'])."=?=\" <".$configMail['smtp_username'].">\r\n";
        $SEND .= "X-Priority: 3\r\n\r\n";
    }
    $SEND .=  $message."\r\n";
    if( !$socket = fsockopen($configMail['smtp_host'], $configMail['smtp_port'], $errno, $errstr, 30) ) {
        if ($configMail['smtp_debug']) echo $errno."<br>".$errstr;
        return false;
    }

    if (!server_parse($socket, "220", __LINE__)) return false;

    fputs($socket, "HELO " . $configMail['smtp_host'] . "\r\n");
    if (!server_parse($socket, "250", __LINE__)) {
        if ($configMail['smtp_debug']) echo '<p>Не могу отправить HELO!</p>';
        fclose($socket);
        return false;
    }
    fputs($socket, "AUTH LOGIN\r\n");
    if (!server_parse($socket, "334", __LINE__)) {
        if ($configMail['smtp_debug']) echo '<p>Не могу найти ответ на запрос авторизаци.</p>';
        fclose($socket);
        return false;
    }
    fputs($socket, base64_encode($configMail['smtp_username']) . "\r\n");
    if (!server_parse($socket, "334", __LINE__)) {
        if ($configMail['smtp_debug']) echo '<p>Логин авторизации не был принят сервером!</p>';
        fclose($socket);
        return false;
    }
    fputs($socket, base64_encode($configMail['smtp_password']) . "\r\n");
    if (!server_parse($socket, "235", __LINE__)) {
        if ($configMail['smtp_debug']) echo '<p>Пароль не был принят сервером как верный! Ошибка авторизации!</p>';
        fclose($socket);
        return false;
    }
    fputs($socket, "MAIL FROM: <".$configMail['smtp_username'].">\r\n");
    if (!server_parse($socket, "250", __LINE__)) {
        if ($configMail['smtp_debug']) echo '<p>Не могу отправить комманду MAIL FROM: </p>';
        fclose($socket);
        return false;
    }
    fputs($socket, "RCPT TO: <" . $mail_to . ">\r\n");

    if (!server_parse($socket, "250", __LINE__)) {
        if ($configMail['smtp_debug']) echo '<p>Не могу отправить комманду RCPT TO: </p>';
        fclose($socket);
        return false;
    }
    fputs($socket, "DATA\r\n");

    if (!server_parse($socket, "354", __LINE__)) {
        if ($configMail['smtp_debug']) echo '<p>Не могу отправить комманду DATA</p>';
        fclose($socket);
        return false;
    }
    fputs($socket, $SEND."\r\n.\r\n");

    if (!server_parse($socket, "250", __LINE__)) {
        if ($configMail['smtp_debug']) echo '<p>Не смог отправить тело письма. Письмо не было отправленно!</p>';
        fclose($socket);
        return false;
    }
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    return TRUE;
}

function server_parse($socket, $response, $line = __LINE__) {
    global $configMail;
    while (@substr($server_response, 3, 1) != ' ') {
        if (!($server_response = fgets($socket, 256))) {
            if ($configMail['smtp_debug']) echo "<p>Проблемы с отправкой почты!</p>$response<br>$line<br>";
            return false;
        }
    }
    if (!(substr($server_response, 0, 3) == $response)) {
        if ($configMail['smtp_debug']) echo "<p>Проблемы с отправкой почты!</p>$response<br>$line<br>";
        return false;
    }
    return true;
}