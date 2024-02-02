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
//$filters[] = "rank_s:species";
$filters[] = "-role_s:deprecated";


// flip this to switch between year
$filters[] = "-authors_string_s:\(*";
//$filters[] = "authors_string_s:\(*";

$facets = array();

$facets['years'] = (object)array(
      "type" => "terms",
      "field" => "publication_year_i",
      "sort" => "index",
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



//echo "<pre>";
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=decades_basionyms.csv");

$year_range = range(1750, 2040, 1);

// initialise the years
$out = array();
foreach($year_range as $year) $out[$year] = 0;

// populate them
foreach ($solr_response->facets->years->buckets as $year) {
    $out[$year->val] = $year->count;
}

// output decades or years
if(true){

    $matrix = array();

    foreach ($out as $year => $count) {
        $decade = floor($year/10)*10;
        $digit = round(($year/10 - floor($year/10))*10);
        
        // create an empty colum
        if(!isset($matrix[$decade])){
            $matrix[$decade] = array(0,0,0,0,0,0,0,0,0,0);
        }
        $matrix[$decade][$digit] = $count;
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
    foreach ($out as $year => $count) {
        echo "{$year},{$count}\n";
    }
}





//print_r($matrix);
//print_r($solr_response->facets->years->buckets);