<?php

/**
 * Holds the details necessary to 
 * decorate a facet and its facet values.
 * 
 */
class FacetDetails{

    private $facetId = null;
    private $facetCache = null;

    public function __construct($facet_id){
        // convert solr index fields to facet ids
        $this->facetId = preg_replace('/_ss$/', '',$facet_id);
        $this->facetId = preg_replace('/_s$/', '',$this->facetId);

        // we used the cached values if they exist
        if(isset($_SESSION['facets_cache']) && isset($_SESSION['facets_cache'][$this->facetId])){
            $this->facetCache = $_SESSION['facets_cache'][$this->facetId];
        }

    }

    public function getFacetName(){

        // we have it cached from the index
        if($this->facetCache) return $this->facetCache->name;

        // it isn't cached so we make it from the id
        $matches = array();
        if(preg_match('/^placed_in_(.+)/', $this->facetId, $matches)){
            return ucfirst($matches[1]);
        }

    
        // giving up and returning just the id
        $name = str_replace('_', ' ', $this->facetId);
        $name = ucfirst($name);
        return $name;

    }

    public function getFacetValueName($value_id){

        if($this->facetCache && isset($this->facetCache->facet_values->{$value_id})) return $this->facetCache->facet_values->{$value_id}->name;
        return $value_id;
    }


    public function getFacetValueLink($value_id){
        if($this->facetCache && isset($this->facetCache->facet_values->{$value_id})) return $this->facetCache->facet_values->{$value_id}->link_uri;
        return null;
    }

    public function getFacetValueCode($value_id){
        if($this->facetCache && isset($this->facetCache->facet_values->{$value_id})) return $this->facetCache->facet_values->{$value_id}->code;
        return null;
    }


}