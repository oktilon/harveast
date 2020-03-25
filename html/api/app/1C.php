<?php
require_once '../../sess.php';
  
$obj = json_encode($_REQUEST);
$DB->prepare("INSERT INTO st_buffer_1c SET obj=:obj");
$DB->bind("obj",$obj);
$DB->execute();

echo json_encode($_REQUEST);