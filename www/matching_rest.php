<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/NameMatcher.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess



// are we being sent a request

if(@$_GET['input_string']){

    $inputString = $_GET['input_string'];
    $checkHomonyms = @$_GET['check_homonyms'] == 'true' ? true : false;
    $checkRank = @$_GET['check_rank'] == 'true' ? true : false;

    $matcher = new NameMatcher((object)array('checkHomonyms' => $checkHomonyms, 'checkRank' => $checkRank, 'method' => 'full'));
    $response = $matcher->match($inputString);

    if($response->match){
        $match = $response->match;
        $response->match = new stdClass();
        $response->match->wfo_id = $match->getWfoId();
        $response->match->full_name_plain = $match->getFullNameStringPlain();
        $response->match->full_name_html = $match->getFullNameStringHtml();
        $response->match->placement = $match->getWfoPath();
    }

    // same again for candidates - this could be split to function ....
    if($response->candidates){

        $candidates = $response->candidates;
        $response->candidates  = array();
        foreach($candidates as $c){

            $candidate = new stdClass();
            $candidate->wfo_id = $c->getWfoId();
            $candidate->full_name_plain = $c->getFullNameStringPlain();
            $candidate->full_name_html = $c->getFullNameStringHtml();
            $candidate->placement = $c->getWfoPath();

            $response->candidates[] = $candidate;
        }

    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

require_once('header.php');
?>

<h1>Name Matching REST API</h1>

<p>
    The most powerful way to query the name matching service is via the <a href="gql_index.php">GraphQL API</a> 
    however for some application a very simple <a href="https://en.wikipedia.org/wiki/Representational_state_transfer">REST</a> service is sufficient.
    That is what is provided here.
    This service should be seen as a chopped down version of the <a href="gql_index.php">GraphQL API</a>. 
    Behind the scenes it uses the same code.
    If you need more complex behaviour you are encouraged to explore the <a href="gql_index.php">GraphQL API</a>
    which can be called without the use of client side libraries if necessary.
</p>

<h2>Requests and Responses</h2>

<p>
    Only GET requests are supported. All parameters are optional. Calling without the "input_string" parameter will return this page.
    All requests that provide a value in the "q" parameter will be responded to with a JSON object equivalent to the matching response object provided by the 
    GraphQL API.
</p>

<h2>Request Parameters</h2>

<ul>
    <li><strong>search_string</strong> The name string to be searched for. It should contain a single botanical name. This should include the authors of the name if available. It should be URL encoded.</li>
    <li><strong>check_homonyms</strong> If present with the value "true" then homonyms will be checked for. If a single, exact match of name and author string is found but there are other names with the same letters but a different author strings present the match won't be considered unambiguous. </li>
    <li><strong>check_rank</strong> If present with the value "true" then the rank will be checked for. If a precise match of name and author string is found and it is possible to extract the rank from the name string but the rank isn't the same the match won't be considered unambiguous.</li>
</ul>

<h2>Response</h2>

<p>A JSON object is returned with the following properties</p>

    <ul>
        <li><strong>inputString (string)</strong> The string provided in the input_string parameter.</li>
        <li><strong>searchString (string)</strong> The cleaned up input string used for matching.</li>
        <li><strong>match (name object)</strong> An unambiguous match. Null if an unambiguous match couldn't be found.</li>
        <li><strong>candidates (array of name objects)</strong> An array of name objects that are close matches but not unambiguous.</li>
        <li><strong>method (string)</strong> The name of the matching method used internally.</li>
        <li><strong>error (bool)</strong> True if there was an error to report.</li>
        <li><strong>error (string)</strong> The error message should an error have occured. </li>
        <li><strong>narrative (array of strings)</strong> An explanation of the steps taken to parse the name and match it to the index. This is useful for debugging or explaining to the user what happened!</li>
    </ul>

    <p>The name objects will have the following properties.</p>

    <ul>
        <li><strong>wfo_id</strong> The WFO ID that should be used to refer to this name.</li>
        <li><strong>full_name_plain</strong>The full name (including authors) as plain text.</li>
        <li><strong>full_name_html</strong>The full name (including authors) with markup text.</li>
        <li><strong>placement</strong>This is a "sanity check". If the name is placed in the current classification it is the path of names from the root of the taxonomy. 
        If it is unplaced the value will either be UNPLACED (not yet placed by expert) or DEPRECATED (declared unplaceable).</li>
    </ul>

    <p>If you would like more complex information back about the name and its placement in different classifications you are encouraged to use 
        the GraphQL API or you could call the names Stable URI to get a JSON representation of it.
    </p>

<h2>Examples</h2>

<?php
    $example_1 = get_uri('matching_rest.php?input_string=Rhopalocarpus+alternifolius+(Baker)+Capuron');
    echo '<p>Perfect match</p>';
    echo "<p><a href=\"$example_1\"><code>$example_1</code></a></p>";

    $example_2 = get_uri('matching_rest.php?input_string=Rhopalocarpus+alternifolia+(Baker)+Capuron');
    echo '<p>Imperfect match</p>';
    echo "<p><a href=\"$example_2\"><code>$example_1</code></a></p>";
?>




<?php
require_once('footer.php');
?>