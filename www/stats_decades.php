<?php

/*

    Generates data for ANOVA of decades or years for graph

    This is a run once to generate the data for an R analysis.
    Only kept incase we need to go back and do something similar.

*/

require_once('config.php');
require_once('../include/SolrIndex.php');
$index = new SolrIndex();

// now set up some filters
$filters = array();

// always filter on a classification
$filters[] = 'classification_id_s:9999-01';

// we are only interested in accepted species.
$filters[] = "rank_s:species";
//$filters[] = "-role_s:deprecated";


// flip this to switch between year
//$filters[] = "-authors_string_s:\(*";
$filters[] = "authors_string_s:\(*";

$facets = array();

$facets['years'] = (object)array(
      "type" => "range",
      "field" => "publication_year_i",
      "start" => 1750,
      "end" => 2024,
      "gap" => 1,
      "mincount" => 0,
      "other" => "all",
      "limit" => -1
);

$query = array(
    'query' => "*:*",
    'facet' => $facets,
    'filter' => $filters,
    'limit' => 0 // we are only interested in the facet data
);

$solr_response = $index->getSolrResponse($query);

if(!isset($solr_response->facets)){
    echo "Somethings up.";
    echo '<pre>';
    print_r($solr_response);
    exit;
}



// echo "<pre>";

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=years_basionyms.csv");



// output decades or years
if(false){

    $matrix = array();

    foreach ($solr_response->facets->years->buckets as $year) {
        $decade = floor($year->val/10)*10;
        $matrix[$decade][] = $year->count;
    }

    echo 'Year,'  . implode(',', array_keys($matrix)) . "\n";
    for ($i=0; $i < 10; $i++) { 
        echo $i . ",";
        $separator = '';
        foreach ($matrix as $decade) {
            echo $separator . $decade[$i];
            $separator = ',';
        }
        echo "\n";
    }
}else{
    echo "Year,Count\n";
    foreach ($solr_response->facets->years->buckets as $year) {
        echo "{$year->val},{$year->count}\n";
    }
}





//print_r($matrix);
//print_r($solr_response->facets->years->buckets);