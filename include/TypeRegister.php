<?php

require_once('../include/ClassificationType.php');

/*

    Register of types because the schema must only have one instance 
    of each type in it.

*/
class TypeRegister {

    private static $classificationType;

    public static function classificationType(){
        return self::$classificationType ?: (self::$classificationType = new ClassificationType());
    }

}