<?php

/**
 * This is a wrapper around a SOLR doc representing taxa and names
 * It loads itself directly from the SOLR index.
 */
class RankObject{

    // for working the singletons
    private static $loaded = array();
    private string $name;

    private function __construct($name){
        $this->name = $name;
        self::$loaded[$this->name] = $this;
    }

    public static function getRank($name){
        if (isset(self::$loaded[$name])) return self::$loaded[$name];
        else return new RankObject($name);
    }

    public function getId(){
        return $this->name;
    }

    public function getTitle(){
        return $this->name;
    }

    public function getIndex(){
        global $ranks_table;
        return array_search($this->name, array_keys($ranks_table));
    }

    public function getChildren(){
        global $ranks_table;
        $kids = array();
        foreach($ranks_table[$this->name]['children'] as $kid_name){
            $kids[] = RankObject::getRank($kid_name);
        }
        return $kids;
    }

    public function getAka(){
        global $ranks_table;
        return $ranks_table[$this->name]['aka']; 
    }

    public function getAbbreviation(){
        global $ranks_table;
        return $ranks_table[$this->name]['abbreviation']; 
    }
    
    public function getPlural(){
        global $ranks_table;
        return $ranks_table[$this->name]['plural']; 
    }
    

}