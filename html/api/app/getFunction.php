<?php
	require_once '../sess.php';

	$user_id = 0;
	if(isset($_SESSION['user'])){
		$u 			= json_decode($_SESSION['user']);
		$user_id 	= $u->id;
	}
	if(!isset($_POST['m'])){
		JSON::log_server('',0,"error");
		die;
	}

	if($user_id>0){

		$DB->prepare("
			SELECT *,
					UNIX_TIMESTAMP(dt_update) as dt
			FROM hrv_spr_modules
			WHERE 	module = :module  OR
					module = md5(:module)
			ORDER BY id
			DESC LIMIT 1;
		");

	}else{

		$DB->prepare("
			SELECT 	*,
					UNIX_TIMESTAMP(dt_update) as dt
			FROM 	hrv_spr_modules
			WHERE 	(module = :module  OR module = md5(:module)) AND
					TRIM(dm_roles) = '999999'
			ORDER BY id
			DESC LIMIT 1;
		");
	}
	$DB->bind('module',$_POST['m']);
	$q = $DB->execute_all();

	$dt = isset($_POST['dt']) ? intval($_POST['dt']) : 0;
	if(defined('ALT_MODULES')) GlobalMethods::altLoadJs($_POST['m'], $dt);

	if(!$q){
		echo json_encode(array("status"=>"error"));
		JSON::log_server($_POST['m'],$user_id,"error");
		die;
	}



	if($user_id>0){
		if($q[0]['dm_roles']!=''){
			$r = $DB->select("SELECT * FROM {TABLE_RIGHTS} WHERE user_id = {$user_id} AND right_id IN ({$q[0]['dm_roles']})");
			if(!$r){
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
				echo json_encode(array("status"=>"error","msg"=>"Access denied"));
				die;
			}
		}
	}



	ini_set('zip.output_compression_level', 9);
	ob_start('ob_gzhandler');

	if(count($q)>0){
		if(floatval($_POST['dt'])==floatval($q[0]['dt'])){
			$q[0]['function'] = "";
			$q[0]['dt'] 	  = 0;
		}
		echo json_encode(array("status"=>"ok","data"=>$q[0]['function'],"dt"=>$q[0]['dt']));
		JSON::log_server($_POST['m'],$user_id,"ok");
	}else{
		JSON::log_server($_POST['m'],$user_id,"function no found");
		echo json_encode(array("status"=>"error"));
	}
