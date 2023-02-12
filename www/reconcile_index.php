<?php

require_once('config.php');
require_once('header.php');
?>

<h1>Reconciliation Service API</h1>

<p>This is an implementation of the 
    <a href="https://reconciliation-api.github.io/specs/0.2/">Reconciliation Service API</a>
    to enable matching within applications such as <a href="https://openrefine.org/">OpenRefine</a>.
</p>

<p>
    The reconciliation service URL is <a href="<?php echo  get_uri('reconcile');   ?>"><?php echo  get_uri('reconcile');  ?></a>.
    You can add it as a standard service in OpenRefine.
</p>

<img src="images/open_refine_screen.png" style="max-width: 600px" />

<p>
    You can explore the service endpoint in the <a href="https://reconciliation-api.github.io/testbench/#/client/<?php echo urlencode(get_uri('reconcile')) ?>">Reconciliation API Test bench<a>. 
    The service supports the reconcile, suggest and preview features.
</p>

<p>
    As with the other matching services here the reconcile service expects a name string that contains the author string for maximum selectivity and ignores ranks by default. 
    The suggest service behaves slightly differently in that it does not expect a rank to by typed in a three word name i.e. the user to type Alysicarpus glumaceus styracifolius not Alysicarpus glumaceus var. styracifolius.
    By the time the second word is typed there are usually very few suggestions anyway so this is a fine point!
</p>

<?php
require_once('footer.php');
?>