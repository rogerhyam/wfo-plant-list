<?php

/**
 * This is a wrapper around a SOLR doc representing taxa and names
 * It loads itself directly from the SOLR index.
 */
class NameMatcher extends PlantList{

    private $params;

    /**
     * Create a matcher with configured behaviour
     * for matching using the match() method.
     * 
     * @param Object $config_params Configuration for the matcher as an array
     */
    public function __construct($config_params){
        
        $this->params = $config_params;

        // override with some defaults if they haven't been set
        if(!isset($this->params->method)) $this->params->method = 'alpha';
        if(!isset($this->params->includeDeprecated)) $this->params->includeDeprecated = false;
        if(!isset($this->params->limit)) $this->params->limit = 100;
        if(!isset($this->params->classificationVersion)) $this->params->classificationVersion = WFO_DEFAULT_VERSION;

    }

    /**
     * Called on a configured NameMatcher
     */
    public function match($searchString){

        $response = new class{};
        $response->searchString = $searchString;
        $response->params = $this->params;
        $response->match = null;
        $response->candidates = array();
        $response->error = false;
        $response->errorMessage = null;

        switch ($this->params->method) {
            case 'alpha':
                return $this->alphaMatch($searchString, $response);
                break;
            case 'full':
                return $this->fullMatch($searchString, $response);
                break;
            default:
                throw new ErrorException("Unrecognized matching method {$this->params->method}");
                break;
        }
    }


