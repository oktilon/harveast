<?php
/**
 * Класс для работы с сообщениями Telegram Messenger
 */
class Telegram {
    const API_URL   = 'https://api.telegram.org/bot';

    const TYPE_TEXT  = 0x01;
    const TYPE_PHOTO = 0x02;

    public static $error  = '';
    public static $result = '';
    public static $return = null;

    // reply_to_message_id
    public static function sendMessage($chat, $text, $extra = null, $log = true) {
        global $DB;
        $data = ($extra != null && is_array($extra)) ? $extra : [];
        $data['chat_id'] = $chat;
        $data['text']    = $text;
        if(!isset($data['parse_mode'])) $data['parse_mode'] = 'Markdown';
        $mid = self::postMethod('sendMessage', $data);
        // if($log && isset($DB)) {
        //     $DB->prepare("INSERT INTO tg_messages
        //                     (chat, user, txt, msg_id) VALUES
        //                     (:c, :u, :t, :m)")
        //         ->bind('c', $chat)
        //         ->bind('t', $text)
        //         ->bind('m', $mid)
        //         //->bind('d', self::$result)
        //         ->execute();
        // }
        return $mid;
    }

    public static function answerCallbackQuery($cq_id, $answer = '', $show_alert = false) {
        $data = [ 'callback_query_id' => $cq_id ];
        if(!empty($answer)) {
            $data['text']       = $answer;
            $data['show_alert'] = $show_alert;
        };
        $ret = self::postMethod('answerCallbackQuery', $data);
        return $ret;
    }

    private static function postMethod($method, $data) {
        $json   = json_encode($data);
        $strUrl = self::API_URL . TELEGRAM_KEY . '/' . $method;
        $ch = curl_init($strUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array('Content-type: application/json; charset=utf8')
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS,     $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT,         5);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $ret = false;
        $mid = 0;
        if($res) {
            $ret = json_decode($res);
        }
        if(!empty($ret) && is_object($ret)) {
            //$ok  = property_exists($ret, 'ok') ? $ret->ok : false;
            if(property_exists($ret, 'result')) {
                if(property_exists($ret->result, 'message_id')) {
                    $mid = $ret->result->message_id;
                }
            }
        }
        self::$error  = $err;
        self::$result = $res;
        self::$return = $ret;
        return $mid;
    }
}