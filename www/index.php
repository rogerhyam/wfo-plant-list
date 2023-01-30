<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');
?>

<h1>WFO Plant List API</h1>

<p>
    Welcome to the WFO Plant List API. The WFO Plant List is the consensus list of names used as the taxonomic backbone of the World Flora Online portal.
    A new version is released every six months.
</p>
<p>
    This site provides techies with access to World Flora Online Plant List data.
    It is intended for those who are comfortable working with simple (not pretty) HTML forms or who want to exploit
    the REST or GraphQL APIs.
</p>

<h2>Are you in the right place?</h2>

<ol>
    <li><a href="http://www.worldfloraonline.org">WFO Portal</a>: The main entry point for the WFO including all the description, distribution, image and other data. This is where you go to find out more about a plant.</li>
    <li><a href="http://www.wfoplantlist.org">WFO Plant List</a>: Human friendly access to the WFO Plant List in all its versions as soon as they are released. It forms part of the main portal.</li>
    <li><a href="https://list.worldfloraonline.org/rhakhis/ui/index.html" >Rhakhis Taxonomic Editor</a>: A tool for taxonomist preparing the next WFO Plant List data release.</li>
    <li><strong>Plant List API</strong> This site. Gives access to the same data as available from 2 above but via APIs and specialist tools.</li>
</ol>

<h2>What is here?</h2>

<ul>
    <li>Matching</li>
    <li>Trees</li>
    <li>Stable URIs</li>
    <li>GraphQL API</li>
</ul>

<h2>Data Model</h2>

<p>
    For each major update of the classification in the main WFO database a snapshot of the taxonomic backbone (names and their statuses) is taken and added to this service. 
    The data available here therefore represents multiple classifications of the plant kingdom showing how our understanding has changed through time.
</p>
<p>
    In order to represent multiple classifications in a single dataset it is necessary to adopt the TaxonConcept model which differentiates between taxa (TaxonConcepts)
    which vary between classifications and names (TaxonNames) which do not, but which may play different roles in different classifications.
</p>

<p class="aside">
    <strong>Taxon name/concept background: </strong>
    A good analogy for those unfamiliar with the TaxonConcept model is that of polygons and points within a geospatial model.
    A classification divides a plane into contiguous map of nested polygons (like counties, regions, countries, continents).
    These are the taxa.
    The names are points on the plane.
    The name used for a polygon is the oldest point that occurs within it.
    Other names that fall in that polygon are referred to as synonyms.
    Different taxonomic classifications are like the different maps of the same plane with different polygons but with points that are the same on all maps.
    Polygons on two maps might have the same calculated name but different boundaries and different synonyms.
    It is therefore necessary to refer to taxa in different classifications using unique identifiers rather than their calculated names.
</p>

<h2>Identifiers</h2>

<p>
    All name records have a single, prescribed ID which is of the form <code>wfo-0000615907</code>. The lowercase letters "wfo" followed by a hyphen followed by ten digits.
    A regular expression similar to <code>'/^wfo-[0-9]{10}$/'</code> will match a WFO ID (depending on your precise regex implementation). 
</p>

<p>
    Once created WFO IDs are never deleted and will always return data.
    However it is possible that two IDs have been created for one real world name. We do our utmost to prevent this happening but 
    we are still dealing with some historical duplication within the data.
    In the cases where it is decided that multiple WFO IDs apply to a single name then the records are merged and one of the IDs prescribed as the one that should be used going forward. 
    The other WFO ID becomes a deduplicated ID for that name record. Services will still respond to that ID. 
    It will never be deleted but it won't be presented as the WFO ID that should be used for that name again.
</p>

<p>
    With each data release a new set of IDs are created that are of the form <code>wfo-0000615907-2022-12</code>. 
    That is for each name the year and month of the data release are appended. 
    A regular expression similar to <code>'/^wfo-[0-9]{10}-[0-9]{10}-[4]{2}$/'</code> will match a versioned WFO ID (depending on your precise regex implementation). 
</p>

<p>
    Within the data release names play one of four roles and the meaning of the sixteen digit WFO ID depends on role the name is playing. 
</p>

<ol>
    <li>Placed as the <strong>accepted</strong> name of a taxon. The ID refers to the taxon.</li>
    <li>Placed as a <strong>synonym</strong> within a taxon. The ID refers to the name usage.</li>
    <li><strong>Unplaced</strong> if our experts have yet to express an opinion on placement. The ID refers to the name alone.</li>
    <li><strong>Deprecated</strong> if our experts conclude it isn't possible to place this name and it should not be used. The ID refers to the name record that shouldn't be used.</p>
</ol>

<p>
    Un-versioned, ten digit WFO IDs will usually be treated as referring to the current usage of that name. 
    i.e. if a data release version isn't specified the current release will be presumed.
</p>

<p>
    WFO IDs are used in their ten digit and sixteen digit forms in different services or as the final parts of <a href="sw_index.php">Stable HTTP URIs</a>.
</p>

<p>
    To link to a WFO name it is recommended to always use the <a href="sw_index.php">Stable HTTP URI</a> form and not to reverse engineer the URL that appears in a browser bar.
    This isn't guaranteed to be stable.
</p>

    

<?php
require_once('footer.php');
?>