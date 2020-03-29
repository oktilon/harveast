<?php
 
	 
	class JSON{

		function __construct(){
		}

		public static function log_server($module,$user_id,$status){
			global $DB;
			$DB->prepare("
				INSERT INTO wx_server_log
				SET ip 		= :ip,
					module 	= :module,
					user_id = :user_id,
					status  = :status,
					`request`=:request
			");
			$DB->bind("module",	$module);
			$DB->bind("user_id",$user_id);
			$DB->bind("status",	$status);
			$DB->bind("request",json_encode($_REQUEST));
			$DB->bind("ip",		$_SERVER['REMOTE_ADDR']);
			$DB->execute();
			return $DB->lastInsertId();
		}

		 

		public static function parse($obj){
			global $DB;

			$obj = json_decode($obj);
			if(isset($obj->m)){

				$user_id = 0;
				if(isset($_SESSION['user'])){
					$u 			= json_decode($_SESSION['user']);
					$user_id 	= $u->id;
				}

				if($user_id>0){
					$DB->prepare("
						SELECT 	* 
						FROM 	hrv_spr_modules 
						WHERE 	(module = :module OR module = md5(:module)) AND `type` = 2 
						ORDER BY id DESC LIMIT 1
					");
				}else{
					$DB->prepare("
						SELECT 	* 
						FROM 	hrv_spr_modules 
						WHERE 	((module = :module OR module = md5(:module)) AND `type` = 2)AND 
								TRIM(dm_roles) = '999999' 
						ORDER BY id DESC LIMIT 1
					");
				}
				$DB->bind('module',$obj->m);
				$q = $DB->execute_all();
				
				if(count($q)>0){

					 
					if($user_id>0){
						
						if($q[0]['dm_roles']!=''){
							$r = $DB->select("SELECT * FROM {TABLE_RIGHTS} WHERE user_id = {$user_id} AND right_id IN ({$q[0]['dm_roles']})");
							if(!$r){
								JSON::log_server($obj->m,$user_id,"rights wrong");
								echo json_encode(array("status"=>"error","msg"=>"Denied rights of access"));
								die;
							}
						}

						if($q[0]['dm_users']!=''){
							$dm_users = explode(",", $q[0]['dm_users']);
							$dmu = [];
							foreach ($dm_users as $users) {
								$dmu[$users] = $users;
							}
							if(!isset($dmu[$u->id])){
								JSON::log_server($obj->m,$user_id,"Access denied");
								echo json_encode(array("status"=>"error","msg"=>"Access denied"));
								die;
							}
						}
					}
					

					$log_id =  JSON::log_server($obj->m,$user_id,"ok");

					$fn = $q[0]['function'];
					if(isset($obj->p)){

						try {
							$ExecF[$obj->m] = create_function('$p',$fn);
							return $ExecF[$obj->m]($obj->p);
						} catch (ParseError $p) {
						    $err = $p->getMessage();
							$DB->prepare("UPDATE wx_server_log SET err=:err WHERE id=:id;");
							$DB->bind("err",$err);
							$DB->bind("id" ,$log_id);
							$DB->execute();
						    echo json_encode(["status"=>"error","err"=>$err]);
						}

					}else{

						try {
							$ExecF[$obj->m] = create_function(''  ,$fn);
							return $ExecF[$obj->m]();
						} catch (ParseError $p) {
							$err = $p->getMessage();
							$DB->prepare("UPDATE wx_server_log SET err=:err WHERE id=:id;");
							$DB->bind("err",$err);
							$DB->bind("id" ,$log_id);
							$DB->execute();
						    echo json_encode(["status"=>"error","err"=>$err]);
						}

					}

				}else{
					JSON::log_server($obj->m,$user_id,"No such function");
					return json_encode(array("status"=>"error","err"=>"No such function"));
				}
			}else{
				JSON::log_server($obj->m,$user_id,"Bad Format");
				return json_encode(array("status"=>"error","err"=>"Bad Format"));
			}
		}
		
	}