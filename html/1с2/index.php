<?php
	require_once '../sess.php';
	$DB->prepare("INSERT INTO tmp_from_1c SET obj=:obj");
	$DB->bind("obj","ok");
	$DB->execute();

	echo json_encode(["status"=>"ok"]);