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
        //if(!isset($this->params->includeDeprecated)) $this->params->includeDeprecated = false;
        if(!isset($this->params->limit)) $this->params->limit = 100;
        if(!isset($this->params->classificationVersion)) $this->params->classificationVersion = WFO_DEFAULT_VERSION;
        if(!isset($this->params->checkHomonyms)) $this->params->checkHomonyms = false;
        if(!isset($this->params->checkRank)) $this->params->checkRank = false;
        if(!isset($this->params->acceptSingleCandidate)) $this->params->acceptSingleCandidate = false;
    
    }

    /**
     * Called on a configured NameMatcher
     */
    public function match($inputString){

        $response = new class{};
        $response->inputString = $inputString; // raw string submitted
        $response->searchString = $inputString;  // sanitized string we actually search on
        $response->params = $this->params;
        $response->match = null;
        $response->candidates = array();
        $response->error = false;
        $response->errorMessage = null;

        // we do some common sanitizing at this level
        
        // hybrid symbols be gone
        $json = '["\u00D7","\u2715","\u2A09"]';
        $hybrid_symbols = json_decode($json);
        foreach ($hybrid_symbols as $symbol) {
            $response->searchString = trim(str_replace($symbol, '', $response->searchString));
        }

        switch ($this->params->method) {
            case 'alpha':
                return $this->alphaMatch($response);
                break;
            case 'full':
                return $this->fullMatch($response);
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
    private function fullMatch($response){

        global $ranks_table;

        $response->parsedName = new class{};
        $response->narrative = array();

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
        $parts = explode(" ", $response->searchString);
        $canonical_parts = array(); // this is just the name parts - up to 3 words
        $response->parsedName->rank = null; // if we can find one
        $authors = null;

        // the first word is always a taxon word
        $canonical_parts[] = $parts[0];
        $response->narrative[] = "The first word is always a name part word: '{$parts[0]}'";
        $final_word_part = 0;

        // look for subsequent parts
        $autonym_rank = false;
        $had_lower_case_word = false; // and we are therefore likely below species level
        for($i = 1; $i < count($parts); $i++){

            $word = $parts[$i];

            if(!$word) continue;

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

            $is_name_word = true;

            // see if it is anything other than just letters
            // or if it starts with a capital when we have already had a lowercase name word (handles case of authors with names that are also genus names.)
            //  - and therefore not a word-part but an author string
            if(preg_match('/[^a-zA-Z\-]/', $word)){
                $is_name_word = false;
                $response->narrative[] = "Word contains non alpha chars and so is start of author string: '$word'.";
            }elseif($had_lower_case_word && preg_match('/^[A-Z]/', $word)){
                $is_name_word = false;
                $response->narrative[] = "Word starts with a capital when we have already had a name word starting with a lower case so part of author string: '$word'.";
            }else{
                    // Is the word a recognized name? 
                    $query = array(
                        'query' => 'name_string_s:' . $word, 
                        'limit' => 0
                    );
                    $solr_response = PlantList::getSolrResponse($query);
                    if(isset($solr_response->response->numFound)){
                        if($solr_response->response->numFound > 0){
                            $is_name_word = true;
                            $response->narrative[] = "Word is found in index and so is part of name: '$word'.";
                            // if it is a lowercase word we remember that as subsequent name words should not start
                            // with capitals
                            if(preg_match('/^[a-z]/', $word)){
                                $response->narrative[] = "Word is lowercase. Subsequent words must start with lowercase: '$word'.";
                                $had_lower_case_word = true; 
                            } 
                        }else{
                            if($i == 1 && preg_match('/^[a-z]+/', $word)){
                                $is_name_word = true;
                                $response->narrative[] = "Word is NOT found in index BUT is second and has lowercase first letter so most probably novel/erroneous epithet: '$word'.";
                            }else{
                                $is_name_word = false;
                                $response->narrative[] = "Word is NOT found in index and so start of authors: '$word'.";
                            }
                        }
                    }else{
                        echo "<p>SOLR Issues</p>";
                        echo "<pre>";
                        print_r($solr_response);
                        echo "<pre/>";
                        exit;
                    }
            }

            // is this word a name part or something else?
            if($is_name_word){                
                // we are building the canonical name OK
                $canonical_parts[] = $word;
                $final_word_part = $i;
            }else{

                // we have run into the author string start
                $final_word_part = $i-1; // the last word was the final word part (ignoring autonym parts )

                // This might be an autonym with authors between the species part and the subspecific part
                if(count($canonical_parts) == 2){
                    $response->narrative[] = "There are two name parts. This may be an autonym with species authors included. Checking for second occurrence of '{$canonical_parts[1]}'";
                    for($j = $i; $j < count($parts); $j++){
                        if($parts[$j] == $canonical_parts[1]){
                            $response->narrative[] = "Found second '{$canonical_parts[1]}'. This is an autonym with authors.";
                            $canonical_parts[] = $canonical_parts[1];
                            break;
                        }
                    }

                    // check for the rank in the authors string of autonym
                    if(count($canonical_parts) == 3 && $canonical_parts[2] == $canonical_parts[1]){
                        for($j = $i; $j < count($parts); $j++){
                            if(PlantList::isRankWord($parts[$j])){
                                $response->parsedName->rank = PlantList::isRankWord($parts[$j]);
                                $response->narrative[] = "Rank estimated as '{$response->parsedName->rank}' based on '{$parts[$j]}'.";
                                $autonym_rank = $parts[$j];
                                break;
                            }
                        }
                    }

                }
                
                break; // stop adding words we have done the authors part
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
        $response->parsedName->author_string = trim(implode(' ', array_slice($parts, $final_word_part +1)));
        $response->narrative[] = "Authors string looks like this: '{$response->parsedName->author_string}'";

        // If we are dealing with an autonym the name may be embedded in the author string when we take this approach.
        if(count($canonical_parts) == 3 && $canonical_parts[1] == $canonical_parts[2] && strpos($response->parsedName->author_string, $canonical_parts[1]) !== false){
            $response->parsedName->author_string = trim(str_replace("{$canonical_parts[1]}", " ", $response->parsedName->author_string));
            $response->narrative[] = "Autonym so removed name part from authors: '{$response->parsedName->author_string}'";

            if($autonym_rank){
                $response->parsedName->author_string = trim(str_replace("{$autonym_rank}", " ", $response->parsedName->author_string));
                $response->narrative[] = "Autonym so removed rank '$autonym_rank' from authors: '{$response->parsedName->author_string}'";
            }

        }

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
            $doc->asName = true;
            $response->candidates[] = new TaxonRecord($doc);
        }

        // do we have a single one with a good author string?
        foreach($response->candidates as $candidate){

            //$response->narrative[] = "Checking candidates for authors string.";

            if($candidate->getAuthorsString() == $response->parsedName->author_string){

                if($response->match && $response->match != $candidate){
                    // we have found a second with good author strings!
                    if($response->match->getRole() == 'deprecated' && $candidate->getRole() != 'deprecated'){
                        // a good name over rules a deprecated one
                        $response->match = $candidate;
                        $response->narrative[] = "Deprecated name removed to reveal matched name.";
                    }else{
                        // we have two and one isn't deprecated so we can't decide
                        // between them
                        $response->match = null;
                        $response->narrative[] = "No candidate has matching author string.";
                        break;
                    }
                }else{
                    // this become the new match
                    $response->match = $candidate;
                    $response->narrative[] = "Found candidate ({$candidate->getWfoId()}) with matching author string so it becomes the match.";
                }
            }
        }

        // if the search string has an ex in it then look without the ex author
        // second author is real one.

        if(!$response->match){
            if(strpos($response->parsedName->author_string, ' ex ') !== false){
                $response->narrative[] = "Submitted authors contain ' ex '. Removing the ex and checking authors again.";
                $ex_less_authors = $this->removeExAuthors($response->parsedName->author_string);
                foreach($response->candidates as $candidate){
                    if($candidate->getAuthorsString() == $ex_less_authors){
                        $response->match = $candidate;
                        $response->narrative[] = "Found matching authors when ex removed from submitted name.";
                        break;
                    }
                }
            }else{
                $response->narrative[] = "Submitted authors do NOT contain ' ex '. Looking for match in candidates if their ex authors are removed.";
                foreach($response->candidates as $candidate){
                    $ex_less_authors = $this->removeExAuthors($candidate->getAuthorsString());
                    if($response->parsedName->author_string == $ex_less_authors){
                        $response->match = $candidate;
                        $response->narrative[] = "Found matching authors when ex removed from candidate name.";
                        break;
                    }
                }
            }
        }


        // if we have a single candidate and the input name doesn't have 
        // an authorstring then we assume that it is a match 
        if(count($response->candidates) == 1 && strlen($response->parsedName->author_string) == 0){
            $response->match = $response->candidates[0];
            $response->candidates = array();
            $response->narrative[] = "Only one candidate found ({$response->match->getWfoId()}) and no author string supplied so name becomes match.";
        }

        // if we find a single candidate and the search term is an autonym and the match is an autonym and it is the same rank
        // then we match it
        if(
            count($response->candidates) == 1 && count($canonical_parts) == 3 && $canonical_parts[1] == $canonical_parts[2] // search is autonym
            && $response->candidates[0]->getNameString() == $response->candidates[0]->getSpeciesString() // match is autonym // match and search ranks are the same
        ){
            $response->narrative[] = "Only one candidate found ({$response->candidates[0]->getWfoId()}) it is an autonym and so is the supplied name.";
            if($response->candidates[0]->getRank() == $response->parsedName->rank){
                $response->narrative[] = "The ranks are the same so making it a match regardless of any author string.";
                $response->match = $response->candidates[0];
                $response->candidates = array();
            }else{
                $response->narrative[] = "The ranks are not the same ('{$response->candidates[0]->getRank()}' and '{$response->parsedName->rank}') so not a match.";
            }

        }

        // they care about ranks so remove the match if the ranks don't match
        if($response->match && $this->params->checkRank && $response->parsedName->rank != $response->match->getRank()){
            // they want the ranks to match and they don't so demote it
            $response->match = null;
            $response->narrative[] = "Checked ranks and they didn't match.";
        }

        // they don't care about homonyms so we can scrub any candidates if we have a match
        if($response->match && $response->candidates && !$this->params->checkHomonyms){
            $response->candidates = array();
            $response->narrative[] = "Homonyms (same name different authors) are considered OK and we have a match so removing candidates.";
        }

        // if we haven't found anything but they would be happy with a genus
        if(!$response->match && @$this->params->fallbackToGenus && count($canonical_parts) > 0){
            
            $response->narrative[] = "No match was found but fallbackToGenus is true so looking for genus.";
            
            $filters = array();
            $filters[] = 'classification_id_s:' . $this->params->classificationVersion;
            $filters[] = 'rank_s:genus';
            $filters[] = '-role_s:deprecated';
            
            $query = array(
                'query' => "name_string_s:" . $canonical_parts[0],
                'filter' => $filters,
                'limit' => $this->params->limit
            );

            $docs = PlantList::getSolrDocs($query);
            $response->candidates = array(); // scrub existing candidates
            foreach($docs as $doc){
                $doc->asName = true;
                $response->candidates[] = new TaxonRecord($doc);
            }

            // do we only have one?
            if(count($response->candidates) == 1){
                $response->narrative[] = "A single genus candidate found so it becomes the match.";
                $response->match = $response->candidates[0];
                $response->candidates = array();
            }else{
                $response->narrative[] = count($response->candidates) . " genus candidates found so no match.";
            }

        }

        // Have we got anything?
        if(!$response->match && count($response->candidates) < 2){


            if(count($response->candidates) == 1 && @$this->params->acceptSingleCandidate){
                
                // a single candidate and that will do for them!
                $response->narrative[] = "A single candidate found and acceptSingleCandidate is true so it becomes the match.";
                $response->match = $response->candidates[0];
                $response->candidates = array();

            }else{

                // no candidates or matches so 
                $response->narrative[] = "No candidates found so moving to relevance searching.";

                $query = array(
                    'query' => "_text_:$response->searchString",
                    'filter' => 'classification_id_s:' . $this->params->classificationVersion,
                    'limit' => $this->params->limit
                );

                $docs = PlantList::getSolrDocs($query);
                foreach($docs as $doc){
                    $doc->asName = true;
                    $response->candidates[] = new TaxonRecord($doc);
                }
            }




        }

        return $response;
    }

    private function removeExAuthors($authors){

        // no ex then just return them
        if(strpos($authors, ' ex ') === false) return $authors;

        // the ex may be in the parenthetical authors
        if(preg_match('/\(.+ ex .+\)/', $authors)){
            return preg_replace('/[^(]+ ex /', '', $authors);
        }else{
            return preg_replace('/^.+ ex /', '', $authors);
        }

    }

    /**
     * A simple alphabetical lookup of names
     * 
     */
    private function alphaMatch($response){

        // we only do it if we have more than 3 characters?
        if(strlen($response->searchString) < 4){
            $response->error = true;
            $response->errorMessage = "Search string must be more than 3 characters long.";
            return $response;
        }

        $name = trim($response->searchString);
        $name = ucfirst($name); // all names start with an upper case letter
        $name = str_replace(' ', '\ ', $name);
        $name = $name . "*";

        $filters = array();
        $filters[] = 'classification_id_s:' . $this->params->classificationVersion;
        if(isset($this->params->excludeDeprecated) && $this->params->excludeDeprecated){
            $filters[] = '-role_s:deprecated'; 
        }

        $query = array(
            'query' => "full_name_string_alpha_s:$name",
            'filter' => $filters,
            'sort' => 'full_name_string_alpha_t_sort asc',
            'limit' => $this->params->limit
        );

        $docs  = $this->getSolrDocs($query);

        if(count($docs) == 1){
            $docs[0]->asName = true;
            $response->match = new TaxonRecord($docs[0]);
        }else{
            foreach ($docs as $doc) {
                $doc->asName = true;
                $response->candidates[] = new TaxonRecord($doc);
            }
        }

        return $response;
    }

}