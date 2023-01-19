<?php

// this is included in ../index.php
// to intercept calls to / and do 
// content negotiation for SW calls 

// path should be of the form /wfo-id/format or /terms/
$path_parts = explode('/', $_SERVER["REQUEST_URI"]);
array_shift($path_parts); // lose the first blank one
$wfo = $path_parts[0];

if(
    preg_match('/^wfo-[0-9]{10}$/', $wfo) // name wfo
    || preg_match('/^wfo-[0-9]{10}-[0-9]{4}-[0-9]{2}$/', $wfo) // taxon wfo
    || preg_match('/^[0-9]{4}-[0-9]{2}$/', $wfo) // classification
  ){

    $format_string = null;
    $formats = \EasyRdf\Format::getFormats();
    
//print_r($formats);

    // we are being called as a stable URI
    // redirect to an appropriate place

    // try and get the format from the http header
    $format_string = null;
    $headers = getallheaders();
    if(isset($headers['Accept'])){
        $mimes = explode(',', $headers['Accept']);
    
        foreach($mimes as $mime){

            // edge case. If they ask for html we pretend they asked 
            // for nothing so we can send them to human readable
            // if not the library will send them to rdfa
            if($mime == 'text/html' || $mime == 'application/xhtml+xml') break;

            foreach($formats as $format){
                $accepted_mimes = $format->getMimeTypes();
                foreach($accepted_mimes as $a_mime => $weight){
                    if($a_mime == $mime){
                        $format_string = $format->getName();
                        break;
                    }
                }
                if($format_string) break;
            }
            if($format_string) break;
        }
    }

    // whatever happens they are asking for a record
    // and we need to check it exists
    if(preg_match('/^wfo-[0-9]{10}$/', $wfo)){
        // we have an unqualified wfo id we need to convert it to the latest classification
        $wfo_qualified_id = $wfo . '-' . WFO_DEFAULT_VERSION;
    }else{
        // wfo is already qualified
        $wfo_qualified_id = $wfo;
    }

    // check it exists
    $record = new TaxonRecord($wfo_qualified_id);

    // get out of here if record doesn't exist
    // and we aren't just asking for a classification (which we assume does exist)
    if(!$record->exists() && !preg_match('/^[0-9]{4}-[0-9]{2}$/', $wfo)){
        header("HTTP/1.1 404 Not Found");
        echo "Not found: {$wfo}";
        exit;
    }

    if(!$format_string){
        
        // they haven't passed a format request 
        // so we assume they are human and pass them 
        // to the Plant List pages
        
        if(preg_match('/^[0-9]{4}-[0-9]{2}$/', $wfo)){
            // they are just asking for a classification
            $redirect_url = "https://wfoplantlist.org/plant-list/" . $path_parts[0];
        }else{

            switch ($record->getRole()) {
                case 'accepted':
                    $redirect_url = "https://wfoplantlist.org/plant-list/taxon/{$record->getId()}";
                    break;
                case 'synonym':
                    $syn_wfo = substr($record->getId(), 0, 14);
                    $redirect_url = "https://wfoplantlist.org/plant-list/taxon/{$record->getAcceptedId}?matched_id={$syn_wfo}";
                    break;
                case 'unplaced':
                    $redirect_url = "https://wfoplantlist.org/plant-list/taxon/{$record->getId()}";
                    break;
                case 'deprecated':
                    $redirect_url = "https://wfoplantlist.org/plant-list/taxon/{$record->getId()}";
                    break;
                default:
                    echo "Unknown role type: {$record->getRole()}";
                    exit;
                    break;
            }

        }

    }else{

        // we have a format string so we redirect to the metadata call

        // are they asking for a name but with classification qualifier?
        // acceptable for humans but not data
        if(preg_match('/^wfo-[0-9]{10}-[0-9]{4}-[0-9]{2}$/', $wfo) && $record->isName()){
                // they are asking for a name but have included a classification version - implying a taxon
                // simply redirect to the name.
                $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                    . "://$_SERVER[HTTP_HOST]/"
                    . substr($wfo, 0,14);
                header("Location: $redirect_url",TRUE,301);
                echo "Moved: Name not taxon";
                exit;
        }

        // 303 redirect to the format version
        // works for names and even classifications
        $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
            . "://{$_SERVER['HTTP_HOST']}/sw_data.php?wfo=$wfo&format=$format_string";

    }

    // finally redirect them and exit
    // always 303 redirect from the core object URIs
    header("Location: $redirect_url",TRUE,303);
    echo "Found: Redirecting to data";
    exit;
    
}
