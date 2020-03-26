<?php
	require_once '../sess.php';
	if(!isset($_POST['obj'])) die;
	
	$txt = json_encode($_POST);

	$DB->prepare("INSERT INTO st_buffer_1c SET obj=:obj,txt=:txt");
	$DB->bind("obj",$_POST['obj']);
	$DB->bind("txt",$txt);
	$q=$DB->execute();

	if($q){
		echo json_encode(["status"=>"ok"]);
	}else{
		echo json_encode(["status"=>"error"]);
	}