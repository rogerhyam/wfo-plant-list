<?php
/*
    the index page is the default taxonomy search 
    page as that is what most people will want to do.
*/
require_once('config.php');
require_once('../include/SolrIndex.php');
require_once('../include/label_map_generate.php');
$index = new SolrIndex();

$taxon_id = $_GET['id'];

// get the doc for the taxon itself
$taxon = $index->getSolrDoc($taxon_id);

// get the ancestors so we can do a path
$ancestor_path = $taxon->name_ancestor_path;
$filters = array();
$filters[] = 'classification_id_s:' . WFO_DEFAULT_VERSION;
$filters[] = 'role_s:accepted'; 
$filters[] = '!id:' . $taxon->id; // not us 
$query = array(
    'query' => "name_ancestor_path:\"$ancestor_path\" ",
    'filter' => $filters,
    'fields' => array(
        'id',
        'wfo_id_s',
        'full_name_string_alpha_s'
    ),
    'sort' => "name_ancestor_path asc",
    'limit' => 100
);
$solr_response = $index->getSolrResponse($query);
$ancestors = $solr_response->response->docs;

// get the synonyms
$filters = array();
$filters[] = 'classification_id_s:' . WFO_DEFAULT_VERSION;
$filters[] = 'role_s:synonym'; 
$query = array(
    'query' => "accepted_id_s:\"{$taxon->id}\"",
    'filter' => $filters,
    'fields' => array(
        'id',
        'wfo_id_s',
        'full_name_string_html_s',
        'citation_micro_s'
    ),
    'sort' => "full_name_string_alpha_s asc",
    'limit' => 1000
);
$solr_response = $index->getSolrResponse($query);
$synonyms = $solr_response->response->docs;

// get the children
$filters = array();
$filters[] = 'classification_id_s:' . WFO_DEFAULT_VERSION;
$filters[] = 'role_s:accepted'; 
$query = array(
    'query' => "parent_id_s:\"{$taxon->id}\"",
    'filter' => $filters,
    'fields' => array(
        'id',
        'wfo_id_s',
        'full_name_string_html_s',
        'citation_micro_s',
        'rank_s'
    ),
    'sort' => "full_name_string_alpha_s asc",
    'limit' => 10000
);
// FIXME - this needs paging for mega genera?
$solr_response = $index->getSolrResponse($query);
$children = $solr_response->response->docs;

require_once('header.php');

echo '<div id="explore_taxon">';

if($ancestors){
    echo "<div id=\"bread_crumbs\">";
    $spacer = "";
    foreach($ancestors as $anc){
        echo "$spacer<a href=\"explore_taxon.php?id=$anc->id\">$anc->full_name_string_alpha_s</a>";
        $spacer = " &gt; ";
    }
    echo "</ul>";
    echo "</div>";
}

