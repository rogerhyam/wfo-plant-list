<?php

// this ensures that we have 
// the names of the facets and facet values
// cached in the session and refreshed every
// now and then.

require_once('config.php');
require_once('../include/SolrIndex.php');

$index = new SolrIndex();

// the facets cache

$facets_cache = @$_SESSION['facets_cache'];

if(!$facets_cache || @$_GET['facet_cache_refresh'] == 'true'){
    
    $facets_cache = array();

    $query = array(
        'query' => "kind_s:wfo-facet",
        'limit' => 10000
    );
  
    $docs  = $index->getSolrDocs($query);
    foreach($docs as $doc){
        $facets_cache[$doc->id] = json_decode($doc->json_t);
    }

    $_SESSION['facets_cache'] = $facets_cache;

}