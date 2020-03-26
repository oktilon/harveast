<?php
	require_once '../sess.php';
	
	if(!isset($_POST['obj'])){
		echo json_encode(["status"=>"error","err"=>"obj undefined"]);
		die;
	}
	if(!isset($_POST['name'])){
		echo json_encode(["status"=>"error","err"=>"name undefined"]);
		die;
	}
	
	$txt = json_encode($_POST);

	$DB->prepare("INSERT INTO st_buffer_1c SET obj=:obj, name=:name;");
	$DB->bind("obj", $_POST['obj']);
	$DB->bind("name",$_POST['name']);
	$q=$DB->execute();

	if($q){
		echo json_encode(["status"=>"ok"]);
	}else{
		echo json_encode(["status"=>"error","err"=>"insert false"]);
	}