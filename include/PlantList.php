<?php

/**
 * This is the root class from which
 * all the other classes in the application 
 * inherit.
 */
class PlantList{


    public function getCurlHandle($uri){
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WFO Plant List');
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, SOLR_USER . ":" . SOLR_PASSWORD);
        return $ch;
    }

    /**
     * Run the cURL requests in one place 
     * so we can catch errors etc
     */
    function runCurlRequest($curl){
    
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

    function curlPostJson($uri, $json){
        $ch = $this->getCurlHandle($uri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $response = $this->runCurlRequest($ch);
        return $response;
    }

    /**
     * cURL returns headers as sting so we need to chop them into
     * a useable array - even though the info is in the 
     */
    function getHeadersFromCurlResponse($out){
        
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

    function runSolrQuery($query){
        $solr_query_uri = SOLR_QUERY_URI . '/query';
        $response = $this->curlPostJson($solr_query_uri, json_encode($query));
        $data = json_decode($response->body);
        return $data->response->docs;
    }

}
