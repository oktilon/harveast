<?php
	require_once '../sess.php';
	$obj = json_encode($_REQUEST);
	$DB->prepare("INSERT INTO st_buffer_1c SET obj=:obj");
	$DB->bind("obj",$obj);
	$q=$DB->execute();

	if($q){
		echo json_encode(["status"=>"ok"]);
	}else{
		echo json_encode(["status"=>"error"]);
	}