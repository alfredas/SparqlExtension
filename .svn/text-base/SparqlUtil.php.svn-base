<?php
if( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not a valid entry point.' );
}
require_once("SparqlLinker.php");

/**
 * Utility methods shared among the SparqlExtension classes
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlUtil {

	public static function parse_csv($data_string, $options = null) {
		$delimiter = empty($options['delimiter']) ? "," : $options['delimiter'];
		$to_object = empty($options['to_object']) ? false : true;
		$expr="/$delimiter(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/";
		$str = $data_string;
		$lines = explode("\n", $str);
		$field_names = explode($delimiter, trim(array_shift($lines)));
		foreach ($lines as $line) {
			// Skip the empty line
			if (empty($line)) continue;
			$fields = preg_split($expr,trim($line)); // added
			$fields = preg_replace("/^\"(.*)\"$/s","$1",$fields); //added
			//$fields = explode($delimiter, $line);
			$_res = $to_object ? new stdClass : array();
			foreach ($field_names as $key => $f) {
				if ($to_object) {
					$_res->{$f} = $fields[$key];
				} else {
					$_res[$f] = $fields[$key];
				}
			}
			$res[] = $_res;
		}
		return $res;
	}

	public static function parse_json($data_string) {
		$json = json_decode($data_string, true);
		$fields = $json["head"]["vars"];
		$results = $json["results"]["bindings"];
		$res = array();
		foreach ($results as $result) {
			$row = array();
			foreach ($fields as $field) {
				$row[$field] = $result[$field]["value"];
			}
			$res[] = $row;
		}
		return $res;
	}


	public static function build_restful_url($url, $params){
		$url .="?";
		foreach ($params as $key=>$value){
			$url .=  "$key=".SparqlUtil::encode($value)."&";
		}
		return $url;
	}

	public static function encode($url){
		$url = urlencode($url);
		return $url;
	}

	/*
	 * create prefixes for querying local
	 */
	public static function createPrefixes() {
		global $smwgNamespace;
		$pref  = "BASE <".$smwgNamespace.">\n";
		$pref .= "PREFIX article: <".$smwgNamespace.">\n";
		$pref .= "PREFIX a: <".$smwgNamespace.">\n";
		$pref .= "PREFIX property: <".$smwgNamespace."Property:>\n";
		$pref .= "PREFIX prop: <".$smwgNamespace."Property:>\n";
		$pref .= "PREFIX category: <".$smwgNamespace."Category:>\n";
		$pref .= "PREFIX cat: <".$smwgNamespace."Category:>\n";
		$pref .= "PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>\n";
		$pref .= "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>\n";
		$pref .= "PREFIX fn: <http://www.w3.org/2005/xpath-functions#>\n";
		$pref .= "PREFIX afn: <http://jena.hpl.hp.com/ARQ/function#>\n";
		return $pref;
	}

	/*
	 * reads a file (using a proxy if specified)
	 */
	public static function readQueryOutput($url, $proxy_ip, $proxy_port, $type) {
		if (isset($proxy_ip)) {
			$fp = fsockopen($proxy_ip, $proxy_port, &$errno, &$errstr);
			$data = "";
			if ($fp) {
				$out = "GET $url HTTP/1.1\r\n";
				if ($type == "json") {
					$out .= "Accept: application/sparql-results+json \r\n\r\n";
				}
				else {
					$out .= "\r\n";
				}
					
				fputs($fp, $out);
				$output = "";
				$reading_headers = true;
				while (!feof ($fp)) {
					$curline = fgets($fp, 4096);
					if ($curline=="\r\n") {
						$reading_headers = false;
					}
					if (!$reading_headers) {
						$output .= $curline;
					}
				}
				fclose($fp);
				return $output;
			}
		} else {
				
			$header = 'Accept: */*';
			if ($type == "json") {
				$header = 'Accept: application/sparql-results+json';
			}
			$opts = array('http' =>
			array(
                					'method'  => 'GET',
                					'header'  => $header,
			)
			);

			$context = stream_context_create($opts);
			$output = file_get_contents($url, false, $context);

			return $output;

			}

			return false;
		}

	}



