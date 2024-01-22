<?php
require_once('config.php');
require_once('../include/SolrIndex.php');
require_once('../include/label_map_generate.php');

// returns a map of labels and descriptions for q numbers 
// in the index. 

header("Content-Type: application/json");
$lang = @$_GET['lang'];
if(!$lang) $lang = 'en';

$map = label_map_generate($lang);

echo json_encode((object)$map);