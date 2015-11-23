<?php
if( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not a valid entry point.' );
}
/**
 * Provides static methods to create wiki links, depending on parameter(s).
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlLinker {

    public static function createLink($link_mode, $uri) {
        global $smwgNamespace;
        $pos = strpos($uri, $smwgNamespace);
        if ($pos !== false) {
        	$uri = SMWExporter::decodeURI($uri);
            $len = strlen($smwgNamespace);
            $page = substr($uri, $len);
            $page = str_replace("_", " ", $page);
            $is_category = ((strpos($page, "Category:") === false) && (strpos($page, "Category%3A") === false)) ? false : true;
            $link = "";
            if ($link_mode) {
                if ($is_category) {
                    $link = "[[:".$page."]]";
                } else {
                    $link = "[[".$page."]]";
                }
            } else {
                $link = $page;
            }
            return $link;
        }
        return $uri;    
    
    }


}



