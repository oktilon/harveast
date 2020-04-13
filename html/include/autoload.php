<?php
function autoloadBase ($className){
    $filePath = PATH_INC. DIRECTORY_SEPARATOR . $className . '.class.php';
    if(file_exists($filePath)) {
        require_once($filePath);
        return;
    }

    $ret = JSON::parse(json_encode(["m"=>($className).'.class',"p"=>[]]));
    if(!$ret) return;

    $ret = JSON::parse(json_encode(["m"=>strtolower($className).'class',"p"=>[]]));
    if(!$ret) return;

    // Try SplitName by capitals
    $classParts = array('');
    $cur_part   = 0;
    $len        = strlen($className);
    for($i = 0; $i < $len; $i++) {
        $j = strlen($classParts[$cur_part]);
        if($j > 1 && ord($className[$i]) < 91) {
            $cur_part++;
            $classParts[] = '';
        }
        $classParts[$cur_part] .= $className[$i];
    }

    $fl = '';
    if(is_dir(PATH_INC . DIRECTORY_SEPARATOR . $classParts[0])) {
        $fl = array_shift($classParts) . DIRECTORY_SEPARATOR;
    }
    $clsName = strtolower(implode('_', $classParts));
    $filePath = PATH_INC . DIRECTORY_SEPARATOR . $fl . $clsName . '.class.php';

    $ret = JSON::parse(json_encode(["m"=>$clsName . '.class',"p"=>[]]));
    if(!$ret) return;

    if (file_exists($filePath)) {
        require_once($filePath);
        return;
    }
}

spl_autoload_register ('autoloadBase');