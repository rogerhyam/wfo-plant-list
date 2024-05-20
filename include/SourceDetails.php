<?php

/**
 * Holds the details necessary to 
 * decorate a source.
 * 
 */
class SourceDetails{

    private $sourceId = null;
    private $sourceCache = null;

    public function __construct($source_id){

        // convert solr index fields to facet ids
        $this->sourceId = preg_replace('/_ss$/', '',$source_id);
        $this->sourceId = preg_replace('/_s$/', '',$this->sourceId);

        // if we are passed just an interger then convert it to the id used
        // in solr
        if(is_int((int)$this->sourceId)){
            $this->sourceId = 'wfo-fs-' . $this->sourceId;
        }

        // we used the cached values if they exist
        if(isset($_SESSION['sources_cache']) && isset($_SESSION['sources_cache'][$this->sourceId])){
            $this->sourceCache = $_SESSION['sources_cache'][$this->sourceId];
        }

    }

    public function getName(){

        // we have it cached from the index
        if($this->sourceCache) return $this->sourceCache->name;

        // giving up and returning just the id
        $name = str_replace('_', ' ', $this->sourceId);
        return $name;

    }

    public function getLink(){
        if($this->sourceCache) return $this->sourceCache->link_uri;
        return null;
    }


}