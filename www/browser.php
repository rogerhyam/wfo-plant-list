<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/NameMatcher.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');

// we always render a record
$id = @$_GET['id']; // from the query string
if(!$id) $id = @$_SESSION['id']; // from the session
if(!$id) $id = 'wfo-9971000003'; // from the default - doesn't work if it doesn't exist i.e. earlier snapshots.
$_SESSION['id'] = $id;

// get this once as it is an index call
$classification_id_latest = PlantList::getLatestClassificationId();

// did they passed an name or placement id?
if(preg_match('/^wfo-[0-9]{10}$/', $id)){
    // they passed a pure name
    $name_id = $id;
    $classification_id = $classification_id_latest;
    $taxon_id = $id . "-" . $classification_id;
}else{
    // they passed qualified id
    $name_id = substr($id, 0, 14);
    $classification_id = substr($id, 15);
    $taxon_id = $id;
}

// load the objects for the name
$record = new TaxonRecord($taxon_id);

if($record->getIsName()){
    $name = $record;
}else{
    $name = $record->getName();
}

// also maintain which view they have
$view = @$_GET['view']; // from the query string
if(!$view) $view = @$_SESSION['view']; // from the session
if(!$view) $view = 'subtaxa'; // from the default
$_SESSION['view'] = $view;

?>

<ul class="nav nav-tabs" id="myTab" role="tablist" style="margin-bottom: 2em;">
    <li class="nav-item" role="presentation">
        <a class="nav-link active" id="view-tab" href="browser.php" type="button">View</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link " id="search-tab" href="browser_search.php" type="button">Search</a>
    </li>
</ul>


<h2>Nomenclature: <?php echo $name->getId() ?></h2>
<div style="border: none; width:100%; padding: 0px;">
    <?php
// just put the pictures in if we have them

if($name->getNomenclaturalReferences()){
    foreach ($name->getNomenclaturalReferences() as $ref) {
        if($ref->thumbnailUri){

            echo "
            <a href=\"{$ref->uri}\">
            <img 
                style=\"width: 80px; float: right;\" 
                src=\"{$ref->thumbnailUri}\" 
                alt=\"{$ref->label}\"
                title=\"{$ref->label}\"
                />
            </a>    
            ";
        }
    }
}
?>



    <p>
        <strong style="color: green; font-size: 152%;"><?php echo $name->getFullNameStringHtml() ?></strong>
        <?php echo $name->getCitationMicro() ?>
    </p>
    <?php

if($name->getNomenclaturalReferences()){
    echo "<h3>Nomenclatural References</h3>";
    echo "<ul>";
    foreach ($name->getNomenclaturalReferences() as $ref) {
        echo "<li>";
        echo  "<strong>{$ref->kind} : </strong><a href=\"{$ref->uri}\">{$ref->label}</a> {$ref->comment}";
        echo "</li>";
    }
    echo "</ul>";
}
?>
</div>
<hr />
<?php 

    // what we display depends on the role played
    switch ($record->getRole()) {
        case 'accepted':
            render_accepted($record, $classification_id);
            break;
        case 'synonym':
            render_synonym($record, $classification_id);
            break;
        case 'unplaced':
            render_unplaced($record, $classification_id);
            break;
        case 'deprecated':
            render_deprecated($record, $classification_id);
            break;
        default:
            echo "Unrecognised role: " . $record->getRole();
            break;
    }

    if($record->getTaxonomicReferences()){
    echo "<h3>Taxonomic References</h3>";
    echo "<ul>";
    foreach ($record->getTaxonomicReferences() as $ref) {
        echo "<li>";
        echo  "<strong>{$ref->kind} : </strong><a href=\"{$ref->uri}\">{$ref->label}</a> {$ref->comment}";
        echo "</li>";
    }
    echo "</ul>";

}

?>

<hr />

<?php
require_once('footer.php');


function render_accepted($record, $classification_id){

    echo "<h2>Taxonomy: Accepted in $classification_id</h2>";
    render_occurs_in($record, $classification_id);

    echo "<p>";
    echo "<strong>Placement: </strong>";
        $first = true;
        foreach(array_reverse($record->getPath()) as $anc){
            if(!$first) echo " &gt; ";
            else $first = false;
            render_name_link($anc, $classification_id);
        }
    echo "</p>";    

    if($record->getChildren()){
        echo "<h3>Subtaxa</h3>";
        echo "<ul>";
        foreach($record->getChildren() as $kid){
            echo "<li>";
            render_name_link($kid, $classification_id);
            echo "</li>";
        }
        echo "</ul>";
    }
 
    

    if($record->getSynonyms()){
        echo "<h3>Synonyms</h3>";
        echo "<ul>";
        foreach($record->getSynonyms() as $syn){
            echo "<li>";
            render_name_link($syn, $classification_id);
            echo "</li>";
        }
        echo "</ul>";
    }

    if($record->getRank() == 'genus' && $record->getUnplacedNames()){    
        echo "<h3>Unplaced Names</h3>";
        echo "<ul>";
        foreach($record->getUnplacedNames() as $un){
            echo "<li>";
            render_name_link($un, $classification_id);
            echo "</li>";
        }
        echo "</ul>";
    }

}
 
function render_synonym($record, $classification_id){

    echo "<h2>Taxonomy: Synonym in $classification_id</h2>";
    render_occurs_in($record, $classification_id);

    $accepted = new TaxonRecord($record->getAcceptedId());

    echo "<p>Accepted name is: <a href=\"browser.php?id={$accepted->getId()}\">{$accepted->getFullNameStringHtml()}</a></p>";

    echo "<h3>Placement</h3>";
    echo "<ul>";
        foreach(array_reverse($accepted->getPath()) as $anc){
            echo "<li>";
            render_name_link($anc, $classification_id);
            echo "</li>";
        }
        echo "<li>syn: {$accepted->getFullNameStringHtml()}</li>";
    echo "</ul>";
    
}

function render_unplaced($record, $classification_id){
    
    echo "<h2>Taxonomy: Unplaced in $classification_id</h2>";
    render_occurs_in($record, $classification_id);

    $names = $record->getAssociatedGenusNames();
        
    if($names){
        echo "<h3>Associated Names</h3>";
        echo "<ul>";
        foreach($names as $name){
            echo "<li>";
            render_name_link($name, $classification_id);
            echo "</li>";
        }
        echo "</ul>";
        echo "<p>These are genera with the same genus name in this classification but a taxonomist hasn't confirmed this as a good taxon or synonym.</p>";
    }
     

}

function render_deprecated($record, $classification_id){
    echo "<h2>Taxonomy: Deprecated in $classification_id</h2>";
    echo "<p>Do not use this name.</p>";
}

function  render_occurs_in($record, $classification_id){

    echo "<strong>Occurs in: </strong>";

    //echo "<pre>"; print_r($record->getUsages()) ; "</pre>";
    $first = true;
    foreach($record->getUsages() as $use){
        if(!$first) echo " : ";
        else $first = false;
        echo "<a href=\"browser.php?id={$use->getId()}\">{$use->getClassificationId()}</a>" ;
        
    }

}


?>


<?php 
require_once('footer.php');

?>