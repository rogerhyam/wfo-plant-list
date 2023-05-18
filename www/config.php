<?php

require_once('../vendor/autoload.php'); // composer libraries
require_once('../../wfo_secrets.php'); // outside the github root

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL & ~E_NOTICE);
error_reporting(E_ALL);
session_start();

define('WFO_SERVICE_VERSION','2.0.1');


// Location of the solr server
define('SOLR_QUERY_URI','http://localhost:8983/solr/wfo2');

// used for lookups and other services that don't want to 
// trouble themselves with many versions of backbone
// will normally be set to the most recent.
define('WFO_DEFAULT_VERSION','2022-12');

define('SOLR_USER', $solr_user); // from wfo_secrets.php
define('SOLR_PASSWORD', $solr_password); // from wfo_secrets.php

// used all over to generate guids
function get_uri($taxon_id){
  if(php_sapi_name() === 'cli'){
    return "https://list.worldfloraonline.org/" . $taxon_id;
  }else{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $taxon_id;
  }
}

// copy of ranks table from management app
// FIXME need a way of sharing this efficiently between 
// repositories!
$ranks_table = array(

  "code" => array(
    "children" => array("kingdom", "phylum"), // permissible ranks for child taxa
    "abbreviation" => "ICN", // official abbreviation
    "plural" => "Code",
    "aka" => array() // alternative representations for import
  ),

  "kingdom" => array(
    "children" => array("phylum"), // permissible ranks for child taxa
    "abbreviation" => "K.", // official abbreviation
    "plural" => "Kingdoms",
    "aka" => array() // alternative representations for import
  ),

  "subkingdom" => array(
    "children" => array("phylum", "class", "order","family", "superorder"), // permissible ranks for child taxa
    "abbreviation" => "subK.", // official abbreviation
    "plural" => "Subkingdoms",
    "aka" => array() // alternative representations for import
  ),
  
  "phylum" => array(
    "children" => array("class", "order", "family", "superorder"), // permissible ranks for child taxa
    "abbreviation" => "P.", // official abbreviation
    "plural" => "Phyla",
    "aka" => array() // alternative representations for import
  ),

  "class" => array(
    "children" => array("subclass", "order", "family","superorder"), // permissible ranks for child taxa
    "abbreviation" => "C.", // official abbreviation
    "plural" => "Classes",
    "aka" => array() // alternative representations for import
  ),

  "subclass" => array(
    "children" => array("order", "family", "superorder"), // permissible ranks for child taxa
    "abbreviation" => "subc.", // official abbreviation
    "plural" => "Subclasses",
    "aka" => array() // alternative representations for import
  ),

  "superorder" => array(
    "children" => array("order"), // permissible ranks for child taxa
    "abbreviation" => "supo.", // official abbreviation
    "plural" => "Superorders",
    "aka" => array() // alternative representations for import
  ),

  "order" => array(
    "children" => array("suborder", "family"), // permissible ranks for child taxa
    "abbreviation" => "O.", // official abbreviation
    "plural" => "Orders",
    "aka" => array() // alternative representations for import
  ),

  "suborder" => array(
    "children" => array("family"), // permissible ranks for child taxa
    "abbreviation" => "subo.", // official abbreviation
    "plural" => "Suborders",
    "aka" => array() // alternative representations for import
  ),

  "family" => array(
    "children" => array("supertribe", "subfamily", "tribe", "genus"), // permissible ranks for child taxa
    "abbreviation" => "Fam.", // official abbreviation
    "plural" => "Families",
    "aka" => array() // alternative representations for import
  ),

  "subfamily" => array(
    "children" => array("supertribe", "tribe", "genus"), // permissible ranks for child taxa
    "abbreviation" => "subfam.", // official abbreviation
    "plural" => "Subfamilies",
    "aka" => array() // alternative representations for import
  ),

  "supertribe" => array(
    "children" => array("tribe"), // permissible ranks for child taxa
    "abbreviation" => "supt.", // official abbreviation
    "plural" => "Supertribes",
    "aka" => array('supertrib.') // alternative representations for import
  ),

  "tribe" => array(
    "children" => array("subtribe", "genus"), // permissible ranks for child taxa
    "abbreviation" => "t.", // official abbreviation
    "plural" => "Tribes",
    "aka" => array('trib.') // alternative representations for import
  ),

  "subtribe" => array(
    "children" => array("genus"), // permissible ranks for child taxa
    "abbreviation" => "subt.", // official abbreviation
    "plural" => "Subtribes",
    "aka" => array('subtrib.', 'subtrib') // alternative representations for import
  ),

  "genus" => array(
    "children" => array("subgenus", "section", "series", "species"), // permissible ranks for child taxa
    "abbreviation" => "gen.", // official abbreviation
    "plural" => "Genera",
    "aka" => array() // alternative representations for import
  ),

  "subgenus" => array(
    "children" => array("section", "series", "species"), // permissible ranks for child taxa
    "abbreviation" => "subgen.", // official abbreviation
    "plural" => "Subgenera",
    "aka" => array() // alternative representations for import
  ),

  "section" => array(
    "children" => array("subsection", "series", "species"), // permissible ranks for child taxa
    "abbreviation" => "sect.", // official abbreviation
    "plural" => "Sections",
    "aka" => array("sect",  "nothosect.") // alternative representations for import
  ),
  
  "subsection" => array(
    "children" => array("series", "species"), // permissible ranks for child taxa
    "abbreviation" => "subsect.", // official abbreviation
    "plural" => "Subsections",
    "aka" => array() // alternative representations for import
  ),

  "series" => array(
    "children" => array("subseries", "species"), // permissible ranks for child taxa
    "abbreviation" => "ser.", // official abbreviation
    "plural" => "Series",
    "aka" => array() // alternative representations for import
  ),

  "subseries" => array(
    "children" => array("species"), // permissible ranks for child taxa
    "abbreviation" => "subser.", // official abbreviation
    "plural" => "Subseries",
    "aka" => array() // alternative representations for import
  ),

  "species" => array(
    "children" => array("subspecies", "variety", "form", "prole"), // permissible ranks for child taxa
    "abbreviation" => "sp.", // official abbreviation
    "plural" => "Species",
    "aka" => array("nothospecies", "spec.") // alternative representations for import
  ),

  "subspecies" => array(
    "children" => array("variety", "form", "prole"), // permissible ranks for child taxa
    "abbreviation" => "subsp.", // official abbreviation
    "plural" => "Subspecies",
    "aka" => array("nothosubspecies", "nothosubsp.", "subsp.", "subsp", "ssp", "ssp.", "subspec.") // alternative representations for import
  ),

  "prole" => array(
    "children" => array(), // permissible ranks for child taxa
    "abbreviation" => "prol.", // official abbreviation
    "plural" => "Proles",
    "aka" => array("race", "proles") // alternative representations for import
  ),

  "variety" => array(
    "children" => array("subvariety", "form", "prole"), // permissible ranks for child taxa
    "abbreviation" => "var.", // official abbreviation
    "plural" => "Varieties",
    "aka" => array("nothovar.", "var.", "var") // alternative representations for import
  ),

  "subvariety" => array(
    "children" => array("form"), // permissible ranks for child taxa
    "abbreviation" => "subvar.", // official abbreviation
    "plural" => "Subvarieties",
    "aka" => array("subvar") // alternative representations for import
  ),

  "form" => array(
    "children" => array("subform"), // permissible ranks for child taxa
    "abbreviation" => "f.", // official abbreviation
    "plural" => "Forms",
    "aka" => array("forma", "f") // alternative representations for import
  ),

  "subform" => array(
    "children" => array(), // permissible ranks for child taxa
    "abbreviation" => "subf.", // official abbreviation
    "plural" => "Subforms",
    "aka" => array("subforma") // alternative representations for import
  ),

  "unranked" => array(
    "children" => array(), // permissible ranks for child taxa = none
    "abbreviation" => "unr.", // official abbreviation
    "plural" => "Unranked",
    "aka" => array() // alternative representations for import
  )

);
