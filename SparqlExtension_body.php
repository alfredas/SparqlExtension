<?php
/**
 * Class that controls the special page. Creates a form and pipes/proxies/transforms the results from the endpoint backlto the client.
 * Useful if you want to transform the sparql results to a custom format. In this case specially used to implement the google visualisation
 * datasource format.
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlExtension extends SpecialPage {

	public static $XML_TYPE = "xml";
	public static $JSON_TYPE = "json";
	public static $CSV_TYPE = "csv";
	public static $TSV_TYPE = "tsv";
	public static $TEXT_TYPE = "text";
	public static $GDS_TYPE = "gds";
  public static $SR_TYPE = "semantic-reports";


	public static $GDS_XSL_URL = "http://enipedia.tudelft.nl/scripts/sparqlxml2googlejson.sparqlextension.xsl";

	function __construct() {
		parent::__construct('SparqlExtension');
		wfLoadExtensionMessages('SparqlExtension');
	}

	function execute($par) {
		global $wgRequest, $wgOut, $sparqlEndpointConfiguration;
		$request = $wgRequest->getValues();
		if (is_null($wgRequest->getVal("query"))) {
			// if no query provided - show the form page
			$url = $this->getTitle()->getLinkUrl();
			$formHTML =
		    	"<form action=\"".$url."\" method=\"get\">" . 
                "<textarea name=\"query\" rows=\"40\">".
			SparqlUtil::createPrefixes().
                "select * where {\n".
                "?x ?y ?z .\n".
                "} limit 10".
                "</textarea>".
                "<div><select name=\"".$sparqlEndpointConfiguration["output_type_parameter"]."\">".
                "<option value=\"".SparqlExtension::$XML_TYPE."\">XML</option>".
                "<option value=\"".SparqlExtension::$JSON_TYPE."\">JSON</option>".
                "<option value=\"".SparqlExtension::$TEXT_TYPE."\" selected=\"true\">TEXT</option>".
                "<option value=\"".SparqlExtension::$CSV_TYPE."\">CSV</option>".
                "<option value=\"".SparqlExtension::$TSV_TYPE."\">TSV</option>".
                "<option value=\"".SparqlExtension::$GDS_TYPE."\">GOOGLE-VIS</option>".
                "<option value=\"".SparqlExtension::$SR_TYPE."\">SEMANTIC REPORT</option>".
                "</select><input type=\"submit\" value=\"Get Results\" /></form></div>";
			$this->setHeaders();
			$wgOut->addHTML( $formHTML );
		} else {
			$sparql_url = $sparqlEndpointConfiguration["service_url"];

			$response = "";
			$header = "";

			$output = isset($request["output"]) ? $request["output"] : "xml";

			switch (strtolower($output)){
				case "xml":
					$url = SparqlUtil::build_restful_url($sparql_url, $request);
					$opts = array('http' =>	
								array(
                					'method'  => 'GET',
                					'header'  => 'Accept: application/sparql-results+xml, application/rdf+xml, application/xml',
							)
					);

					$context = stream_context_create($opts);
					$response = file_get_contents($url, false, $context);
					$header = "Content-type: application/xml; charset=utf-8";
					break;
				case "gds":
					$url = SparqlUtil::build_restful_url($sparql_url, $request);
					$inputparams =array();
					if (isset($request["tqx"]))
					$inputparams["tqx"] = $request["tqx"];
					$response = $this->xslt_transform($url, SparqlExtension::$GDS_XSL_URL, $inputparams);
					$header = "Content-type: application/x-javascript; charset=utf-8";
					break;
				case "json":
					$url = SparqlUtil::build_restful_url($sparql_url, $request);
					$opts = array('http' =>	
								array(
                					'method'  => 'GET',
                					'header'  => 'Accept: application/sparql-results+json, application/rdf+json, application/json',
							)
					);

					$context = stream_context_create($opts);
					$response = file_get_contents($url, false, $context);
					$header = "Content-type: application/json; charset=utf-8";
					break;
				case "text":
					$url = SparqlUtil::build_restful_url($sparql_url, $request);
					$opts = array('http' =>	
								array(
                					'method'  => 'GET',
                					'header'  => 'Accept: text/plain, text/*, */*',
							)
					);

					$context = stream_context_create($opts);
					$response = file_get_contents($url, false, $context);
					$header = "Content-type: text/plain; charset=utf-8";
					break;
				case "csv":
					$url = SparqlUtil::build_restful_url($sparql_url, $request);
					$opts = array('http' =>	
								array(
                					'method'  => 'GET',
                					'header'  => 'Accept: text/csv, text/*, */*',
							)
					);

					$context = stream_context_create($opts);
					$response = file_get_contents($url, false, $context);
					$header = "Content-type: text/csv; charset=utf-8";
					break;
				case "tsv":
					$url = SparqlUtil::build_restful_url($sparql_url, $request);
					$opts = array('http' =>	
								array(
                					'method'  => 'GET',
                					'header'  => 'Accept: text/tsv, text/*, */*',
							)
					);

					$context = stream_context_create($opts);
					$response = file_get_contents($url, false, $context);
					$header = "Content-type: text/tsv; charset=utf-8";
					break;
        case "semantic-reports":  
          $request["endpoint"] = $sparql_url;
          $request["view"] = "create";
          $url = SparqlUtil::build_restful_url("http://semanticreports.com/reports/", $request); 
          //$response = file_get_contents($url);
          $header = "Location: " . $url;
          break;
				default:
					$url = SparqlUtil::build_restful_url($sparql_url, $request);
					$opts = array('http' =>	
								array(
                					'method'  => 'GET',
			       					'header'  => 'Accept: application/sparql-results+xml, application/rdf+xml, application/xml',
							)
					);

					$context = stream_context_create($opts);
					$response = file_get_contents($url, false, $context);
					$header = "Content-type: application/xml; charset=utf-8";
					break;
			}
			$wgOut->disable();
			header($header);
			print $response;
		}
	}


	function xslt_transform($url_xml, $url_xsl, $params=false){
		# LOAD XML FILE
		$XML = new DOMDocument();
		$XML->load( $url_xml );

		# LOAD XSL FILE
		$XSL = new DOMDocument();
		$XSL->load( $url_xsl , LIBXML_NOCDATA);

		# START XSLT
		$xslt = new XSLTProcessor();

		#load style sheet
		$xslt->importStylesheet( $XSL );

		#set params
		$xslt->setParameter("",$params);

		#transform
		$data = $xslt->transformToXML( $XML );
		return $data;
	}

}
