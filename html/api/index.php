<?php 

$url 		= substr($_SERVER['REQUEST_URI'],strrpos($_SERVER['SCRIPT_NAME'],'/')+1);
$exploded 	= explode(DIRECTORY_SEPARATOR,$url);
$dir 		= '';
foreach ($exploded as $key => $expl) {
	# code...
	if(file_exists($dir.$expl.'.php')){
		include $dir.$expl.'.php';
		die;
	}else{
		$dir = $dir.$expl.DIRECTORY_SEPARATOR;
	}
}
 
