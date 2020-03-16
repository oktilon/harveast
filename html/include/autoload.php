<?php
if(!isset($_SESSION)) 
{ 
    session_start(); 
} 

spl_autoload_register ('autoload');
function autoload ($className){
  $fileName = $className . '.class.php';
  include PATH_INC. DIRECTORY_SEPARATOR .$fileName;
}