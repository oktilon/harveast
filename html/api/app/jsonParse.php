<?php 
require_once '../sess.inc';
session_start();

if(!isset($_POST['obj'])){
	echo json_encode(["status"=>"error"]);
	die;
}

$obj = json_decode($_POST['obj']);
JSON::parse($_POST['obj']);



	 
