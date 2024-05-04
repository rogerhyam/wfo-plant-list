<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/SolrIndex.php');
require_once('../include/FacetDetails.php');
require_once('../include/facet_decoration_cache.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess
require_once('header.php');

// get this once as it is an index call
$classification_id_latest = PlantList::getLatestClassificationId();

// we keep the terms in the session
if(isset($_GET['terms'])){
    $terms = @trim($_GET['terms']);
    $_SESSION['search_terms'] = $terms; 
}else{
    $terms = @$_SESSION['search_terms'];
}

// we always do a searcht to load the facet fields
if(!preg_match('/^wfo-/', $terms)){

    // we haven't been passed a wfo ID

    // query by start of word if we have one
    if($terms){
        $query_txt = ucfirst($terms); // all names start with an upper case letter
        $query_txt = str_replace(' ', '\ ', $query_txt);
        $query_txt = $query_txt . "*";
        $query_txt = "full_name_string_alpha_s:$query_txt";
    }else{
        $query_txt = "*:*";
    }

    // restrict to the real names in the accepted classification
    $filters = array();
    $filters[] = 'classification_id_s:' . $classification_id_latest;
    $filters[] = '-role_s:deprecated';

    $facets = array();
    foreach($facet_ids as $fi){

        // add the field to facet on
        $facets[$fi] = (object)array(
                "type" => "terms",
                "field" => $fi
        );

        // if we have a value for the facet
        // then add a filter for that facet
        if(isset($_REQUEST[$fi])){
            foreach($_REQUEST[$fi] as $v){
                $filters[] =  $fi . ':' . $v;
            }
        }
    }

    // pull whole query together
    $query = array(
        'query' => $query_txt,
        'filter' => $filters,
        'sort' => 'full_name_string_alpha_t_sort asc',
        'limit' => 100,
        'facet' => (object)$facets
    );

    echo "<pre>";
    //print_r($query);
    echo '</pre>';
    
    $index = new SolrIndex();
    $solr_response  = $index->getSolrResponse($query);
    if(isset($solr_response->response->docs)) $docs = $solr_response->response->docs;
    if(isset($solr_response->facets)) $facets_response = $solr_response->facets;


  //  echo "<pre>";
//    print_r($solr_response);
//print_r($_SESSION['facets_cache']);


}else{

    // if they put a good wfo in
    if(preg_match('/^wfo-[0-9]{10}$/', $terms) || preg_match('/^wfo-[0-9]{10}-[0-9]{4}-[0-9]{2}$/', $terms)){
        header('Location: browser.php?id=' . $terms);
        exit;
    }

}

?>

<ul class="nav nav-tabs" id="myTab" role="tablist" style="margin-bottom: 2em;">
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="view-tab" href="browser.php" type="button">View</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link active" id="search-tab" href="browser_search.php" type="button">Search</a>
    </li>
</ul>

<form method="GET" action="browser_search.php">
    <p>
        <strong>Search: </strong>
        <input type="text" name="terms" id="terms" placeholder="Type the first part of then name or enter WFO ID"
            size="100" value="<?php echo $terms ?>" onfocus="inputFocussed(this)" autofocus />
        &nbsp;<button type="submit">Search</button>
    </p>

    <p id="loading" style="display: none;">Loading ...</p>

    <div class="container">
        <div class="row">
            <div class="col col-8">
                <?php

    // write out the search results
    if($docs){
        echo "<p><strong>Records: </strong>" . number_format($solr_response->response->numFound) . "</p>";
        echo "<ul>";
        foreach($docs as $doc){
            echo "<li id=\"{$doc->wfo_id_s}\">";
            echo $doc->full_name_string_html_s;
            echo "</li>";
        }
        echo "</ul>";
    }else{
        echo "<p>Nothing found</p>";
    }

?>
            </div>
            <div class="col">
                <div class="accordion" id="facet_accordion">

                    <?php
    foreach($facets_response as $f_name => $f){

        if($f_name == 'count') continue;
        
        $facet_details = new FacetDetails($f_name);

        // calculate if we are collapsed or not
        // any value is ticked then we render as open
        $collapsed = 'collapsed';
        $collapse = 'collapse';
        if(@$_REQUEST[$f_name]){
            foreach($f->buckets as $bucket){
                if(in_array($bucket->val, $_REQUEST[$f_name])){
                    $collapsed = '';
                    $collapse = '';
                    break;
                }
            }
        }


        // we do an accordion item
        echo '<div class="accordion-item">';

        // header
        echo '<h2 class="accordion-header">';
        echo "<button class=\"accordion-button $collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#$f_name\" aria-expanded=\"true\" aria-controls=\"collapseOne\">";
        echo $facet_details->getFacetName();
        echo '</button>';
        echo '</h2>';
       
        // body
        echo "<div id=\"$f_name\" class=\"accordion-collapse $collapse\" data-bs-parent=\"#accordionExample\">";
        echo '<div class="accordion-body">';

        // work through the values
       // echo "<pre>";
//        print_r($facet_details->facet_values);
        //print_r($f->buckets);
        //echo "</pre>";

        foreach($f->buckets as $bucket){

            if(@$_REQUEST[$f_name] && in_array($bucket->val, $_REQUEST[$f_name])){
                $checked = 'checked';
            }else{
                $checked = '';
            }

            $count = number_format($bucket->count, 0);

            echo '<div class="mb-3">';
            echo '<input class="form-check-input"  type="checkbox"';
            echo "value=\"$bucket->val\" id=\"{$bucket->val}\" $checked name=\"{$f_name}[]\"";
            echo 'onchange="this.form.submit()"';
            echo "/>";
            echo "<label class=\"form-check-label\" for=\"{$bucket->val}\">&nbsp;";
            echo $facet_details->getFacetValueName($bucket->val) . " - {$count}";
            echo "</label>";                
            echo '</div>';

        }


        echo '</div>'; // end of body
        echo '</div>'; // end of collapseOne

        // end of accordion-item
        echo '</div>'; 

   

    }

//    print_r($facets_response);

?>
                </div> <!-- end of accordian -->
            </div>
        </div>
</form>
<script>
function inputFocussed(input) {

    input.setSelectionRange(input.value.length, input.value.length, "forward");
    /*
        console.log(val);
        document.getElementById("terms").select();
        div.getElementsByTagName("input")[0].setSelectionRange(div.getElementsByTagName("input")[0].value.length,div.getElementsByTagName("input")[0].value.length,"forward");
    */
}
</script>
<?php
require_once('footer.php');
?>