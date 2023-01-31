
<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');
?>

<h1>Tree File Download</h1>

<p>This will be a service that allows you to upload files containing lists of WFO IDs (perhaps generated using the matching service) 
    and download tree files in formats useful for incorporation into phylogenetic studies.</p>

<p><strong>Are you interested in this service?</strong> 

    We are keen to develop it but would like to partner with a data consumer during development
    so we are sure to create what is most useful.
    Please contact <a href="mailto:rhyam@rbge.org.uk">Roger Hyam</a> with your requirements.

</p>


<?php
require_once('footer.php');
?>