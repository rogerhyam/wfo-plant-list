<?php

require_once('config.php');
require_once('../include/SolrIndex.php');
$index = new SolrIndex();

//echo "<pre>";
//print_r($_GET);

// Build a list of the facets and values
$facet_filters = array();

foreach ($_GET as $key => $value) {
    $matches = array();
    if(preg_match('/(Q[0-9]+)_(Q[0-9]+)/', $key, $matches) ){
        if(isset($facet_filters[$matches[1]])){
            $facet_filters[$matches[1]][] = $matches[2];
        }else{
            $facet_filters[$matches[1]] = array($matches[2]);
        }   
    }
}


// let's build a query!

$name = trim( @$_GET['search']);
$name = ucfirst($name); // all names start with an upper case letter
$name = str_replace(' ', '\ ', $name);
$name = $name . "*";

$filters = array();
$filters[] = 'classification_id_s:' . WFO_DEFAULT_VERSION;
$filters[] = 'role_s:accepted'; 

// filter by facets selected
foreach($facet_filters as $facet => $values ){
    $filters[] = "{$facet}_ss: (" . implode(' AND ', $values) . ')'; 
}

// facets for form
$facets = array();
// $facet_q_numbers is defined in config.php
foreach($facet_q_numbers as $fq){
    $facets[$fq] = (object)array(
        "type" => "terms",
        "field" => "{$fq}_ss",
        "limit" => -1,
        "sort" => 'count',
        "missing" => true, // include the 'other' bucket
        "mincount" => 1 // we want them all for the form
    );
}


$query = array(
    'query' => "all_names_alpha_ss:$name",
    'filter' => $filters,
    'fields' => array(
        'id',
        'wfo_id_s',
        'full_name_string_html_s',
        'role_s',
        'all_names_alpha_ss',
        'name_path_s',
        'facet'
    ),
    'sort' => "strdist(\"$name\",full_name_string_alpha_s,edit) desc, full_name_string_alpha_s asc",
    'facet' => $facets,
    'limit' => 10
);



// we add the original form values into the response
// from solr so we can repopulate the form easily.
$solr_response = $index->getSolrResponse($query);
$solr_response->getParams = $_GET;
$solr_response->querySent = $query;

header("Content-Type: application/json");
echo json_encode($solr_response);