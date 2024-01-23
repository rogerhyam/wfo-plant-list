<?php

require_once('config.php');
require_once('header.php');
require_once('../include/SolrIndex.php');
$index = new SolrIndex();

/*

    Common facet definitions used below

*/

$rank_facet = (object)array(
        "type" => "terms",
        "field" => "rank_s",
        "limit" => -1,
        "sort" => 'count',
        "mincount" => 0 // we want them all for the form
);

$role_facet = (object)array(
        "type" => "terms",
        "field" => "role_s",
        "limit" => -1,
        "sort" => 'count',
        "mincount" => 0 // we want them all for the form
);

$classification_facet = (object)array(
        "type" => "terms",
        "field" => "classification_id_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 0, // we want them all for the form
);


/*

    First query is to get key figures for each classification

*/
$facets = array();

// row for each classification
$facets['classification'] = $classification_facet;

// by role
$facets['classification']->facet = (object)array('role' => $role_facet);

// by rank
$facets['classification']->facet->role->facet = (object)array('rank' => $rank_facet);

// so we can answer "give me the accepted species in classification x
$query = array(
    'query' => "*:*",
    'facet' => $facets,
    'limit' => 0 // we are only interested in the facet data
);

$solr_response = $index->getSolrResponse($query);

if(!isset($solr_response->facets)){
    echo "Somethings up.";
    echo '<pre>';
    print_r($solr_response);
    exit;
}

echo "<h2>Overview</h2>";

// build a more useful data structure
$overview = array();


foreach ($solr_response->facets->classification->buckets as $classification) {

    $o = (object)array();
    $o->name = $classification->val;
    $o->total_names = $classification->count;

    if(!$o->total_names) continue;

    // may not be present in early classifications
    $o->accepted_species = 0;
    $o->accepted_taxa = 0;
    $o->unplaced = 0; 
    $o->deprecated = 0;


    foreach($classification->role->buckets as $role){

        if($role->val == 'accepted'){
            $o->accepted_taxa = $role->count;

            foreach($role->rank->buckets as $rank){
                if($rank->val == 'species'){
                    $o->accepted_species = $rank->count;
                }
            }
        }

        if($role->val == 'synonym'){
            $o->synonyms = $role->count;
        }

        if($role->val == 'unplaced'){
            $o->unplaced = $role->count;
        }

        if($role->val == 'deprecated'){
            $o->deprecated = $role->count;
        }

    }



    // add in the %
    $o->accepted_species_proportion = $o->accepted_species / $o->total_names;
    $o->accepted_species_percentage = number_format($o->accepted_species_proportion * 100 , 2) . '%';

    $o->accepted_taxa_proportion = $o->accepted_taxa / $o->total_names;
    $o->accepted_taxa_percentage = number_format($o->accepted_taxa_proportion * 100 , 2) . '%';

    $o->synonyms_proportion = $o->synonyms / $o->total_names;
    $o->synonyms_percentage = number_format($o->synonyms_proportion * 100 , 2) . '%';
    
    $o->unplaced_proportion = $o->unplaced / $o->total_names;
    $o->unplaced_percentage = number_format($o->unplaced_proportion * 100 , 2) . '%';

    $o->deprecated_proportion = $o->deprecated / $o->total_names;
    $o->deprecated_percentage = number_format($o->deprecated_proportion * 100 , 2) . '%';
    
    $overview[] = $o;
    
}


// graph view
echo '<div style="height: 200px; padding: 0px; width: 80%; margin-bottom: 1em; position:relative;">';

$classification_width = ((1 / count($overview)) * 100) . '%';
$col_width = (1/7 * 100) .'%';

foreach ($overview as $o) {
    echo "<div style=\"float: left; padding: 0px; margin: 0px; height: 100%; width: $classification_width; red; border:none; vertical-align: bottom; position: relative;\">";
    
   
    $h = ($o->accepted_species_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'green');

    $h = ($o->accepted_taxa_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'orange');

    $h = ($o->synonyms_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'blue');

    $h = ($o->unplaced_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'brown');

    $h = ($o->deprecated_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'gray');

     echo "<div style=\"position: absolute; left: 0px; top: 0px; padding: 0.3em; margin: 0px; width: auto; border:none;\">$o->name</div>";


    echo '</div>';
}

echo '</div>';


// table view
echo '<table style="width: 80%">';

