<?php
	
	if(!file_exists('sess.inc') ){
		echo json_decode(["status"=>"error"]);
		die;
	}
	require_once 'sess.inc';
	session_start();

 	ini_set('zip.output_compression_level', 9);
	ob_start('ob_gzhandler');

	$m = isset($_REQUEST['m']) ? $_REQUEST['m'] : '';
	$r = isset($_REQUEST['r']) ? $_REQUEST['r'] : '';
	$u = isset($_SESSION['user']) ? $_SESSION['user'] : '';
 	if($m && $r && $u){
		JSON::parse(json_encode([ "m" => $m, "p" => [ "r" => $r ] ]));	
	}else{

		if(!$user) {
		 	JSON::parse(json_encode([ "m" => "db.Authorization", "p" => [] ]));
		} else {
			JSON::parse(json_encode([ "m" => "db.START", "p" => [] ]));
		}
	}
 