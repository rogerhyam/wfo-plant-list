<?php

 /**
 * This is a wrapper around a SOLR doc
 * It is used for direct access to the index
 * by the explorer and stats pages
 * 
 */
class SolrIndex{

    public function getSolrDoc($id){

        $id = trim($id);

        // malformed wfo_ids are rejected
        if(preg_match('/^wfo-[0-9]{10}$/', $id)){
            $record_id = $id . '-' . WFO_DEFAULT_VERSION;
        }else{
            $record_id = $id;
        }

        // load it by id
        $solr_query_uri = SOLR_QUERY_URI . '/get?id=' . $record_id;
        $ch = $this->getCurlHandle($solr_query_uri);
        $response = $this->runCurlRequest($ch);
        if(isset($response->body)){
            $body = json_decode($response->body);
            if(isset($body->doc)){
                return $body->doc;                
            }
        }
        return null;
    }

    /**
     * 
     * 
     */
    public function getSolrDocs($query){
        $data = $this->getSolrResponse($query);
        if(isset($data->response->docs)) return $data->response->docs;
        else print_r($data); //return null;
    }

    public  function getSolrResponse($query){
        $solr_query_uri = SOLR_QUERY_URI . '/query';
        $response = $this->curlPostJson($solr_query_uri, json_encode($query));
        if(isset($response->body)) return json_decode($response->body);
        else return json_decode($response);
    }

    public  function getCurlHandle($uri){
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WFO Facet Service');
        curl_setopt($ch, CURLOPT_USERPWD, SOLR_USER . ":" . SOLR_PASSWORD);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        // we are read only so 
        // curl_setopt($ch, CURLOPT_USERPWD, SOLR_USER . ":" . SOLR_PASSWORD);
        return $ch;
    }

    /**
     * Run the cURL requests in one place 
     * so we can catch errors etc
     */
    public  function runCurlRequest($curl){
    
        $out['response'] = curl_exec($curl);  
        $out['error'] = curl_errno($curl);
        
        if(!$out['error']){
            // no error
            $out['info'] = curl_getinfo($curl);
            $out['headers'] = $this->getHeadersFromCurlResponse($out);
            $out['body'] = trim(substr($out['response'], $out['info']["header_size"]));

        }else{
            // we are in error
            $out['error_message'] = curl_error($curl);
        }
        
        // we close it down after it has been run
        curl_close($curl);
        
        return (object)$out;
        
    }

    public  function curlPostJson($uri, $json){
        $ch = $this->getCurlHandle($uri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_USERPWD, SOLR_USER . ":" . SOLR_PASSWORD);
        $response = $this->runCurlRequest($ch);
        return $response;
    }

    /**
     * cURL returns headers as sting so we need to chop them into
     * a useable array - even though the info is in the 
     */
    public  function getHeadersFromCurlResponse($out){
        
        $headers = array();
        
        // may be multiple header blocks - we want the last
        $headers_block = substr($out['response'], 0, $out['info']["header_size"]-1);
        $blocks = explode("\r\n\r\n", $headers_block);
        $header_text = trim($blocks[count($blocks) -1]);

        foreach (explode("\r\n", $header_text) as $i => $line){
            if ($i === 0){
                $headers['http_code'] = $line;
            }else{
                list ($key, $value) = explode(': ', $line);
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    function getBodyFromCurlResponse($response){
        return trim(substr($response, strpos($response, "\r\n\r\n")));
    }


}