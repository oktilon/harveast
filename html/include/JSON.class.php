<?php
class JSON
{
	private static $isScript = false;

	function __construct()
	{
	}

	public static function run($script)
	{
		self::$isScript = true;
		$obj = (object) [
			'm' => $script
		];
		$txt = json_encode($obj);
		$_REQUEST['obj'] = '{"p":1}';
		self::parse($txt);
	}

	public static function log_server($module, $user_id, $status, $req = false, $err = '')
	{
		global $DB;
		$err_line = $err ? ", err = :err" : '';
		$DB->prepare("INSERT INTO wx_server_log
						SET ip         = :ip
							, module   = :module
							, user_id  = :user_id
							, `status` = :status
							, request  = :request
							$err_line")
			->bind("module",	$module)
			->bind("user_id",   $user_id)
			->bind("status",	$status)
			->bind("request",   json_encode($req ? $req : $_REQUEST))
			->bind("ip",		isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');
		if ($err) $DB->bind('err', $err);
		$DB->execute();
		return $DB->lastInsertId();
	}

	public static function loadClass($className)
	{
		global $DB;
		$u = isset($_SESSION['user']) ? json_decode($_SESSION['user']) : false;
		$user_id = $u ? $u->id : 0;
		$mdl = $className . '.class';
		$q = $DB->prepare("SELECT *
								FROM 	hrv_spr_modules
								WHERE 	(module = :module OR module = md5(:module)) AND `type` = 2
								ORDER BY id DESC
								LIMIT 1")
			->bind('module', $mdl)
			->execute_row();
		if (!$q) return false;
		$fn = $q['function'];
		try {
			$fun = create_function('$obj', $fn);
			$fun([]);
			JSON::log_server($mdl, $user_id, "ok", (object)['autoload' => $className]);
			return true;
		} catch (ParseError $p) {
			$err = $p->getMessage();
			JSON::log_server($mdl, $user_id, "parser error", (object)['autoload' => $className], $err);
		}
		return false;
	}


	public static function parse($txt)
	{
		global $DB;

		$obj = json_decode($txt);
		if (property_exists($obj, 'm')) {

			$user_id = 0;
			if (isset($_SESSION['user'])) {
				$u 			= json_decode($_SESSION['user']);
				$user_id 	= $u->id;
			}

			if ($user_id > 0 || self::$isScript) {
				$DB->prepare("
						SELECT 	*
						FROM 	hrv_spr_modules
						WHERE 	(module = :module OR module = md5(:module)) AND `type` = 2
						ORDER BY id DESC LIMIT 1
					");
			} else {
				$DB->prepare("
						SELECT 	*
						FROM 	hrv_spr_modules
						WHERE 	((module = :module OR module = md5(:module)) AND `type` = 2)AND
								TRIM(dm_roles) = '999999'
						ORDER BY id DESC LIMIT 1
					");
			}
			$DB->bind('module', $obj->m);
			$q = $DB->execute_all();

			if(defined('ALT_MODULES')) $q = GlobalMethods::altLoad($obj->m, $q);

			if (count($q) > 0) {


				if ($user_id > 0) {

					if ($q[0]['dm_roles'] != '') {
						$r = $DB->select("SELECT * FROM {TABLE_RIGHTS} WHERE user_id = {$user_id} AND right_id IN ({$q[0]['dm_roles']})");
						if (!$r) {
							JSON::log_server($obj->m, $user_id, "rights wrong");
							echo json_encode(array("status" => "error", "msg" => "Denied rights of access"));
							die;
						}
					}

					if ($q[0]['dm_users'] != '') {
						$dm_users = explode(",", $q[0]['dm_users']);
						$dmu = [];
						foreach ($dm_users as $users) {
							$dmu[$users] = $users;
						}
						if (!isset($dmu[$u->id])) {
							JSON::log_server($obj->m, $user_id, "Access denied");
							echo json_encode(array("status" => "error", "msg" => "Access denied"));
							die;
						}
					}
				}


				$log_id =  JSON::log_server($obj->m, $user_id, "ok");

				$fn = $q[0]['function'];
				if (property_exists($obj, 'p')) {

					try {
						$fun = create_function('$p', $fn);
						return $fun($obj->p);
					} catch (ParseError $p) {
						$err = $p->getMessage();
						$DB->prepare("UPDATE wx_server_log SET err=:err WHERE id=:id;");
						$DB->bind("err", $err);
						$DB->bind("id", $log_id);
						$DB->execute();
						echo json_encode(["status" => "error", "err" => $err]);
					}
				} else {

					try {
						$fun = create_function('', $fn);
						return $fun();
					} catch (ParseError $p) {
						$err = $p->getMessage();
						$DB->prepare("UPDATE wx_server_log SET err=:err WHERE id=:id;");
						$DB->bind("err", $err);
						$DB->bind("id", $log_id);
						$DB->execute();
						echo json_encode(["status" => "error", "err" => $err]);
					}
				}
			} else {
				JSON::log_server($obj->m, $user_id, "No such function");
				return json_encode(array("status" => "error", "err" => "No such function"));
			}
		} else {
			JSON::log_server($txt, $user_id, "Bad Format");
			return json_encode(array("status" => "error", "err" => "Bad Format"));
		}
	}
}
