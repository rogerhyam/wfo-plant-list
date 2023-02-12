<?php

require_once('../include/NameMatcher.php');
require_once('../include/TaxonRecord.php');

/*
    Implementation of this
    https://reconciliation-api.github.io/specs/latest/

http://localhost:2001/reconcile?queries=%7B%22q0%22%3A%7B%22query%22%3A%22Agropyron+unilaterale+Cassidy%22%7D%7D
https://list-dev.rbge.info/reconcile?queries=%7B%22q0%22%3A%7B%22query%22%3A%22Agropyron+unilaterale+Cassidy%22%7D%7D

*/

class ReconciliationService extends PlantList{

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
        
        $response = array();

        foreach($this->queries as $id => $query){
            $response[$id] = $this->getQueryResponse($query);
        }

        return (object)$response;

    }

    private function getQueryResponse($query){

        // actually run the query!

        $response = new stdClass();
        $response->result = array();

        $config = new stdClass();
        $config->method = 'full';
        $config->limit = 10;
        
        // FIXME: add homonyms and/or rank flags    
        
        $matcher = new NameMatcher($config);
        $matches = $matcher->match($query->query);

        if($matches->match){
            // we have a single hit
            $response->result[] = $this->getCandidate($matches->match, 100, true);
        }else{
            // we have multiple candidates
            for ($i=0; $i < count($matches->candidates); $i++) { 
                $candi = $matches->candidates[$i];
                $response->result[] = $this->getCandidate($candi, count($matches->candidates) - $i ,false);
            }

        }

        return $response;

    }

    private function getCandidate($name, $score, $is_match){
            $candidate = new stdClass();
            $candidate->id = $name->getWfoId();
            $candidate->name =  $name->getFullNameStringPlain();
            $candidate->description =  $name->getCitationMicro() ? $name->getCitationMicro(): '';
            $candidate->match = $is_match;
            $candidate->types = array('TaxonName');
            $candidate->score = $score;

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

        // preview the names
        $manifest->preview = (object)array(
            'url' => get_uri('reconcile?preview={{id}}'),
            'width' => 300,
            'height' => 100
        );

        // suggest based on first few characters
        $manifest->suggest = new stdClass();

        $manifest->suggest->entity = (object)array(
            'service_url' => get_uri('reconcile'),
            'service_path' => ''
        );

        return $manifest;


    }

    /**
     * returns a small preview of the 
     * name for use as a popup
     */
    public function getPreview($wfo){

        if(!preg_match('/^wfo-[0-9]{10}$/', $wfo)){
            $out = "Malformed WFO ID: '$wfo'";
        }else{
            $name = new TaxonRecord($wfo);

            if($name->exists()){

                $out = "<p>";
                $out = "<strong>" . $name->getFullNameStringHtml() . "</strong>";
                $out .= "<br/>";
                $out .= $name->getCitationMicro();
                $out .= "<br/>";
                $out .= $name->getNomenclaturalStatus();
                $out .= ":&nbsp;";
                $out .= $name->getRank();
                $out .= "</p>";

            }else{
                // name couldn't be loaded
                $out = "Couldn't load name";
            }

        }

        return "
            <html>
            <head><meta charset=\"utf-8\" /></head>
            <body>$out</body>
            </html>
        ";

    }

    public function getSuggestions($input_string){

        $matcher = new NameMatcher((object)array('limit' => 30, 'method' => 'alpha'));
        $match_response = $matcher->match($input_string);

        if($match_response->match){
            $candidates = array($match_response->match); // we have a perfect match
        }else{
            $candidates = $match_response->candidates;
        }

        $output_response = new stdClass();
        $output_response->result = array();
        
        foreach($candidates as $candidate){
            $output_response->result[] = (object)array(
                'id' => $candidate->getWfoId(),
                'name' => $candidate->getFullNameStringPlain(),
                'description' => $candidate->getCitationMicro()
            );
        }

        return $output_response;
        
    }


} // end class