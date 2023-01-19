<?php

require_once('../vendor/autoload.php'); // composer libraries
require_once('../../wfo_secrets.php'); // outside the github root

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL & ~E_NOTICE);
error_reporting(E_ALL);
session_start();

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