<?php

/**
 * This is a wrapper around a SOLR doc representing taxa and names
 * It loads itself directly from the SOLR index.
 */
class TaxonRecord extends PlantList{

    private ?Object $solrDoc = null;
    private ?Array $usages = null;

    // private properties available through GraphQL Type

    private ?String $id = null;
    private ?String $title = null;
    private ?String $wfoId = null;
    private ?String $classificationId = null;
    private ?Classification $classification = null;
    
    private ?String $stableUri = null;
    
    private ?String $fullNameStringHtml = null;
    private ?String $fullNameStringPlain = null;
    private ?String $fullNameStringNoAuthorsPlain = null;
    private ?String $nameString = null;
    private ?String $genusString = null;
    private ?String $speciesString = null;

    private ?String $authorsString = null;
    private ?String $authorsStringHtml = null;

    private ?String $nomenclaturalStatus = null;
    private ?String $role = null;
    
    private ?String $rank = null;

    private ?String $citationMicro = null;
    private ?String $comment = null;

    private bool $isName;

    private array $identifiersOther = array();
    private array $wfoIdsDeduplicated = array();

    private ?TaxonRecord $currentUsage = null;
    private ?TaxonRecord $parent = null;
    private ?Array $synonyms = null;
    private ?Array $unplacedNames = null;
    private int $childCount = -1; 

    private ?String $wfoPath = null;

    /**
     * Create a new wrapper around a SOLR doc
     */
    public function __construct($init_val){

        if(is_object($init_val)){

            // we have been given the solr doc (probably from a search)
            $this->solrDoc = $init_val;

            // there is a flag to say if we are supposed to be a name
            // or a taxon on loading.
            if(isset($this->solrDoc->asName) && $this->solrDoc->asName){
                // we are a name
                $this->isName = true;
            }else{
                // we are a taxon
                $this->isName = false;
            }

        }else{

            // if we are passed an unqualified WFO (getting name) then we qualify it with the 
            // current version 
            if(preg_match('/^wfo-[0-9]{10}$/', $init_val)){
                $init_val_full = $init_val . '-' . WFO_DEFAULT_VERSION;
                $this->isName = true;
            }else{
                $init_val_full = $init_val;
                $this->isName = false;
            }

            // load it by id
            $solr_query_uri = SOLR_QUERY_URI . '/get?id=' . $init_val_full;
            $ch = PlantList::getCurlHandle($solr_query_uri);
            $response = PlantList::runCurlRequest($ch);
            if(isset($response->body)){
                $body = json_decode($response->body);
                if(isset($body->doc)){
                    $this->solrDoc = $body->doc;
                }
            }

            // if we haven't got it yet try and load it by it being a deduplicated wfo ID
            // it will be a name
            if(!$this->solrDoc && preg_match('/^wfo-[0-9]{10}$/', $init_val)){
                $query = array( 
                    'query' => "wfo_id_deduplicated_ss:$init_val",
                    'filter' => ['classification_id_s:' . WFO_DEFAULT_VERSION ],
                );
                $docs = PlantList::getSolrDocs($query);
                if(count($docs) > 0){
                    $this->solrDoc = $docs[0];
                }
            }


        }

        if(!$this->solrDoc) return; // failed to load.

        // customize on if we are a name or not
        if($this->isName){
            $this->isName = true;
            $this->id = $this->solrDoc->wfo_id_s;
            $this->classificationId = null;
            $this->stableUri = 'https://list.worldfloraonline.org/' . $this->solrDoc->wfo_id_s;
        }else{
            $this->isName = false;
            $this->id = $this->solrDoc->id;
            $this->classificationId = $this->solrDoc->classification_id_s;
            $this->stableUri = 'https://list.worldfloraonline.org/' . $this->solrDoc->id;
        }

        // initialize all the simple fields that don't need another call
        
        // common properties
        $this->title                = $this->solrDoc->full_name_string_plain_s;
        $this->wfoId                = $this->solrDoc->wfo_id_s;
        $this->fullNameStringHtml   = $this->solrDoc->full_name_string_html_s;
        $this->fullNameStringPlain  = $this->solrDoc->full_name_string_plain_s;
        $this->fullNameStringNoAuthorsPlain     = isset($this->solrDoc->full_name_string_no_authors_plain_s) ? $this->solrDoc->full_name_string_no_authors_plain_s : null;
        $this->nameString           = $this->solrDoc->name_string_s;
        $this->genusString          = isset($this->solrDoc->genus_string_s) ? $this->solrDoc->genus_string_s : null;
        $this->speciesString        = isset($this->solrDoc->species_string_s) ? $this->solrDoc->species_string_s : null;
        $this->authorsString        = isset($this->solrDoc->authors_string_s) ? $this->solrDoc->authors_string_s: null;
        $this->authorsStringHtml    = isset($this->solrDoc->authors_string_html_s) ? $this->solrDoc->authors_string_html_s: null;
        $this->nomenclaturalStatus  = isset($this->solrDoc->nomenclatural_status_s) ? $this->solrDoc->nomenclatural_status_s : null;
        $this->role                 = $this->solrDoc->role_s;
        $this->rank                 = $this->solrDoc->rank_s;
        $this->citationMicro        = isset($this->solrDoc->citation_micro_s) ? $this->solrDoc->citation_micro_s : null;
        $this->comment              = isset($this->solrDoc->comment_t) ? $this->solrDoc->comment_t : null;


        // identifiers other
        if(isset($this->solrDoc->identifiers_other_kind_ss)){
            for ($i=0; $i < count($this->solrDoc->identifiers_other_kind_ss); $i++) { 
                $identifier = array();
                $identifier['kind'] = $this->solrDoc->identifiers_other_kind_ss[$i];
                $identifier['value'] = $this->solrDoc->identifiers_other_value_ss[$i];
                $this->identifiersOther[] = (object)$identifier;
            }
        }

        // dedupe wfo ids
        if(isset($this->solrDoc->wfo_id_deduplicated_ss)){
            $this->wfoIdsDeduplicated = $this->solrDoc->wfo_id_deduplicated_ss;
        }

        // we have wfo path which is a useful hint for where this name might be
        if(isset($this->solrDoc->name_path_s)){
            switch ($this->role) {
                case 'deprecated':
                    $this->wfoPath = 'DEPRECATED';
                    break;
                case 'unplaced':
                    $this->wfoPath = 'UNPLACED';
                    break;
                case 'synonym':
                    $this->wfoPath = $this->solrDoc->name_path_s . "$" . str_replace(' ', '/', $this->solrDoc->full_name_string_alpha_s);
                    break;
                case 'accepted':
                    $this->wfoPath = $this->solrDoc->name_path_s;
                    break;
                default:
                    $this->wfoPath = '';
                    break;
            }
        }else{
            $this->wfoPath = '';
        }

    }

