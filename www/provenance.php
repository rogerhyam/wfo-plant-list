<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/SourceDetails.php');

$provs = json_decode($_GET['prov']);


foreach($provs->facet_value->provenance as $prov){

    $matches = array();
    preg_match('/(wfo-[0-9]{10})-s-([0-9]+)-(.+)/', $prov, $matches);

    $source = new SourceDetails($matches[2]);

    switch ($matches[3]) {
        case 'direct':
            $phrase = "based on direct scoring of taxon name";
            break;
        case 'synonym':
            $phrase = "based on scoring of synonym";
            break;
        case 'ancestor':
            $phrase = "based on inheritance from of taxon name";
            break;
        default:
            $phrase = 'unrecognised';
            break;
    }

    $record = new TaxonRecord($matches[1]);


    echo "<p>{$phrase} <a target=\"wfo-plantlist\" href=\"https://list.worldfloraonline.org/{$record->getId()}\">{$record->getFullNameStringHtml()}</a></p>";

    echo "<p>by ";
    if($source->getLink()){
       echo "<strong><a target=\"wfo-source\" href=\"{$source->getLink()}\">{$source->getName()}</a></strong>";
    }else{
       echo "<strong>{$source->getName()}</strong>";
    }
    echo "</p>";


    echo "<hr/>";

}

?>