<?php

require_once('../include/ClassificationType.php');
require_once('../include/TaxonNameType.php');
require_once('../include/TaxonConceptType.php');
require_once('../include/IdentifierType.php');
require_once('../include/ReferenceType.php');

/*

    Register of types because the schema must only have one instance 
    of each type in it.

*/
class TypeRegister {

    private static $classificationType;
    private static $taxonNameType;
    private static $taxonConceptType;
    private static $identifierType;
    private static $referenceType;

    public static function classificationType(){
        return self::$classificationType ?: (self::$classificationType = new ClassificationType());
    }

    public static function identifierType(){
        return self::$identifierType ?: (self::$identifierType = new IdentifierType());
    }
    public static function referenceType(){
        return self::$referenceType ?: (self::$referenceType = new ReferenceType());
    }

    public static function taxonNameType(){
        return self::$taxonNameType ?: (self::$taxonNameType = new TaxonNameType());
    }

    public static function taxonConceptType(){
        return self::$taxonConceptType ?: (self::$taxonConceptType = new TaxonConceptType());
    }
}