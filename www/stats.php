<?php

require_once('config.php');
require_once('header.php');
require_once('../include/SolrIndex.php');
$index = new SolrIndex();

// facets for form

echo "<h2>Statistics</h2>";


$rank_facet = (object)array(
        "type" => "terms",
        "field" => "rank_s",
        "limit" => -1,
        "sort" => 'count',
        "mincount" => 1 // we want them all for the form
);


$role_facet = (object)array(
        "type" => "terms",
        "field" => "role_s",
        "limit" => -1,
        "sort" => 'count',
        "mincount" => 1 // we want them all for the form
);

$classification_facet = (object)array(
        "type" => "terms",
        "field" => "classification_id_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 1, // we want them all for the form
);

// each classification is split by roles
$classification_facet->facet = (object)array('role' => $role_facet);

// each rank is divided into classifications
$rank_facet->facet = (object)array('classification' => $classification_facet);

$facets = array();
$facets['rank'] = 


$query = array(
    'query' => "*:*",
    'facet' => (object)array("rank" => $rank_facet),
    'limit' => 0 // we are only interested in the facet data
);

$solr_response = $index->getSolrResponse($query);

if(!isset($solr_response->facets)){
    echo "Somethings up.";
    echo '<pre>';
    print_r($solr_response);
    exit;
}

$classificications_number = count($solr_response->facets->rank->buckets[0]->classification->buckets);
$colspan = $classificications_number * 5; // four roles in each classification 

echo "<h2>Overall Counts</h2>";
echo "<table>";

// top row just says |   | classifications |
echo "<tr><th>&nbsp;</th><th colspan=\"$colspan\" style=\"text-align: center;\">Classifications</th></tr>";

// next row says |  | 2021-06 | 2021-12 |  etc
// we can put the classifications headings in based on the first as it will be biggest
echo "<tr><th>&nbsp;</th>";
foreach ($solr_response->facets->rank->buckets[0]->classification->buckets as $classification) {
    echo "<th colspan=\"5\" style=\"text-align: center;\" >{$classification->val}</th>";
}
echo "</tr>";

// next row breaks classifications into accepted/synonym/unplaced/deprecated
// we can put the classifications headings in based on the first as it will be biggest
echo "<tr><th style=\"text-align: center;\" >Ranks</th>";
foreach ($solr_response->facets->rank->buckets[0]->classification->buckets as $classification) {
    echo "<th style=\"text-align: center;\" >total</th>";
    echo "<th style=\"text-align: center;\" >accepted</th>";
    echo "<th style=\"text-align: center;\" >synonym</th>";
    echo "<th style=\"text-align: center;\" >unplaced</th>";
    echo "<th style=\"text-align: center;\" >deprecated</th>";
}
echo "</tr>";


foreach ($solr_response->facets->rank->buckets as $rank) {
   echo "<tr>";
   echo "<th style=\"text-align: right;\" >{$rank->val}</th>";

   foreach ($rank->classification->buckets as $classification) {
        $total = number_format($classification->count, 0);
        echo "<td style=\"text-align: right;\" >{$total}</td>";

        $accepted = "-";
        $synonym = "-";
        $unplaced = "-";
        $deprecated = "-";

        foreach($classification->role->buckets as $role){

            //print_r($role);

            switch ($role->val) {
                case 'accepted':
                    $accepted = number_format($role->count, 0);
                    break;
                case 'synonym':
                    $synonym = number_format($role->count, 0);
                    break; 
                case 'unplaced':
                    $unplaced = number_format($role->count, 0);
                    break;  
                case 'deprecated':
                    $deprecated = number_format($role->count, 0);
                    break;
            }
        }

        echo "<td style=\"text-align: right;\" >{$accepted}</td>";
        echo "<td style=\"text-align: right;\" >{$synonym}</td>";
        echo "<td style=\"text-align: right;\" >{$unplaced}</td>";
        echo "<td style=\"text-align: right;\" >{$deprecated}</td>";

   }


   echo "</tr>";
}

echo "</table>";


//echo "<pre>";
//print_r($solr_response->facets);


// filtering the bryophytes in and out
// !name_descendent_path:Code/Plantae/Bryobiotina


?>


<?php

//print_r($solr_response);


require_once('footer.php');
?>