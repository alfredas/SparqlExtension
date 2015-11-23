<?php

require_once("../../maintenance/commandLine.inc");
require_once("SesameStore.php");
require_once("JosekiStore.php");

echo "N.B. About to insert ALL pages in the wiki into your endpoint! This might take a while.\n";

$import = new ImportPages();

/**
 * utility script that import ALL pages into sparql endpoint
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class ImportPages {

    public function __construct() {
    	global $smwgDefaultStore;
    	
        $pageids = $this->get_pages();
        
        if ($smwgDefaultStore == "SesameStore") {
        	$endpoint = new SesameStore();
        }
        else {
        	$endpoint = new JosekiStore();
        }
        
        foreach ($pageids as $pid) {
            $title = Title::newFromID($pid);
            
            echo "Inserting ".$title->getDBkey()."...";
            $endpoint->insertData($title, $pid);
            echo "...done\n";
        }  
	}
    
    protected function get_pages() {
        $res = array();
        $continued = true;
        $apfrom = '';
        while ($continued) {        
            $query_url = $this->create_query_url($apfrom);
			echo "getting url:".$query_url."\n";
        	$query_handle = @fopen($query_url, "rb");
            if ($query_handle) {
                $query_output = @stream_get_contents($query_handle);
                fclose($query_handle);
                $json = json_decode($query_output, true);
                $pages = $json["query"]["allpages"];
                foreach ($pages as $page) {
                    $res[] = $page["pageid"];
                    echo "Added page ".$page["title"]."\n";
                }
                if (!empty($json["query-continue"])) {
                    $apfrom = $json["query-continue"]["allpages"]["apfrom"];
                } else {
                    $continued = false;
                }
            } else {
                $continued = false;
            }
        }
        return $res;
    }
    
	function create_query_url($apfrom = '') {
        global $wgScriptPath;
        $query = "http://localhost".$wgScriptPath."/api.php?action=query&list=allpages&aplimit=max&format=json&apfrom=".urlencode($apfrom);
	    return $query;
	}

}