    /**
     * Check we have loaded the document from SOLR
     */
    public function exists(){
        return $this->solrDoc ? true : false;
    }


    public function getAcceptedId(){
        if(!$this->exists()) return null;
        if(!isset($this->solrDoc->accepted_id_s)) return null;
        return $this->solrDoc->accepted_id_s;
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
            $this->usages = $this->loadTaxonRecords($query, false);
        }

        return $this->usages;

    }

    public function getCurrentUsage(){

        if(!$this->exists()) return null;

        $current_me = new TaxonRecord($this->solrDoc->wfo_id_s . "-" . WFO_DEFAULT_VERSION);
        
        if(isset($current_me->solrDoc->accepted_id_s)){
            // we are a synonym - return the accepted name we belong to
            return new TaxonRecord($current_me->solrDoc->accepted_id_s);
        }elseif($current_me->getRole() == 'accepted'){
            // we are an accepted name of a taxon
            return $current_me;
        }else{
            return null;
        }

    }

    public function getReplaces(){

        if(!$this->exists()) return null;
        if($this->getIsName()) return null;

        $replaces = null;
        $usages = $this->getUsages(); // these are in order

        foreach($usages as $use){
            if($use->getIsName()) continue; // only looking at taxa
            if($use->getClassificationId() == $this->getClassificationId()) break; // reached current times
            $replaces = $use;
        }

        return $replaces;

    }

    public function getReplacedBy(){

        if(!$this->exists()) return null;
        if($this->isName) return null;

        $replaced_by = null;
        $usages = $this->getUsages(); // these are in order
        $usages = array_reverse($usages);

        foreach($usages as $use){
            if($use->getIsName()) continue; // only looking at taxa
            if($use->getClassificationId() == $this->getClassificationId()) break; // reached current times
            $replaced_by = $use;
        }

        return $replaced_by;

    }

    public function getParent(){

        if(!$this->exists()) return null;
        if($this->getIsName()) return null;

        if(!$this->parent && isset($this->solrDoc->parent_id_s)){
            $this->parent = new TaxonRecord($this->solrDoc->parent_id_s);
        } 

        return $this->parent;

    }

    public function getPath(){
        $path = array($this);
        if($this->getParent()){
            return array_merge($path, $this->getParent()->getPath());
        }else{
            return $path;
        }

    }

    public function getChildren($limit = -1, $offset = 0){

        if(!$this->exists()) return null;
        if($this->isName) return null;

        $query = array(
            'query' => 'parent_id_s:' . $this->solrDoc->id,
            'offset' => $offset,
            'sort' => 'full_name_string_alpha_s asc'
        );

        // -1 is unlimited but in Solr you just miss the parameter 
        if($limit >= 0){
            $query['limit'] = $limit;
        }

        return $this->loadTaxonRecords($query, false);

    }

    public function getChildCount(){
                
        if($this->childCount !== -1) return $this->childCount;

        $query = array(
            'query' => 'parent_id_s:' . $this->solrDoc->id,
            'fields' => 'id',
            'limit' => 0,
            'offset' => 0,
        );
        $response = PlantList::getSolrResponse($query);

        if(isset($response->response->numFound)){
            $this->childCount = $response->response->numFound;
        }

        return $this->childCount;

    }

    public function getSynonyms(){

        if(!$this->exists()) return null;
        if($this->isName) return null;

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

    public function getUnplacedNames(){

        if(!$this->exists()) return null;
        if($this->isName) return null;
        if($this->getRank() != 'genus') return null;
        
        if(!$this->unplacedNames){

            $query = array(
                'query' => 'genus_string_s:' . $this->solrDoc->name_string_s,
                'filter' => 'role_s:unplaced',
                'limit' => 1000000,
                'sort' => 'full_name_string_plain_s asc'
            );

            $this->unplacedNames = $this->loadTaxonRecords($query);

        }

        return $this->unplacedNames;

    }


    public function getNomenclaturalReferences(){
        if(!$this->exists()) return null;
        return $this->getReferences(true);
    }

    public function getTaxonomicReferences(){
        if(!$this->exists()) return null;
        if($this->getIsName()) return null;
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
                $ref['thumbnailUri'] = $this->solrDoc->reference_thumbnail_uris_ss[$i] == '-' ? null : $this->solrDoc->reference_thumbnail_uris_ss[$i];
                $ref['comment'] = $this->solrDoc->reference_comments_ss[$i] == '-' ? null : $this->solrDoc->reference_comments_ss[$i];

                $references[] = (object)$ref;      
        
            }
        }


        return $references;

    }

    public function loadTaxonRecords($query, $as_name = true){

        $records = array();

        $docs = PlantList::getSolrDocs($query);
        foreach($docs as $doc){
            $doc->asName = $as_name;
            $records[] = new TaxonRecord($doc);
        }

        return $records;

    }


    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of title
     */ 
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get the value of wfoId
     */ 
    public function getWfoId()
    {
        return $this->wfoId;
    }

    /**
     * Get the value of isName
     */ 
    public function getIsName()
    {
        return $this->isName;
    }

    /**
     * Get the value of classificationId
     */ 
    public function getClassificationId()
    {
        return $this->classificationId;
    }

    /**
     * Get the value of rank
     */ 
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * Get the value of fullNameStringHtml
     */ 
    public function getFullNameStringHtml()
    {
        return $this->fullNameStringHtml;
    }

    /**
     * Get the value of fullNameStringPlain
     */ 
    public function getFullNameStringPlain()
    {
        return $this->fullNameStringPlain;
    }

    /**
     * Get the value of authorsString
     */ 
    public function getAuthorsString()
    {
        return $this->authorsString;
    }

    /**
     * Get the value of genusString
     */ 
    public function getGenusString()
    {
        return $this->genusString;
    }

    /**
     * Get the value of speciesString
     */ 
    public function getSpeciesString()
    {
        return $this->speciesString;
    }

    /**
     * Get the value of citationMicro
     */ 
    public function getCitationMicro()
    {
        return $this->citationMicro;
    }

    /**
     * Get the value of comment
     */ 
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Get the value of role
     */ 
    public function getRole()
    {
        return $this->role;
    }

    /**
     * Get the value of identifiersOther
     */ 
    public function getIdentifiersOther()
    {
        return $this->identifiersOther;
    }

    /**
     * Get the value of wfoIdsDeduplicated
     */ 
    public function getWfoIdsDeduplicated()
    {
        return $this->wfoIdsDeduplicated;
    }

    /**
     * Get the value of stableUri
     */ 
    public function getStableUri()
    {
        return $this->stableUri;
    }

    /**
     * Get the value of fullNameStringNoAuthorsPlain
     */ 
    public function getFullNameStringNoAuthorsPlain()
    {
        return $this->fullNameStringNoAuthorsPlain;
    }

    /**
     * Get the value of fullNameStringNoAuthorsHtml
     */ 
    public function getFullNameStringNoAuthorsHtml()
    {
        // this one doesn't exist in the index so we 
        // create it for completeness
        return trim(preg_replace('/<span\s*class="wfo-name-authors"\s*>.+<\/span>/', '', $this->fullNameStringHtml));
    }


    /**
     * TaxonConcepts only will return 
     * their name
     */
    public function getName(){
        if($this->isName) return null;

        // the name based on the 10 digit wfo id
        return new TaxonRecord(substr($this->id, 0, 14));

    }

    /**
     * Get the value of nameString
     */ 
    public function getNameString()
    {
        return $this->nameString;
    }

    public function getWfoPath()
    {
        return $this->wfoPath;
    }


    /**
     * Get the value of nomenclaturalStatus
     */ 
    public function getNomenclaturalStatus()
    {
        return $this->nomenclaturalStatus;
    }

    /**
     * Get the value of authorsStringHtml
     */ 
    public function getAuthorsStringHtml()
    {
        return $this->authorsStringHtml;
    }

    /**
     * Get the value of classification
     */ 
    public function getClassification(){

        if(!$this->classification){
            $this->classification = Classification::getById($this->classificationId);
        }

        return $this->classification;
    }

    public function getStats(){

        // no stats for names
        if($this->isName) return null;

        // no stats for taxa without paths
        if(!isset($this->solrDoc->name_descendent_path)) return null;

        // return value
        $stats = array();

        $query = array(
            'query' => 'name_descendent_path:' . $this->solrDoc->name_descendent_path, 
            'filter' => 'classification_id_s:' . $this->solrDoc->classification_id_s,
            'facet' => array(
                "role" => array(
                    "type" => "terms",
                    "field" => "role_s",
                    'limit' => 10,
                    'facet' => array(
                        "rank" => array(
                            "type" => "terms",
                            "field" => "rank_s",
                            'limit' => 100
                        )
                    )
                ),
                "rank" => array(
                    "type" => "terms",
                    "field" => "rank_s",
                    'limit' => 100,
                    'facet' => array(
                        "role" => array(
                            "type" => "terms",
                            "field" => "role_s",
                            'limit' => 10
                        )
                    )
                ),
            ),
            'limit' => 0
        );

        // if we are a genus then we include unplaced names
        if($this->solrDoc->rank_s == 'genus'){
            $query['query'] .= " OR (genus_string_s:{$this->solrDoc->name_string_s} AND role_s:unplaced) ";
        }

        $response = PlantList::getSolrResponse($query);

        if(isset($response->facets)){

            foreach($response->facets as $upper_facet_name => $upper_facet){
                if($upper_facet_name == 'count') continue; 
                foreach ($upper_facet->buckets as $upper_bucket) {
                    $stats[] = new TaxonConceptStat($upper_facet_name ."-". $upper_bucket->val, "Total names with $upper_facet_name:'{$upper_bucket->val}'", $upper_bucket->count);
                    foreach($upper_bucket as $lower_facet_name => $lower_facet){
                        if($lower_facet_name == 'val') continue; 
                        if($lower_facet_name == 'count') continue;
                        foreach($lower_facet->buckets as $lower_bucket){
                            $stats[] = new TaxonConceptStat($upper_facet_name ."-". $upper_bucket->val ."-". $lower_facet_name ."-". $lower_bucket->val, "Total names with $upper_facet_name:'{$upper_bucket->val}' and $lower_facet_name:'{$lower_bucket->val}'", $lower_bucket->count);    
                        }
                    }
                }
            }

        }

        return $stats;
    }



}