    /**
     * 
     * A comprehensive search.
     * 
     */
    private function fullMatch($searchString, $response){

        global $ranks_table;

        $response->parsedName = new class{};
        $response->narrative = array();

        // FIXME: sanitize the name of hybrid symbols

        /*
            - possible name structures.
            Word
            Word author-string
            Word word
            Word word author-string
            Word rank word author-string
            Word word word author-string
            Word word rank word author-string
        */

        // lets parse the name out
        $parts = explode(" ", $searchString);
        $canonical_parts = array(); // this is just the name parts - up to 3 words
        $response->parsedName->rank = null; // if we can find one
        $authors = null;

        // the first word is always a taxon word
        $canonical_parts[] = $parts[0];
        $response->narrative[] = "The first word is always a name part word: '{$parts[0]}'";
        $final_word_part = 0;

        // look for subsequent parts
        for($i = 1; $i < count($parts); $i++){

            $word = $parts[$i];

            // is this a rank?
            if(PlantList::isRankWord($word)){

                // we have found the rank
                $response->parsedName->rank = PlantList::isRankWord($word);
                $response->narrative[] = "Rank estimated as '{$response->parsedName->rank}' based on '$word'.";

                // the following word is alway a name part and completes the name
                if($i+1 < count($parts)){
                    $canonical_parts[] = $parts[$i+1];
                    $final_word_part = $i+1;
                    $response->narrative[] = "Word following rank is always name part: '{$parts[$i+1]}'.";
                }
                break;

            }

            // see if it is anything other than just letters - and there for not a word-part but an author string
            if(preg_match('/[^a-zA-Z\-]/', $word)){
                $final_word_part = $i-1; // the last word was the final word part
                $response->narrative[] = "Word contains non alpha chars and so is start of author string: '$word'.";
                break;
            }

            // we have not found a rank is the word a recognized name? 
            $query = array(
                'query' => 'name_string_s:' . $word, 
                'limit' => 0
            );
            $solr_response = PlantList::getSolrResponse($query);
            if(isset($solr_response->response->numFound)){
                if($solr_response->response->numFound > 0){
                    $canonical_parts[] = $word;
                    $final_word_part = $i;
                    $response->narrative[] = "Word is found in index and so is part of name: '$word'.";
                }else{
                    $response->narrative[] = "Word is NOT found in index and so start of authors: '$word'.";
                    $final_word_part = $i-1;
                    break;
                }
            }else{
                echo "<p>SOLR Issues</p>";
                echo "<pre>";
                print_r($solr_response);
                echo "<pre/>";
                exit;
            }

            // if we have found 3 name-parts we should definitely stop
            if(count($canonical_parts) == 3){
                $response->narrative[] = "Found three name words and so rest of string must be authors.";
                break;
            }

        }

        // build the name out of the max three canonical parts
        $response->parsedName->canonical_form = implode(' ', $canonical_parts);

        // all the rest of the parts are the authors string
        $response->parsedName->author_string = implode(' ', array_slice($parts, $final_word_part +1));

        $response->narrative[] = "Parsed name complete.";

        // we can assume it is a species if there are two words and the second is lower case
        if(!$response->parsedName->rank && count($canonical_parts) == 2 && preg_match('/^[a-z]+/', $canonical_parts[1])  ){
            $response->parsedName->rank = 'species';
            $response->narrative[] = "Rank estimated as 'species' from name parts.";
        }

        // let us actually do the search...
        // get everything with a matching canonical name
        $query = array(
            'query' => 'full_name_string_alpha_s:"' . $response->parsedName->canonical_form . '"',
            'filter' => 'classification_id_s:' . WFO_DEFAULT_VERSION,
            'limit' => 100
        );
        $docs = PlantList::getSolrDocs($query);

        $response->narrative[] = "Searched index of " . WFO_DEFAULT_VERSION ." for canonical form of name '{$response->parsedName->canonical_form}' and found " . count($docs) . " candidates.";
        
        // rather than do convoluted logic we do it step wise.


        // they are all candidates
        foreach($docs as $doc){
            $response->candidates[] = new TaxonRecord($doc);
        }

        // do we have a single one with a good author string?
        foreach($response->candidates as $candidate){

            if($candidate->getAuthorsString() == $response->parsedName->author_string){

                if($response->match && $response->match != $candidate){
                    // we have found two with good author strings!
                    if($response->match->getRole() == 'deprecated' && $candidate->getRole() != 'deprecated'){
                        // a good name over rules a deprecated one
                        $response->match = $candidate;
                    }else{
                        // we have two and one isn't deprecated so we can't decide
                        // between them
                        $response->match = null;
                    }
                }else{
                    // this become the new match
                    $response->match = $candidate;
                    $response->narrative[] = "Found candidate ({$candidate->getWfoId()}) with matching author string so it becomes the match.";
                }
            }
        }

        // they care about ranks so remove the match if the ranks don't match
        if($response->match && @$_GET['rank'] && $response->parsedName->rank != $name->getRank()){
            // they want the ranks to match and they don't so demote it
            $response->match = null;
            $response->narrative[] = "Checked ranks and they didn't match.";
        }

        // they don't care about homonyms so we can scrub any candidates if we have a match
        if($response->match && !@$GET['homonyms']){
            $response->candidates = array();
            $response->narrative[] = "Homonyms (same name different authors) are considered OK and we have a match so removing candidates.";
        }

        // Have we go anything?





        return $response;
    }

    /**
     * A simple alphabetical lookup of names
     * 
     */
    private function alphaMatch($searchString, $response){

        // we only do it if we have more than 3 characters?
        if(strlen($searchString) < 4){
            $response->error = true;
            $response->errorMessage = "Search string must be more than 3 characters long.";
            return $response;
        }

        $name = trim(strtolower($searchString));
        $name = ucfirst($name); // all names start with an upper case letter
        $name = str_replace(' ', '\ ', $name);
        $name = $name . "*";

        $query = array(
            'query' => "full_name_string_alpha_s:$name",
            'filter' => 'classification_id_s:' . $this->params->classificationVersion,
            'sort' => 'full_name_string_alpha_t_sort asc',
            'limit' => $this->params->limit
        );

       //error_log(print_r($query, true));

        $docs  = $this->getSolrDocs($query);

        if(count($docs) == 1){
            $response->match = new TaxonRecord($docs[0]);
        }else{
            foreach ($docs as $doc) {
                $response->candidates[] = new TaxonRecord($doc);
            }
        }

        return $response;
    }

}