
<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');
?>

<h1>Stable URIs</h1>

<p>
All TaxonConcepts and TaxonNames are identified with URIs which resolve according to semantic web best practices (see below).
These identifiers are also used in the GraphQL accessible data. They are intended to be persistent and can be stored. The URIs are based on the WFO IDs used elsewhere.</p>
<p>
<strong>TaxonNames</strong> identifiers take the form <a href="<?php echo get_uri('wfo-0001048237') ?>" ><?php echo get_uri('wfo-0001048237') ?></a>. The final part of the URI is the same as the identifier 
used in the live web pages for the current version of the WFO. There is a one to one relationship between names, as created under the International Code for Botanical Nomenclature,
and these identifiers.
</p>

<p>
<strong>TaxonConcepts</strong> identifiers take the form <a href="<?php echo get_uri('wfo-0001048237-2022-12') ?>" ><?php echo get_uri('wfo-0001048237-2022-12') ?></a>. The final part of the URI is a name identifier 
qualified by a classification version. The version format is the year followed by the two digit month. 
</p>

<p>
    Note that although the format of identifiers is described here (because it is useful for understanding and debugging) you should not construct them programmatically
    but treat them as opaque strings.

    It is possible to construct taxon concept identifiers for taxa that don't exist. If a name did not occur in an earlier version of the classification but you create a URI
    that consists of the WFO ID plus the version of that classification you will get a 404 NOT FOUND response.
</p>

<p>
    We bend the semantics slightly for the sake of utility. If a record is a synonym it is semantically not a TaxonConcept but a TaxonName. The versioned WFO IDs for synonyms will therefore 301 redirect to the name entry only.

The name <a href="<?php echo get_uri('wfo-0000615907') ?>" ><?php echo get_uri('wfo-0000615907') ?></a> (<i>Comandra elliptica</i> Raf.)
Is a synonym in the classification 2022-12.
The versioned URI of that name is this:
<a href="<?php echo get_uri('wfo-0000615907-2022-12') ?>" ><?php echo get_uri('wfo-0000615907-2022-12') ?></a> If you click on it in a web browser you will be redirected to the taxon page in the WFO Plant List for the accepted name.

If on the other hand you were to call for <strong>data</strong> for that name using an HTTP Accept header of application/json, perhaps with the curl command 
</p>
<code>curl -I -H "Accept: application/json" <?php echo get_uri('wfo-0000615907') ?></code>
<p>
then you would get a 301 redirect to the accepted name <a href="<?php echo get_uri('wfo-0000615918-2022-12') ?>" ><?php echo get_uri('wfo-0000615918-2022-12') ?></a>
(<i>Comandra umbellata</i> (L.) Nutt.)
in which <i>Comandra elliptica</i> Raf. is a synonym.
</p>
<h3>Properties</h3>
<p>
The diagram below shows the property relationships in the data model. Further documentation on these can be found either by dereferencing the URIs of the terms in the RDF responses 
or by looking at the GraphQL documentation using an IDE. The second way might be useful even if you intend to only use the Semantic Web API.
</p>

<a href="terms/png"><img src="terms/png" style="width: 100%"/></a>

<p></p>


<?php
require_once('footer.php');
?>