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
            default:
                throw new ErrorException("Unrecognized matching method {$this->params->method}");
                break;
        }
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