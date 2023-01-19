<?php

/**
 * This is a wrapper around a SOLR doc representing taxa and names
 * It loads itself directly from the SOLR index.
 */
class TaxonRecord extends PlantList{

    private ?Object $solrDoc = null;
    private ?Array $usages = null;
    private ?TaxonRecord $currentUsage = null;
    private ?TaxonRecord $parent = null;
    private ?Array $children = null;
    private ?Array $synonyms = null;

    /**
     * Create a new wrapper around a SOLR doc
     */
    public function __construct($init_val){


        if(is_object($init_val)){

            // we have been given the solr doc (probably from a search)
            $this->solrDoc = $init_val;

        }else{

            // load it by id
            $solr_query_uri = SOLR_QUERY_URI . '/get?id=' . $init_val;
            $ch = $this->getCurlHandle($solr_query_uri);
            $response = $this->runCurlRequest($ch);
            if(isset($response->body)){
                $body = json_decode($response->body);
                if(isset($body->doc)){
                    $this->solrDoc = $body->doc;
                }
            }
        }

    }

    /**
     * Check we have loaded the document from SOLR
     */
    public function exists(){
        return $this->solrDoc ? true : false;
    }

    public function getId(){
        if(!$this->exists()) return null;
        return $this->solrDoc->id;
    }


    public function getClassificationId(){
        if(!$this->exists()) return null;
        if($this->isName()) return null;
        return $this->solrDoc->classification_id_s;
    }



    public function getWfoId(){
        if(!$this->exists()) return null;
        return $this->solrDoc->wfo_id_s;
    }

    public function getAcceptedId(){
        if(!$this->exists()) return null;
        if(!isset($this->solrDoc->accepted_id_s)) return null;
        return $this->solrDoc->accepted_id_s;
    }

    public function isName(){
    
        if(!$this->exists()) return false; // we don't exist so can't be a name

        if(in_array($this->solrDoc->role_s, array('synonym', 'unplaced', 'deprecated'))){
            return true;
        }else{
            return false;
        }
    
    }

    public function getRole(){
        if(!$this->exists()) return null;
        return $this->solrDoc->role_s;
    }

    public function getRank(){
        if(!$this->exists()) return null;
        return $this->solrDoc->rank_s;
    }

    public function getNameStringPlain(){
        if(!$this->exists()) return null;
        return $this->solrDoc->full_name_string_plain_s;
    }

    public function getAuthorsString(){
        if(!$this->exists()) return null;
        return $this->solrDoc->authors_string_s;
    }

    public function getGenusString(){
        if(!$this->exists()) return null;
        if(isset($this->solrDoc->genus_string_s))  return $this->solrDoc->genus_string_s;
        return null;
    }

    public function getSpeciesString(){
        
        if(!$this->exists()) return null;

        if(isset($this->solrDoc->species_string_s)){
            return $this->solrDoc->species_string_s;
        }

        if($this->getRank() == 'species'){
            return $this->solrDoc->name_string_s;
        }

        return null;
        
    }

    public function getCitationMicro(){
        if(!$this->exists() || !isset($this->solrDoc->citation_micro_s)) return null;
        return $this->solrDoc->citation_micro_s;
    }
    
    public function getBasionymId(){
        if(!$this->exists() || !isset($this->solrDoc->basionym_id_s)) return null;
        return $this->solrDoc->basionym_id_s;
    }


    public function getUsages(){

        if(!$this->exists()) return array();

        // if we haven't previously loaded them
        if(!$this->usages){
            $query = array(
                'query' => 'wfo_id_s:' . $this->getWfoId(),
                'sort' => 'classification_id_s asc'
            );
            $this->usages = $this->loadTaxonRecords($query);
        }

        return $this->usages;

    }

    public function getCurrentUsage(){

        if(!$this->exists()) return null;

        // are we the current usage?
        if($this->solrDoc->classification_id_s == WFO_DEFAULT_VERSION) return $this;

        return new TaxonRecord($this->solrDoc->wfo_id_s . "-" . WFO_DEFAULT_VERSION);

    }

    public function getReplaces(){

        if(!$this->exists()) return null;
        if($this->isName()) return null;

        $replaces = null;
        $usages = $this->getUsages(); // these are in order

        foreach($usages as $use){
            if($use->isName()) continue; // only looking at taxa
            if($use->getClassificationId() == $this->getClassificationId()) break; // reached current times
            $replaces = $use;
        }

        return $replaces;

    }

    public function getReplacedBy(){

        if(!$this->exists()) return null;
        if($this->isName()) return null;

        $replaced_by = null;
        $usages = $this->getUsages(); // these are in order
        $usages = array_reverse($usages);

        foreach($usages as $use){
            if($use->isName()) continue; // only looking at taxa
            if($use->getClassificationId() == $this->getClassificationId()) break; // reached current times
            $replaced_by = $use;
        }

        return $replaced_by;

    }

    public function getParent(){

        if(!$this->exists()) return null;
        if($this->isName()) return null;

        if(!$this->parent && isset($this->solrDoc->parent_id_s)){
            $this->parent = new TaxonRecord($this->solrDoc->parent_id_s);
        } 

        return $this->parent;

    }

    public function getChildren(){

        if(!$this->exists()) return null;
        if($this->isName()) return null;

        if(!$this->children){

            $query = array(
                'query' => 'parent_id_s:' . $this->solrDoc->id,
                'limit' => 1000000,
                'sort' => 'id asc'
            );

            $this->children = $this->loadTaxonRecords($query);

        }

        return $this->children;

    }

    public function getSynonyms(){

        if(!$this->exists()) return null;
        if($this->isName()) return null;

        if(!$this->synonyms){

            $query = array(
                'query' => 'accepted_id_s:' . $this->solrDoc->id,
                'limit' => 1000000,
                'sort' => 'id asc'
            );

            $this->synonyms = $this->loadTaxonRecords($query);

        }

        return $this->synonyms;

    }


    public function getNomenclaturalReferences(){
        if(!$this->exists()) return null;
        return $this->getReferences(true);
    }

    public function getTaxonomicReferences(){
        if(!$this->exists()) return null;
        if($this->isName()) return null;
        return $this->getReferences(false);
    }

    private function getReferences($nomenclatural){

        $references = array();

        if(isset($this->solrDoc->reference_uris_ss)){

            for ($i=0; $i < count($this->solrDoc->reference_uris_ss); $i++) { 
                
                $ref = array();

                // we only return appropriate references for the context    
                if($nomenclatural && $this->solrDoc->reference_contexts_ss[$i] != 'name') continue;
                if(!$nomenclatural && $this->solrDoc->reference_contexts_ss[$i] == 'name') continue;

                // always present
                $ref['uri'] = $this->solrDoc->reference_uris_ss[$i];
                $ref['context'] = $this->solrDoc->reference_contexts_ss[$i];
                $ref['kind'] = $this->solrDoc->reference_kinds_ss[$i];
                $ref['label'] = $this->solrDoc->reference_labels_ss[$i];

                // optional values
                $ref['thumbnail_uri'] = $this->solrDoc->reference_thumbnail_uris_ss[$i] == '-' ? null : $this->solrDoc->reference_thumbnail_uris_ss[$i];
                $ref['comment'] = $this->solrDoc->reference_comments_ss[$i] == '-' ? null : $this->solrDoc->reference_comments_ss[$i];

                $references[] = $ref;      
        
            }
        }


        return $references;

    }

    public function loadTaxonRecords($query){

        $records = array();

        $docs = $this->runSolrQuery($query);
        foreach($docs as $doc){
            $records[] = new TaxonRecord($doc);
        }

        return $records;

    }

}
