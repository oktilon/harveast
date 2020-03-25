<?php
	require_once '../sess.php';
	$obj = json_encode($_REQUEST);
	$DB->prepare("INSERT INTO tmp_from_1c SET obj=:obj");
	$DB->bind("obj",$obj);
	$DB->execute();

	echo json_encode($_REQUEST);