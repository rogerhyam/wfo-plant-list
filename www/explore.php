<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/NameMatcher.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');
?>
<div id="navbar">
    <strong>Explore: </strong>
    <a href="index.php">Classification</a>
    |
    <a href="sw_index.php">Nomenclature</a>
    |
    <a href="gql_index.php">GraphQL API</a>
    |
    <a href="matching.php">Matching Tool</a>
    |
    <a href="matching_rest.php">Matching API</a>
    |
    <a href="reconcile_index.php">Reconciliation API</a>
    |
    <a href="references.php">Refs Tool</a>
    |
    <a href="browser.php">Browser</a>
</div>
<p>Explore the current classification and download subsets of it.</p>


<?php
require_once('footer.php');
?>