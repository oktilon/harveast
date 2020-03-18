<?php
	
	if(!file_exists('sess.php') ){
		header("Content-type: application/json; charset=utf-8");
		echo json_encode(["status"=>"error"]);
		die;
	}
	require_once 'sess.php';

 	ini_set('zip.output_compression_level', 9);
	ob_start('ob_gzhandler');

	$m = isset($_REQUEST['m']) ? $_REQUEST['m'] : '';
	$r = isset($_REQUEST['r']) ? $_REQUEST['r'] : '';
	$u = isset($_SESSION['user']) ? $_SESSION['user'] : '';
 	if($m && $r && $u){
		JSON::parse(json_encode([ "m" => $m, "p" => [ "r" => $r ] ]));	
	}else{

		if(!$u) {
		 	JSON::parse(json_encode([ "m" => "db.Authorization", "p" => [] ]));
		} else {
			JSON::parse(json_encode([ "m" => "db.START", "p" => [] ]));
		}
	}
 