<?php

require_once('../include/NameMatcher.php');

/*
    Implementation of this
    https://reconciliation-api.github.io/specs/latest/
*/

class ReconciliationService{

    private $queries;

    public function __construct($queries){
        $this->queries = $queries;
    }

    public function getResponse(){
        // if they haven't passed anything then we return the manifest
        if(!$this->queries){
            return $this->getManifest();
        }else{
            return $this->getQueriesResponse();
        }
    }

    private function getQueriesResponse(){
        
        $response = new stdClass();
        $response->results = array();

        foreach($this->queries as $id => $query){
            $response->results = $this->getQueryResponse($query);
        }

        return $response;

    }

    private function getQueryResponse($query){

        // actually run the query!

        $response = new stdClass();
        $response->candidates = array();

        $config = new stdClass();
        $config->method = 'full';
        
        // FIXME: add homonyms and/or rank flags    
        
        $matcher = new NameMatcher($config);
        $matches = $matcher->match($query->query);

        if($matches->match){
            // we have a single hit
            $response->candidates[] = $this->getCandidate($matches->match, 100, true);
        }else{
            // we have multiple candidates
            for ($i=0; $i < count($matches->candidates); $i++) { 
                $candi = $matches->candidates[$i];
                $response->candidates[] = $this->getCandidate($candi, count($matches->candidates) - $i ,false);
            }

        }

        return $response;

    }

    private function getCandidate($name, $score, $is_match){
            $candidate = new stdClass();
            $candidate->id = $name->getWfoId();
            $candidate->name =  $name->getFullNameStringPlain();
            $candidate->match = $is_match;
            $candidate->type = 'TaxonName';
            $candidate->score = $score;
            $candidate->features = array();

            $candidate->features[] = (object)array(
                'id' => 'fullNameStringPlain',
                'name' => 'The full name including authors but without any markup',
                'value' => $name->getFullNameStringPlain()
            );

            $candidate->features[] = (object)array(
                'id' => 'fullNameStringHtml',
                'name' => 'The full name including authors with HTML tags',
                'value' => $name->getFullNameStringHtml()
            );

            $candidate->features[] = (object)array(
                'id' => 'rank',
                'name' => 'The taxonomic rank of this name',
                'value' => $name->getRank()
            );

            $candidate->features[] = (object)array(
                'id' => 'nomenclaturalStatus',
                'name' => 'The nomenclatural status of this name',
                'value' => $name->getNomenclaturalStatus()
            );

            return $candidate;
    }
    
    private function getManifest(){

        $manifest = new stdClass();

        $manifest->versions = array("0.1", "0.2");
        $manifest->name = "WFO Plant List name matching service.";
        
        // The identifier space used by the service, as a URI;
        $manifest->identifierSpace = get_uri("");

        // The schema space used by the service, as a URI;
        $manifest->schemaSpace = get_uri("terms/");

        /*
            An array of types which are considered sensible default choices as types 
            supplied in reconciliation queries. For services which do not rely on types,
            this MAY contain a single type with a generic name making it clear that all 
            entities in the database are instances of this type.
        */
        $type = new stdClass();
        $type->id = 'TaxonName';
        $type->name = 'Taxonomic Name in the ICNABP' ;
        $manifest->defaultTypes = array($type);

        /*
            An optional URL with human-readable documentation about the service,
            for instance giving more information about the data it exposes;
        */
        $manifest->documentation = get_uri("reconcile_index.php");

        // An optional URL of a square image which can be used as the service's logo;
        $manifest->logo = get_uri('images/logo512.png');

        $manifest->serviceVersion = WFO_SERVICE_VERSION;

        $manifest->view = (object)array( 'url' => get_uri('{{id}}'));

        $manifest->batchSize = 100;

        return $manifest;


    }


} // end class