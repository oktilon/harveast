<?php

function autoloadTryFile($fileName) {
    $filePath = PATH_INC. DIRECTORY_SEPARATOR . $fileName;
    if(file_exists($filePath)) {
        require_once($filePath);
        return true;
    }
    if(defined('ALT_MODULES')) {
        $filePath = ALT_MODULES . $fileName;
        if(file_exists($filePath)) {
            require_once($filePath);
            return true;
        }
    }
    return false;
}

function autoloadBase ($className){
    $fileName = $className . '.class.php';
    if(autoloadTryFile($fileName)) return;

    $fileName = strtolower($className) . '.class.php';
    if(autoloadTryFile($fileName)) return;

    if(JSON::loadClass($className)) return;

    if(JSON::loadClass(strtolower($className))) return;

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
    $fileName = $fl . $clsName . '.class.php';
    if(autoloadTryFile($fileName)) return;

    if(JSON::loadClass($clsName)) return;

}

spl_autoload_register ('autoloadBase');