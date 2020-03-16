<?php
	
	if(!file_exists('sess.inc') ){
		echo json_decode(["status"=>"error"]);
		die;
	}
	require_once 'sess.inc';
	session_start();

 	ini_set('zip.output_compression_level', 9);
	ob_start('ob_gzhandler');

 	if($_REQUEST['m']&&$_REQUEST['r']&&$_SESSION['user']){
		JSON::parse(json_encode(["m"=>$_REQUEST['m'], "p"=>["r"=>$_REQUEST['m']]]));	
	}else{

		if(!isset($_SESSION['user']))
		 	JSON::parse(json_encode(["m"=>"db.Authorization", "p"=>[] ]));
		else
			JSON::parse(json_encode(["m"=>"db.START", "p"=>[] ]));
	}
 