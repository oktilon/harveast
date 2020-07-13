<?php
	require_once '../sess.php';
	$ret = new ScriptAnswer();

	try {
		if(!isset($_POST['obj'])) throw new Exception('obj undefined', 400);

		$obj = json_decode($_POST['obj']);

		if(!$obj) throw new Exception('json parse error ' . json_last_error_msg(), 400);

		$all = [];
		$ok  = true;
		foreach($obj as $type => $arr) {
			$q = $DB->prepare("INSERT INTO st_buffer_1c (obj, name) VALUES (:obj, :name);")
					->bind("obj", json_encode($obj, JSON_UNESCAPED_UNICODE))
					->bind("name", $type)
					->execute();
			if(!$q) $ok = false;
			$all[] = $q ? "{$type}=ok" : "{$type}={$DB->error}";
		}

		if($ok) {
			$ret->ok();
		} else {
			$ret->error('error', 500);
			$ret->err = implode('; ', $all);
		}
	}
	catch(Exception $e) {
		$ret->exception($e);
	}

	$ret->output();