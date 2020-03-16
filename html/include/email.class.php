<?php
	require_once "../PHPMailer/PHPMailerAutoload.php";

	class Email {
		/** @var PHPMailer */
		private $mailer = null;
        const CUSTOM_HEADER = 'MailerPortal';

        public static $SmtpLogin    = "mailerportal@ventaltd.com.ua";
        public static $SmtpPassword = "1qaZXsw23edC";
        public static $SmtpHost     = '172.20.0.20';

        /**
        * Create PHPMailer for send Email messages
        *
        * @param string Имя отправителя
        * @param string Адрес отправителя
        * @param string Адрес почтового сервера
        * @param integer Порт почтового сервера (25, TLS.SMTP=587)
        * @param integer Режим отладки (вывод сообщений) 0=Выкл, 1=Клиент, 2=Клиент и Сервер
        * @return Email
        */
		function __construct($senderName = 'MailerPortal', $senderAddr = 'mailerportal@ventaltd.com.ua', $host = '', $port = 0, $debug = 2) {
            //date_default_timezone_set('Etc/UTC');

            $this->mailer = new PHPMailer;
            $this->mailer->isSMTP();
            $this->mailer->SMTPDebug   = $debug;
            $this->mailer->Debugoutput = 'html';

            $this->mailer->CharSet     = 'UTF-8';
            $this->mailer->Encoding    = '8bit';
            $this->mailer->Host        = $host ? $host : self::$SmtpHost;
            $this->mailer->Port        = $port ? $port : 26 ;
            // SMTP Auth
            //$this->mailer->SMTPSecure  = 'tls';
            //$this->mailer->SMTPSecure = '';

            $this->mailer->SMTPSecure  = false;
            $this->mailer->SMTPAutoTLS = false;

            $this->mailer->SMTPAuth    = true;
            $this->mailer->Username    = self::$SmtpLogin;
            $this->mailer->Password    = self::$SmtpPassword;

            // Sender
            $this->mailer->setFrom($senderAddr, $senderName);
        }

        function setDebugMode($iMode) {
            if($iMode >= 0 && $iMode < 3) {
                $this->mailer->SMTPDebug = $iMode;
            }
        }

        function addCustomHeader($name, $value = null) {
            $this->mailer->addCustomHeader($name, $value);
        }

		function attachFile($fileName) {
			if(file_exists($fileName)) {
				$this->mailer->addAttachment($fileName);
			}
		}

        function bodyFromFile($fileName) {
            if(file_exists($fileName)) {
                $this->mailer->msgHTML(file_get_contents($fileName), dirname(__FILE__));
            }
        }

        function writeDelivery($user_id, $email, $who) {
            global $DB;
            if(!$user_id) return $this;
            $id = $DB->select("SELECT id FROM mail_delivery WHERE user_id = $user_id");
            if($id) {
                $id = intval($id[0]['id']);
                $q = $DB->query("UPDATE mail_delivery SET dt=NOW(), email='$email', who=$who WHERE id = $id");
            } else {
                $q = $DB->query("INSERT INTO mail_delivery (user_id, email, who, flag, dt)
                                    VALUES ($user_id, '$email', $who, 1, NOW())");
                $id = $q ? $DB->lastInsertId() : 0;
            }
            if($id) {
                $this->mailer->addCustomHeader(self::CUSTOM_HEADER, $id);
            }
            return $this;
        }

		function send($rcptAddr, $rcptName, $subject = '', $message = '', $isHtml = true) {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($rcptAddr, $rcptName);
            //$this->mailer->addReplyTo('replyto@example.com', 'FirstName LastName');
            $this->mailer->Subject = $subject;

            //Replace the plain text body with one created manually
            //$this->mailer->AltBody = 'Hello, world!';

            if($message) {
                if ($isHtml) {
                    $this->mailer->IsHTML(true);
                }
                $this->mailer->Body = $message;
            }

            //send the message, check for errors
            $ret = $this->mailer->send();

            if($this->mailer->SMTPDebug > 0) {
                if($ret) {
                    echo "Message sent!";
                } else {
                    echo "Mailer Error: " . $this->mailer->ErrorInfo;
                }
            }
            return $ret;
        }

        static function createKey($template_name) {
            return md5('f5Dgb' . date('mdY') . $template_name);
        }

        static function sendTemplate($user, $template, $data = array(), $who = 3) {
            $body = array(
                'template_name' => $template,
                'who'           => $who,
                'key'           => self::createKey($template),
                'message'       => array(
                    'global_merge_vars' => array(),
                    'to'                => array(
                        array(
                            'email' => '',
                            'name'  => ''
                        )
                    )
                )
            );
            if(is_a($user, 'CUser')) {
                $body['message']['to'][0]['email'] = $user->Email;
                $body['message']['to'][0]['name']  = $user->Fio(null, ' ');
            } else {
                $body['message']['to'][0]['email'] = $user;
                $body['message']['to'][0]['name']  = $user;
            }
            foreach($data as $k=>$v) {
                $body['message']['global_merge_vars'][] = array(
                    'name'    => $k,
                    'content' => $v
                );
            }

            $ch = curl_init('https://portal.agrocentrua.com/conv/mail/');
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array('Content-type: application/json; charset=utf8')
            );
            curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($body));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT,         5);
            $result = curl_exec($ch);
            $err    = curl_error($ch);
            curl_close($ch);
            if ($result === FALSE) return $err;
            $res = @json_decode($result);
            if(empty($res)) return 'No valid JSON.';
            if(isset($res->status)) {
                return $res->status;
            }
            return 'Status not found';
        }
    }