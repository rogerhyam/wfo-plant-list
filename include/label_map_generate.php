<?php

function label_map_generate($lang){

    $index = new SolrIndex();

    $query = array(
        'query' => 'kind_s:wiki_cache AND id:Q*',
        'fields' => array("id", "label_{$lang}_s", "description_{$lang}_s"),
        'limit' => 10000
    );
    $docs = $index->getSolrResponse($query);
    $map = array();
    if(isset($docs->response->docs)){
        foreach($docs->response->docs as $doc){

            $vals = array();

            $label_prop = "label_{$lang}_s";
            if(isset($doc->{$label_prop})) $vals['label'] =  $doc->{$label_prop};

            $description_prop = "description_{$lang}_s";
            if(isset($doc->{$description_prop})) $vals['description'] =  $doc->{$description_prop};

            $map[$doc->id] = (object)$vals;
        }
    }

    return $map;

}