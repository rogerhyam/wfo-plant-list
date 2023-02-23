
<?php

require_once('config.php');
require_once('../include/PlantList.php');

require_once('header.php');

?>
<h2>Solr Query Tester</h2>

<form action="solr_test.php" method="POST">
<textarea name="solr_query" cols="100" rows="20"><?php echo @$_POST['solr_query'] ?></textarea>
<br/>
<input type="submit" />
</form>
<hr/>
<pre>
<?php


if(@$_POST['solr_query']){
    $query = json_decode($_POST['solr_query']);
    print_r(PlantList::getSolrResponse($query));
}

?>

</pre>
<hr/>

<?php
require_once('footer.php');
?>