// right column
echo '<div style="float: right; width: 30%;">';
if($children){
    echo "<div id=\"subtaxa\">";
    echo "<h3 style=\"background-color: lightgray; margin: 0px; padding: 0.3em;\">Subtaxa</h3>";
    echo "<ul>";
    foreach($children as $kid){
        echo "<li>";
        echo "<a href=\"explore_taxon.php?id=$kid->id\">$kid->full_name_string_html_s</a>";
        if(isset($kid->citation_micro_s)) echo "<br/>$kid->citation_micro_s"; 
        echo "</li>";
    }
    echo "</ul>";
    echo "</div>";
}
if($synonyms){
    echo "<div id=\"synonyms\">";
    echo "<h3 style=\"background-color: lightgray; margin: 0px; padding: 0.3em;\">Synonyms</h3>";
    echo "<ul>";
    foreach($synonyms as $syn){
        echo "<li>";
        echo "<a href=\"name.php?id=$syn->id\">$syn->full_name_string_html_s</a>";
        if(isset($syn->citation_micro_s)) echo "<br/>$syn->citation_micro_s"; 
        echo "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "</div>";

// left column
echo '<div style="float: left; width: 65%;">';
echo "<h2>{$taxon->full_name_string_html_s} <sup style=\"font-size: 50%; color: green;\">{$taxon->wfo_id_s} </sup></h2>";
if(isset($taxon->citation_micro_s)) echo "<p>$taxon->citation_micro_s</p>";

// render the facets - what we are here for.

$label_map = label_map_generate('en'); // we need to translate the labels

echo '<div style="border: solid 1px gray; margin-bottom: 1em;" >';
echo "<h3 style=\"background-color: lightgray; margin: 0px; padding: 0.3em;\">Facets</h3>";
foreach($taxon as $prop => $value){
    $matches = array();
    if(!preg_match('/^(Q[0-9]+)_ss$/', $prop, $matches)) continue;

    $facet_q = $matches['1'];
    $facet_label = ucfirst($label_map[$facet_q]->label);
    $facet_description = ucfirst($label_map[$facet_q]->description);
    $facet_values = $value;

    echo "<p style=\"margin-left: 0.3em;\">
     <strong>
    <a target=\"wikidata\" href=\"https://www.wikidata.org/entity/{$facet_q}\" title=\"$facet_description\" >{$facet_label}</a>: </strong>";
    $separator = '';
    foreach ($facet_values as $val_q) {
        $label = ucfirst($label_map[$val_q]->label);
        $description = ucfirst($label_map[$val_q]->description);
        echo $separator;
        echo "<a target=\"wikidata\" href=\"https://www.wikidata.org/entity/{$val_q}\" title=\"$description\" >$label</a>";
       $separator = "; ";
    }
    echo ".</p>";
}
echo "</div>";

// references - make them into something more useful
$references = array();
if($taxon->reference_uris_ss){
    for ($i=0; $i < count($taxon->reference_uris_ss) ; $i++) { 
               $references[$taxon->reference_labels_ss[$i]] = array(
                    'kind' => $taxon->reference_kinds_ss[$i],
                    'label' => $taxon->reference_labels_ss[$i],
                    'uri' => $taxon->reference_uris_ss[$i],
                    'thumbnail' => $taxon->reference_thumbnail_uris_ss[$i],
                    'comment' => $taxon->reference_comments_ss[$i],
                    'context' => $taxon->reference_contexts_ss[$i],
                );
    }
}

// we should be in alphabetical order
ksort($references);
render_references($references);


echo '</div>';


echo '</div>'; // end explore_taxon

?>


<?php
require_once('footer.php');


function render_references($references){

    if(!$references){
        echo "<!-- no refs -->";
        return;
    }

    render_reference_block($references, 'name', 'Nomenclatural References' );
    render_reference_block($references, 'taxon', 'Taxonomic Sources' );
    
}

function render_reference_block($references, $context, $title){

    echo '<div style="border: solid 1px gray; margin-bottom: 1em;" >';
    echo "<h3 style=\"background-color: lightgray; margin: 0px; padding: 0.3em;\">$title</h3>";
    echo '<ul style="list-style: none; padding-left: 0px; margin-top: 0px;">';
    render_reference_kind($references, $context, 'person');
    render_reference_kind($references, $context, 'literature');
    render_reference_kind($references, $context, 'specimen');
    render_reference_kind($references, $context, 'database');
    echo "</ul>";
    echo "</div>"; // reference block

}

function render_reference_kind($references, $context, $kind){

    foreach ($references as $ref) {

        // skip inappropriate ones
        if($ref['kind'] != $kind || $ref['context'] != $context) continue;
        
        echo "<li style=\"position:relative; clear:both;height:50px; padding-top: 0.3em; padding-bottom: 0.3em; border-top: 1px gray solid;\">";
        echo '<div style="width:50px; height:50px; float:left; margin-right: 0.8em; vertical-align: middle; text-align: center;">';
        if($ref['thumbnail'] != '-'){
            echo "<img style=\"max-height: 100%;\"src=\"{$ref['thumbnail']}\" />";
        }else{
            echo "<img style=\"max-height: 100%;\"src=\"images/{$kind}_default.jpg\" />";;
        }
        echo '</div>';// picture surround
        echo"<a target=\"_new\" href=\"{$ref['uri']}\">{$ref['label']}</a>";
        echo "<br/>{$ref['comment']}";
        echo "</li>";
    }

}


?>