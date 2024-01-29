<?php

require_once('config.php');
require_once('header.php');
require_once('../include/SolrIndex.php');
$index = new SolrIndex();

if(@$_GET['classification']){
    $classification_selected = $_GET['classification'];
}else{
    $classification_selected =  WFO_DEFAULT_VERSION;
}

// the filters are hierachical - we ignore lower ones if higher ones aren't set
$phylum_selected = @$_GET['phylum'];
if($phylum_selected) $order_selected = @$_GET['order'];
else $order_selected = false;
if($order_selected) $family_selected = @$_GET['family'];
else $family_selected = false;


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
        "mincount" => 0 // we want them all for the form
);

$family_facet = (object)array(
        "type" => "terms",
        "field" => "placed_in_family_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 1 // we want them all for the form
);

$order_facet = (object)array(
        "type" => "terms",
        "field" => "placed_in_order_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 1 // we want them all for the form
);

$phylum_facet = (object)array(
        "type" => "terms",
        "field" => "placed_in_phylum_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 1 // we want them all for the form
);

$activity_decades = (object)array(
      "type" => "range",
      "field" => "publication_year_i",
      "start" => 1750,
      "end" => date("Y"),
      "gap" => 10,
      "mincount" => 0,
      "other" => "all"
);



/*
$activity_facet = (object)array(
        "type" => "terms",
        "field" => "placed_in_phylum_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 1, // we want them all for the form
);

*/



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

    if($classification_selected == $classification->name){
        $bg_colour = 'lightyellow';
    }else{
        $bg_colour = 'white';
    }

    echo "<tr style=\"background-color: $bg_colour;\">";
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
$facets['family'] = $family_facet;
$facets['order'] = $order_facet;
$facets['phylum'] = $phylum_facet;
$facets['activity_decades'] = $activity_decades;

// now set up some filters
$filters = array();

// always filter on a classification
$filters[] = 'classification_id_s:' . $classification_selected;

// can filter on some ranks
if($phylum_selected) $filters[] = 'placed_in_phylum_s:"' . $phylum_selected . '"';
if($order_selected) $filters[] = 'placed_in_order_s:"' . $order_selected . '"';
if($family_selected) $filters[] = 'placed_in_family_s:"' . $family_selected . '"';

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

// pick a classification
echo '<strong>Data Release:&nbsp;</strong><select name="classification" onchange="this.form.submit()">';
foreach ($solr_response->facets->classification->buckets as $classification) {
    if( $classification->val == '9999-99') continue;
    $selected = $classification_selected == $classification->val ? 'selected' : '';
    echo "<option $selected value=\"{$classification->val}\">{$classification->val}</option>";
}
echo '</select>';


// pick a phylum
echo '&nbsp;<select name="phylum" onchange="this.form.submit()">';
echo "<option value=\"\">Placed, Unplaced and Deprecated</option>";
foreach ($solr_response->facets->phylum->buckets as $phylum) {
    $c = number_format($phylum->count, 0);
    $selected = $phylum_selected == $phylum->val ? 'selected' : '';
    echo "<option $selected  value=\"{$phylum->val}\">Placed in: {$phylum->val} ($c)</option>";
}
echo '</select>';

// pick an order
if($phylum_selected){
    echo '&nbsp;<select name="order" onchange="this.form.submit()">';
    echo "<option value=\"\">~ Any Order ~</option>";
    foreach ($solr_response->facets->order->buckets as $order) {
        $c = number_format($order->count, 0);
        $selected = $order_selected == $order->val ? 'selected' : '';
        echo "<option $selected  value=\"{$order->val}\">{$order->val} ($c)</option>";
    }
    echo '</select>';
}


// pick a family
if($order_selected){
    echo '&nbsp;<select name="family" onchange="this.form.submit()">';
    echo "<option value=\"\">~ Any Family ~</option>";
    foreach ($solr_response->facets->family->buckets as $family) {
        $c = number_format($family->count, 0);
        $selected = $family_selected == $family->val ? 'selected' : '';
        echo "<option $selected  value=\"{$family->val}\">{$family->val} ($c)</option>";
    }
    echo '</select>';
}

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
            if(isset($rank->role->buckets)){
                foreach($rank->role->buckets as $role){
                    if($role->val == $col){
                        $count = number_format($role->count);
                        echo "<td style=\"text-align: right;\" >{$count}</td>";
                        break;
                    }
                }
            }else{
                echo "<td style=\"text-align: right;\" >-</td>";
            }

        }

   }


   echo "</tr>";

echo "</table>";


// if we have nomenclatural activity we add it in
// not available for earlier classifications
$total_pubs = $solr_response->facets->activity_decades->between->count;
if($total_pubs > 0){

    $max = 0;
    foreach ($solr_response->facets->activity_decades->buckets as $decade) {
        if($decade->count > $max) $max = $decade->count;
    }

/*
    echo "<pre>";
    echo print_r($solr_response->facets->activity_decades->buckets);
    echo "</pre>";
*/
    echo "<h4>Nomenclatural Activity</h4>";
    echo "<p>Creation of nomenclatural publications can give an indication of active taxonomic work. This table shows the years of publication for " . number_format($total_pubs, 0) . " names that we have data for in the current selection.</p>";

    echo "<table  style=\"width: 80%; margin-top: 1em;\">";
    echo "<tr>";
    echo "<th>Decade</th>";
    echo "<th>Publication Count</th>";
    echo "</tr>";
    foreach ($solr_response->facets->activity_decades->buckets as $decade) {
        echo "<tr>";
        echo "<th>{$decade->val}</th>";
        echo '<td style="width: 100%; padding: 0px;">';
        $percentage = ($decade->count/$max * 100) . "%";
        echo '<div style="width: '. $percentage.'; height: 100%; display: inline-block; padding-left: 0px; padding-right: 0px;  background-color: lightgray; color: black; border: none; text-align: right;">&nbsp;' . number_format($decade->count, 0) . "&nbsp;</div>";
        echo "</td>";
        echo "</tr>";
    }

    echo "</table>";

}



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