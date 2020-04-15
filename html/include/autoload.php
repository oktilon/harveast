<?php
function autoloadBase ($className){
    $filePath = PATH_INC. DIRECTORY_SEPARATOR . $className . '.class.php';
    if(file_exists($filePath)) {
        require_once($filePath);
        return;
    }

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
    $filePath = PATH_INC . DIRECTORY_SEPARATOR . $fl . $clsName . '.class.php';

    if(JSON::loadClass($clsName)) return;

    if (file_exists($filePath)) {
        require_once($filePath);
        return;
    }
}

spl_autoload_register ('autoloadBase');