<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not a valid entry point.' );
}

/**
 * This is the extension's entry point. Does all kinds of configuration, registers hooks and parser extensions.
 * Function Sparql_ParserFunctionRender is responsible for passing the sparql queries to  web-service returning wiki-text/HTML, depending
 * on the format parameter. See http://www.mediawiki.org/wiki/Extension:SparqlExtension for more info.
 * TODO: would be great to integrate this with SMW result formats - much more elaborate implementation.
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */

require_once("SparqlOutputFactory.php");
require_once("JosekiStore.php");
require_once("SesameStore.php");


global $wgExtensionFunctions, $wgHooks;
/*
 * gobal constants
 */
define("SPARQL_EXTENSION_VERSION", "0.7");
define("SPARQL_EXTENSION_NAME", "SparqlExtension v".SPARQL_EXTENSION_VERSION);
define("SPARQL_EXTENSION_AUTHOR", "A.Chmieliauskas@tudelft.nl");
define("SPARQL_EXTENSION_URL", "http://www.mediawiki.org/wiki/Extension:SparqlExtension");
define("SPARQL_EXTENSION_DESCRIPTION", "Connects semantic mediawiki to a Sparql endpoint.");

/*
 * special page setup
 */
$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['SparqlExtension'] = $dir . 'SparqlExtension_body.php'; 
$wgExtensionMessagesFiles['SparqlExtension'] = $dir . 'SparqlExtension.i18n.php'; 
$wgExtensionAliasesFiles['SparqlExtension'] = $dir . 'SparqlExtension.alias.php'; 
$wgSpecialPages['SparqlExtension'] = 'SparqlExtension'; 

//setup function
$wgExtensionFunctions[] = "Sparql_Setup";

// register hooks
$wgHooks["ParserFirstCallInit"][] = "Sparql_ParserFunctionSetup";
$wgHooks["LanguageGetMagic"][]    = "Sparql_ParserFunctionMagic";

/*
 * needed for special page metadata
 */
$wgExtensionCredits['specialpage'][] = array(
    "name" => SPARQL_EXTENSION_NAME,
    "author" => SPARQL_EXTENSION_AUTHOR,
    "url" => SPARQL_EXTENSION_URL,
    "description" => SPARQL_EXTENSION_DESCRIPTION,
);

/*
 * extension setup function
 */
function Sparql_Setup() {
    global $wgDebugLogGroups, $IP;
    // log - make sure this is writeable by php/apache
    $wgDebugLogGroups  = array("SPARQL_LOG" => "$IP/logs/sparql.log");

    // add scripts present in every page (try to keep to minimum)
    addUtilityScripts();
    
    return true;
}

/*
 * parser function setup
 */
function Sparql_ParserFunctionSetup($parser) {
    // Set a function hook associating the "example" magic word with our function
    $parser->setFunctionHook("twinkle", "Sparql_ParserFunctionRender" );
    // no hash needed 
    $parser->setFunctionHook("sparqlencode", "Sparql_SparqlEncode", SFH_NO_HASH);
    return true;
}

/*
 * register magic words
 */
function Sparql_ParserFunctionMagic(&$magicWords, $langCode) {
    $magicWords["twinkle"] = array( 0, "twinkle","sparql");
    $magicWords["sparqlencode"] = array( 0, "sparqlencode");
    return true;
}

/*
 * similar to anchorencode but leaves the slashes intact
 */
function Sparql_SparqlEncode($parser, $text) {
    $a = urlencode( $text );
    $a = strtr( $a, array( '%' => '.', '+' => '_' ) );
    # leave colons alone, however
    $a = str_replace( '.3A', ':', $a );
    # leave forward slashes alone, however
    $a = str_replace( '.2F', '/', $a );
    return $a;
}

/*
 * main function that outputs wiki text or HTML from the query 
 */
function Sparql_ParserFunctionRender(&$parser) {
    
    // get the function arguments
    $argv = func_get_args();
    // parser is first - remove it
    array_shift($argv);
    
    // pass the arguments to the output factory that decides how to get the data, 
    // in what format and how to display it
    $output = SparqlOutputFactory::getOutputForFormat($argv);
    
    if ($output) {
        // if array - output it in the "parser" format (ie with flags)
        // if not an array output it w/o processing - raw (used in drawing charts)
        if (is_array($output)) {
            return $output;
        } else {
            return $parser->insertStripItem( $output, $parser->mStripState );
        }
    } else {
        return wfMsg('no_data');
    }
}

/*
 * 1) script needed for converting the &amp; ->  left over from parser output 
 * 2) google visualization api
 */
function addUtilityScripts() {
    global $wgOut;
    $wgOut->addScript("<script type=\"text/javascript\">/*<![CDATA[*/function fixUrlForSparqlExtension(url) { return url.replace(/\&amp;/g,'&'); } /*]]>*/</script>\n");
    $wgOut->addScript("<script type=\"text/javascript\" src=\"http://www.google.com/jsapi\"></script>\n");
}

