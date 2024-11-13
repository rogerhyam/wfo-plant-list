<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/NameMatcher.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess



// are we being sent a request
$response = false;
if(@$_GET['input_string']){

    $inputString = $_GET['input_string'];
    $checkHomonyms = @$_GET['check_homonyms'] == 'true' ? true : false;
    $checkRank = @$_GET['check_rank'] == 'true' ? true : false;
    $acceptSingleCandidate = @$_GET['accept_single_candidate'] == 'true' ? true : false;
    
    if(@$_GET['fuzzy_names']) $fuzzyNameParts = intval($_GET['fuzzy_names']);
    else $fuzzyNameParts = 0;

    if(@$_GET['fuzzy_authors']) $fuzzyAuthors = intval($_GET['fuzzy_authors']);
    else $fuzzyAuthors = 0;


    $matcher = new NameMatcher(
        (object)array(
            'checkHomonyms' => $checkHomonyms,
            'checkRank' => $checkRank,
            'method' => 'full',
            'acceptSingleCandidate' => $acceptSingleCandidate,
            'fuzzyNameParts' => $fuzzyNameParts,
            'fuzzyAuthors' => $fuzzyAuthors
        ));
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

    if(!@$_GET['human']){
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}

require_once('header.php');
?>

<h1>Name Matching REST API</h1>

<p>
    The most powerful way to query the name matching service is via the <a href="gql_index.php">GraphQL API</a> 
    however for some applications a very simple <a href="https://en.wikipedia.org/wiki/Representational_state_transfer">REST</a> service is sufficient.
    That is what is provided here.
    This service should be seen as a chopped down version of the <a href="gql_index.php">GraphQL API</a>. 
    Behind the scenes it uses the same code.
    If you need more complex behaviour you are encouraged to explore the <a href="gql_index.php">GraphQL API</a>
    which can be called without the use of client side libraries if necessary.
</p>

<h2>Test Form</h2>
    <p>
        Use this form to test how name strings are parsed and matched.
    </p>
    <form class="row g-3">
        <input type="hidden" name="human" value="true" />

        <div class="col-12">
            <label for="name_string" class="form-label">Name for matching</label>
            <input id="name_string" type="text" class="form-control" name="input_string" value="<?php echo @$_GET['input_string'] ?>" placeholder="Enter your name string here."/>
        </div>

         <div class="col-md-6">
            <label class="form-label" for="fuzzy_names">Fuzzy name matching</label>
            <select id="fuzzy_names" name="fuzzy_names" class="form-select">
                <option value="0" <?php echo @$_GET['fuzzy_names'] == 0 || !@$_GET['fuzzy_names'] ? 'selected' : '';  ?> >Off</option>
                <option value="1" <?php echo @$_GET['fuzzy_names'] == 1 ? 'selected' : '';  ?> >1 character difference per word is permitted</option>
                <option value="2" <?php echo @$_GET['fuzzy_names'] == 2 ? 'selected' : '';  ?> >2 character differences per word are permitted</option>
                <option value="3" <?php echo @$_GET['fuzzy_names'] == 3 ? 'selected' : '';  ?> >3 character differences per word are permitted</option>
            </select>                
         </div>

         <div class="col-md-6">
            <label class="form-label" for="fuzzy_authors">Fuzzy author string matching</label>
            <select id="fuzzy_authors" name="fuzzy_authors" class="form-select">
                <option value="0" <?php echo @$_GET['fuzzy_authors'] == 0 || !@$_GET['fuzzy_authors'] ? 'selected' : '';  ?> >Off</option>
                <option value="1" <?php echo @$_GET['fuzzy_authors'] == 1 ? 'selected' : '';  ?> >1 character difference in the string permitted</option>
                <option value="2" <?php echo @$_GET['fuzzy_authors'] == 2 ? 'selected' : '';  ?> >2 character differences in the string permitted</option>
                <option value="3" <?php echo @$_GET['fuzzy_authors'] == 3 ? 'selected' : '';  ?> >3 character differences in the string permitted</option>
                <option value="4" <?php echo @$_GET['fuzzy_authors'] == 4 ? 'selected' : '';  ?> >4 character differences in the string permitted</option>
                <option value="5" <?php echo @$_GET['fuzzy_authors'] == 5 ? 'selected' : '';  ?> >5 character differences in the string permitted</option>
            </select>                
         </div>


        <div class="col-md-12">
            <div class="form-check">
                <input id="check_homonyms" type="checkbox" name="check_homonyms" <?php echo @$_GET['check_homonyms'] ? "checked" : "" ?> value="true" />
                <label class="form-check-label" for="check_homonyms"><strong>All homonyms:</strong> Consider matches to be ambiguous if there are other names with the same words but different author strings.</label>
            </div>
         </div>

         <div class="col-md-12">
            <div class="form-check">
                <input id="check_rank" type="checkbox" name="check_rank" <?php echo @$_GET['check_rank'] ? "checked" : "" ?>  value="true" />
                <label class="form-check-label" for="check_rank"><strong>Check rank:</strong> Consider matches to be ambiguous if it is possible to estimate rank from the search string and the rank does not match that in the name record.</label>
            </div>
         </div>

         <div class="col-md-12">
            <div class="form-check">
            <input id="accept_single_candidate" type="checkbox" name="accept_single_candidate" <?php echo @$_GET['accept_single_candidate'] ? "checked" : "" ?>  value="true" />
                <label class="form-check-label" for="accept_single_candidate"><strong>Accept single candidate:</strong> If only a single candidate name is found that will be made the match, even if it isn't an exact match.</label>
            </div>
         </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Match now</button>
            <button type="submit" class="btn btn-outline-secondary" onclick="event.preventDefault(); window.location='matching_rest.php';">Clear form</button>
        </div>
               
    </form>
    <p></p>
<?php 
if($response){
    echo "<hr/>";
    echo "<h3>Matching Test Results</h3>";
    render_list($response);
    $ip = urlencode($_GET['input_string']);
    $check_rank = @$_GET['check_rank'];
    $check_homonyms = @$_GET['check_homonyms']; 
    echo "<a href=\"matching_rest.php?input_string=$ip&check_rank=$check_rank&check_homonyms=$check_homonyms\" target=\"wfo_matching_json\">View JSON version</a>";
    echo "<hr/>";
}// there is a response

function render_list($ob){
    echo "<ul>";
    $count = 0;
    foreach($ob as $key => $val){
        echo "<li><strong>$key: </strong>";
        if(is_object($val) || is_array($val)){
            render_list($val);
        }else{
            echo $val;
        }
        echo "</li>";
        $count++;
        if($count > 19){
            $remains = count($ob) - $count;
            echo "<li>and $remains more ...</li>";
            break;
        }
    }    
    echo "</ul>";
}

?>


<h2>Requests and Responses</h2>

<p>
    Only GET requests are supported. All parameters are optional. Calling without the "input_string" parameter will return this page.
    All requests that provide a value in the "input_string" parameter will be responded to with a JSON object equivalent to the taxonNameMatch response object provided by the 
    GraphQL API.
</p>

<h2>Request Parameters</h2>

<ul>
    <li><strong>input_string</strong> The name string to be searched for. It should contain a single botanical name. This should include the authors of the name if available. It should be URL encoded.</li>
    <li><strong>fuzzy_names</strong> If an integer value greater than 0 is provided then it will be used as a maximum <a href="https://en.wikipedia.org/wiki/Levenshtein_distance">Levenshtein distance</a> 
        when matching words in the name. Each word parsed from a name (not forming part of the authors string or a rank) is checked against the index. If it doesn't exist then an attempt will be made 
    to find a replacement word that is used in the index and that is within this Levenshtein distance. If a single, unambiguous word is found then that is used in place of the word provided. 
This helps increase matches when there are typographical/OCR errors of a few characters in complex words. It is recommended not to set this above 3.</li>
    <li><strong>fuzzy_authors</strong> If an integer value greater than 0 is provided then it will be used as the maximum Levenshtein distance that two authors strings can be apart and still be considered to match. 
Unlike with fuzzy_names this is applied to the whole string not words within the string thus catching punctuation and spacing errors.</li>
    <li><strong>check_homonyms</strong> If present with the value "true" then homonyms will be checked for. If a single, exact match of name and author string is found but there are other names with the same letters but a different author strings present the match won't be considered unambiguous. </li>
    <li><strong>check_rank</strong> If present with the value "true" then the rank will be checked for. If a precise match of name and author string is found and it is possible to extract the rank from the name string but the rank isn't the same the match won't be considered unambiguous.</li>
    <li><strong>accept_single_candidate</strong> If present with the value "true" and only a single candidate name is found that will be made the match, even though it isn't an exact match.</li>
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

<h2>GraphQL equivalent</h2>
<p>
    This is an example of a similar matching call using a GraphQL query.
    It could be extended to include appropriate parts of the graph of 
    TaxonName and TaxonConcept objects as required.
</p>
<code>
<pre>
query {
  taxonNameMatch(inputString: "Rhopalocarpus alternifolium (Baker) Capuro") {
    inputString
    searchString
    match {
      wfoId
      fullNameStringPlain
      fullNameStringHtml
    }
    candidates {
      wfoId
      fullNameStringPlain
      fullNameStringHtml
    }
    narrative
  }
}
</pre>
</code>





<?php
require_once('footer.php');
?>