<?php
	require_once '../sess.php';

	if(!isset($_POST['obj'])){
		echo json_encode(["status"=>"error","err"=>"obj undefined"]);
		die;
	}

	$DB->prepare("INSERT INTO st_buffer_1c SET obj=:obj;");
	$DB->bind("obj", $_POST['obj']);
	$q=$DB->execute();

	if($q){
		echo json_encode(["status"=>"ok"]);
	}else{
		echo json_encode(["status"=>"error","err"=>"insert false"]);
	}