echo "<tr><th style=\"text-align: center;\" >Classification</th>";
echo "<th style=\"text-align: center; color: green; \" >Accepted Species</th>";
echo "<th style=\"text-align: center; color: orange; \" >Accepted Taxa</th>";   
echo "<th style=\"text-align: center; color: blue; \" >Synonyms</th>";
echo "<th style=\"text-align: center; color: brown; \" >Unplaced Names</th>";
echo "<th style=\"text-align: center; color: gray; \" >Deprecated Names</th>";
echo "<th style=\"text-align: center; color: black; \" >Total Names</th>";
echo "</tr>";

foreach ($overview as $classification) {
    echo "<tr>";
    echo "<th style=\"text-align: right;\" >{$classification->name}</th>";
    
    echo "<td style=\"text-align: right;\" >" . number_format($classification->accepted_species, 0) . "<br/>$classification->accepted_species_percentage</td>";
    echo "<td style=\"text-align: right;\" >" . number_format($classification->accepted_taxa, 0) . "<br/>$classification->accepted_taxa_percentage</td>";
    echo "<td style=\"text-align: right;\" >" . number_format($classification->synonyms, 0) . "<br/>$classification->synonyms_percentage</td>";
    echo "<td style=\"text-align: right;\" >" . number_format($classification->unplaced, 0) . "<br/>$classification->unplaced_percentage</td>";
    echo "<td style=\"text-align: right;\" >" . number_format($classification->deprecated, 0) . "<br/>$classification->deprecated_percentage</td>";
    
    echo "<td style=\"text-align: right;\" >" . number_format($classification->total_names, 0) . "<br/>&nbsp;</td>";
    echo "<tr>";
}

echo '</table>';

//echo "<pre>";
//print_r($solr_response->facets);
//echo "</pre>";


/*

    Now we do a filtered view of a single classification

*/

// clear the facets so we don't get recursion
unset($rank_facet->facet);
unset($role_facet->facet);
unset($classification_facet->facet);

// each rank is divided into roles
$facets = array();
$rank_facet->facet = (object)array('role' => $role_facet);
// the main breakdown is via rank
$facets['rank'] = $rank_facet;

// get a list of the classifications to filter by
$facets['classification'] = $classification_facet;

// now set up some filters
$filters = array();
if(@$_GET['classification']){
    $classification_selected = $_GET['classification'];
}else{
    $classification_selected =  WFO_DEFAULT_VERSION;
}
$filters[] = 'classification_id_s:' . $classification_selected;

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


// a form to filter by


echo "<h2>Summary for {$classification_selected}</h2>";


echo '<form method="GET" action="stats.php">';

echo '<select name="classification" onchange="this.form.submit()">';
foreach ($solr_response->facets->classification->buckets as $classification) {
    $selected = $classification_selected == $classification->val ? 'selected' : '';
    echo "<option $selected value=\"{$classification->val}\">{$classification->val}</option>";
}
echo '</select>';
echo '<input type="submit" value="Set Classification" />';
echo '<form/>';

echo "<table  style=\"width: 80%; margin-top: 1em;\">";

// next row breaks classifications into accepted/synonym/unplaced/deprecated
// we can put the classifications headings in based on the first as it will be biggest
echo "<tr><th style=\"text-align: center;\" >Ranks</th>";
   echo "<th style=\"text-align: center;\" >Total</th>";
    echo "<th style=\"text-align: center;\" >Accepted</th>";
    echo "<th style=\"text-align: center;\" >Synonyms</th>";
    echo "<th style=\"text-align: center;\" >Unplaced</th>";
    echo "<th style=\"text-align: center;\" >Deprecated</th>";
echo "</tr>";


foreach ($solr_response->facets->rank->buckets as $rank) {
   echo "<tr>";
   echo "<th style=\"text-align: right;\" >{$rank->val}</th>";
   
        // the total
        $total = number_format($rank->count, 0);
        echo "<td style=\"text-align: right;\" >{$total}</td>";
        

        $cols = array('accepted', 'synonym', 'unplaced', 'deprecated');

        foreach ($cols as $col) {
            foreach($rank->role->buckets as $role){
                if($role->val == $col){
                    $count = number_format($role->count);
                    echo "<td style=\"text-align: right;\" >{$count}</td>";
                    break;
                }
            }
        }

   }


   echo "</tr>";

echo "</table>";




// filtering the bryophytes in and out
// !name_descendent_path:Code/Plantae/Bryobiotina


?>


<?php

//print_r($solr_response);


require_once('footer.php');

function render_histogram_column($width, $height, $colour){

    echo "<div style=\"height: 100%; width: $width; position: relative; float: left; background-color: white;  padding: 0px; margin: 0px; border:none; \">";
    echo "<div style=\" position: absolute; bottom: 0; left: 0;  width:100%; height: $height; background-color: $colour; border:none; padding: 0px; margin: 0px;\" ></div>";
    echo "</div>";


}

?>