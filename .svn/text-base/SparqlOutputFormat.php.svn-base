<?php
if( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not a valid entry point.' );
}

require_once("SparqlLinker.php");
require_once("SparqlUtil.php");
require_once("SparqlExtension_body.php");

/**
 * Abstract class SparqlOutputFormat provides the base for the implementations of different output formats that
 * do the job of creating wiki text or html from the sparql query results.
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlOutputFormat {
	private $parameters = null;
	private $format = null;
	public static $DEFAULT_FORMAT = "table"; // default format
	public static $DEFAULT_WIDTH = 400; // default width for visualizations
	public static $DEFAULT_HEIGHT = 400; // default height for visualizations

	function __construct($args) {
		global $sparqlEndpointConfiguration;
		//query string is first argument
		$query_string = array_shift($args);

		// prepend prefixes to query
		$query_string = SparqlUtil::createPrefixes() . $query_string;

		// get the rest of the function arguments
		$tmp = array();
		foreach ($args as $arg) {
			if (!is_object($arg)) {
				preg_match('/^(\\w+)\\s*=\\s*(.+)$/is', $arg, $match) ? $tmp[$match[1]] = $match[2] : $args[] = $arg;
			}
		}
		// add query string to the parameters
		$tmp[$sparqlEndpointConfiguration["query_parameter"]] = $query_string;
		if (isset($tmp["format"])) {
			$this->format = $tmp["format"];
		} else {
			$this->format = SparqlOutputFormat::$DEFAULT_FORMAT;
		}
		$this->parameters = $tmp;

	}

	public function getParameters() {
		return $this->parameters;
	}

	public function getParameter($param) {
		return $this->parameters[$param];
	}

	public function getFormat() {
		return $this->format;
	}


	public function getDefaultEndpointRequestUrl() {
		global $sparqlEndpointConfiguration;
		return $this->getEndpointRequestUrlForType($sparqlEndpointConfiguration["default_type"]);
	}

	public function getEndpointRequestUrlForType($type) {
		global $sparqlEndpointConfiguration, $smwgDefaultStore;

		// store endpoint parameters
		$endpoint_parameters = array();
		$endpoint_parameters[$sparqlEndpointConfiguration["query_parameter"]] = $this->parameters[$sparqlEndpointConfiguration["query_parameter"]];
		$endpoint_parameters[$sparqlEndpointConfiguration["output_type_parameter"]] = $type;
		// build endpoint request url
		if ($smwgDefaultStore == "SesameStore") {
			// FIXME: for Sesame the use of a proxy does not seem to work, using
			// direct endpoint for now instead (Jeen).
			$srvc_url = $sparqlEndpointConfiguration["service_url"];
		}
		else {
			// pipe everything thorugh the proxy
			// TODO: performance test - maybe better to go for the original endpoint instead of proxy,
			// but also possible to do some caching via proxy and query rewriting, user control etc...much better
			$srvc_url = SpecialPage::getTitleFor("SparqlExtension")->getFullURL();
		}
		$endpoint_request_url = SparqlUtil::build_restful_url($srvc_url, $endpoint_parameters);
		return $endpoint_request_url;
	}

	/*
	 * get data into an array from csv or json endpoint response
	 */
	public function getData() {
		global $sparqlProxyIP, $sparqlProxyPort, $sparqlEndpointConfiguration;
		$data = false;
		$type = false;

		if (isset($this->parameters[$sparqlEndpointConfiguration["output_type_parameter"]])) {
			$type = $this->parameters[$sparqlEndpointConfiguration["output_type_parameter"]];
		} else {
			$type = $sparqlEndpointConfiguration["default_type"];
		}
		$url = $this->getEndpointRequestUrlForType($type);
		$query_output = SparqlUtil::readQueryOutput($url, $sparqlProxyIP, $sparqlProxyPort, $type);

		switch ($type) {
			case "json":
				$data = SparqlUtil::parse_json($query_output);
				break;
			default:
				$data = SparqlUtil::parse_csv($query_output);
		}
		return $data;
	}

	/*
	 * create an json of parameters to be passed to google visualization
	 */
	public function createJSONFromParameters() {
		global $sparqlEndpointConfiguration;

		// add width and height as default parameters
		if (!in_array("width", array_keys($this->parameters))) {
			$this->parameters["width"] = SparqlOutputFormat::$DEFAULT_WIDTH;
		}
		if (!in_array("height", array_keys($this->parameters))) {
			$this->parameters["height"] = SparqlOutputFormat::$DEFAULT_HEIGHT;
		}

		$json = "{";

		foreach ($this->parameters as $param => $value) {
			// exclude endpoint configuration and query
			if (in_array($param, array_values($sparqlEndpointConfiguration)) || $param == "format") continue;
			$json .= $param . ": ";
			if (is_numeric($value)) {
				$json .= $value;
			} elseif ( ($this->startsWith($value, "[") && $this->endsWith($value, "]")) || ($this->startsWith($value, "{") && $this->endsWith($value, "}")))  {
				$json .= htmlspecialchars($value);
			} else {
				$json .= "'".htmlspecialchars($value)."'";
			}
			$json .= ",";
		}
		//remove last comma since this causes Internet Explorer to break with an "Expected identifier, string or number" error
		$json = substr($json,0,-1);
		$json .= "}";

		return $json;
	}

	function startsWith($Haystack, $Needle){
		return strpos($Haystack, $Needle) === 0;
	}

	function endsWith($Haystack, $Needle) {
		return strrpos($Haystack, $Needle) === strlen($Haystack)-strlen($Needle);
	}

	function encode($url){
		$url = urlencode($url);
		$pattern = array("%7B","%7D","%5B","%5D","%27");
		$value = array("{","}","[","]","'");
		$url = str_replace($pattern, $value, $url);
		return $url;
	}


}

/**
 * output format to be used with template
 * | format=template
 * | template=myTemplate
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlTemplate extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		$args = $this->getParameters();
		$data = $this->getData();

		$link = (isset($args["link"]) && $args["link"] == "none") ? false : true;
		if (isset($args["template"])) {
			$template_name = $args["template"];
			$output = "";
			foreach($data as $row) {
				$template = "";
				foreach(array_keys($row) as $key) {
					$value = SparqlLinker::createLink($link, $row[$key]);
					$template .= "|".$key."=".$value;
				}
				$template = "{{".$template_name.$template."}}";
				$output .= $template;
			}
			return array($output, 'noparse' => false, 'isHtml' => false);
		}
	}
}

/**
 * default output format - a wiki table with a number of formating options
 * | format=template
 * |  tablestyle=border-width:1px; border-spacing:0px; border-style:outset; border-color:black; border-collapse:collapse;
 * | rowstyle=padding:2px;
 * | oddrowstyle=background-color:Lavender
 * | evenrowstyle=background-color:white
 * | headerstyle=background-color:CornflowerBlue; color: white
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlTable extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		$args = $this->getParameters();
		$data = $this->getData();

		// get settings
		$link = (isset($args["link"]) && $args["link"] == "none") ? false : true;
		$tablestyle = isset($args["tablestyle"]) ? "style=\"".$args["tablestyle"]."\"" : "border=\"1\"";
		$rowstyle = isset($args["rowstyle"]) ? $args["rowstyle"] : "";
		$oddrowstyle = isset($args["oddrowstyle"]) ? $args["oddrowstyle"] : "";
		$evenrowstyle = isset($args["evenrowstyle"]) ? $args["evenrowstyle"] : "";
		$headerstyle = isset($args["headerstyle"]) ? $args["headerstyle"] : "";
		$replaceWhat = isset($args["replacewhat"]) ? $args["replacewhat"] : "";
		$replaceWith = isset($args["replacewith"]) ? $args["replacewith"] : "";
		$decimals = isset($args["decimals"]) ? $args["decimals"] : 2;
		$header = "{| class=\"wikitable sortable\" ".$tablestyle;
		$new_line = "\n|-";
		$headerstyle = ($headerstyle == "") ? "" : "style=\"".$headerstyle."\"";
		$header .= $new_line." ".$headerstyle." \n!";

		if (is_array($data[0])) {
			foreach(array_keys($data[0]) as $key) {
				$header .= $key . "!!";
			}
		}
		$header = trim($header, "!");
		$is_odd = true;
		foreach($data as $row) {
			$style = ($rowstyle == "" || $this->endsWith(trim($rowstyle), ";")) ? $rowstyle : $rowstyle.";";
			$style .= ($is_odd) ? $oddrowstyle : $evenrowstyle;
			$style = ($style == "") ? "" : "style=\"".$style."\"";
			$header .= $new_line." ".$style." \n";
			$header .= "| ";
			foreach($row as $cell) {
				if ($replaceWhat != "") {
					$cell = str_replace($replaceWhat, $replaceWith, $cell);
				}
				$value = SparqlLinker::createLink($link, $cell);
				if (is_numeric($value)) {
					$value = number_format($value, $decimals, ".", ",");
				}
				$header .= " " . $value . " ||";
			}
			$header = trim($header, "|");
			$is_odd = !$is_odd;
		}
		$header .= "\n|}";
		return array($header, 'noparse' => false, 'isHtml' => false);;
	}

	function endsWith( $str, $sub ) {
		return ( substr( $str, strlen( $str ) - strlen( $sub ) ) === $sub );
	}
}

/**
 * returns result in the same line, if an array - returns comma separated
 * | format=inline
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlInline extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		$args = $this->getParameters();
		$data = $this->getData();

		$link = (isset($args["link"]) && $args["link"] == "none") ? false : true;
		$decimals = isset($args["decimals"]) ? $args["decimals"] : 2;

		$output = "";
		$first = true;
		foreach($data as $row) {
			foreach($row as $cell) {
				$value = SparqlLinker::createLink($link, $cell);
				if (is_numeric($value)) {
					$value = number_format($value, $decimals, ".", ",");
				}
				if (!$first) {
					$output .= ", " . $value;
				} else {
					$output = $value;
					$first = false;
				}
			}
		}
		return array($output, 'noparse' => false, 'isHtml' => false);
	}
}


/**
 * Graph result format. Wraps the query output (csv) into format suitable for the graphviz plugin
 * | format=graph
 * | from=
 * | to=
 * | edge=
 * | fromlabel=
 * | tolabel=
 * | edgelabel=
 * | size=width,height (in pixels)
 * @author C.B.Davis@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlGraph extends SparqlOutputFormat {
	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {

		$args = $this->getParameters();
		$data = $this->getData();

		//get parameters
		$link = (isset($args["link"]) && $args["link"] == "none") ? false : true ;
		$from = isset($args["from"]) ? $args["from"] : "";
		$to = isset($args["to"]) ? $args["to"] : "";
		$edge = isset($args["edge"]) ? $args["edge"] : "";
		$size = isset($args["size"]) ? $args["size"] : "";

		//allow the user to specify labels for the nodes that are different from the pagename
		$fromlabel = isset($args["fromlabel"]) ? $args["fromlabel"] : "";
		$tolabel = isset($args["tolabel"]) ? $args["tolabel"] : "";
		$edgelabel = isset($args["edgelabel"]) ? $args["edgelabel"] : "";

		$header = "<graphviz caption=''>\n";
		//$header .= "digraph G {\nrankdir=LR\nsize=\"2,2\";\nratio=\"compress\";\nwidth=2;\nheight=2;\n";
		//this loop creates labels for each of the nodes and creates connections
		$arr = array();

		$header = "<graphviz renderer='neato' caption='Hello Neato'>\n";
		$header .= "digraph G {\nrankdir=LR\n";
		if (isset($args["size"])){
			$dimensions = explode(",", $size);
			//assume 96 dpi for web browser resolution
			$width=$dimensions[0]/96;
			$height=$dimensions[1]/96;
			$header .= "size=\"".$width.",".$height."\"\n";
		}
		//this loop creates labels for each of the nodes and creates connections
		foreach($data as $row) {
			//create label, url for "from" node
			if (isset($args["fromlabel"])){
				//fromlabel is specified, make it distinct from the url
				$header .= "\"".trim($row[$from])."\"[label=\"".trim($row[$fromlabel])."\" URL=\"".$row[$from]."\"];\n";
			} else {
				$header .= "\"".trim($row[$from])."\"[label=\"".trim($row[$from])."\" URL=\"".$row[$from]."\"];\n";
			}

			//create label, url for "to" node
			if (isset($args["tolabel"])){
				//tolabel is specified, make it distinct from the url
				$header .= "\"".trim($row[$to])."\"[label=\"".trim($row[$tolabel])."\" URL=\"".$row[$to]."\"];\n";
			} else {
				$header .= "\"".trim($row[$to])."\"[label=\"".trim($row[$to])."\" URL=\"".$row[$to]."\"];\n";
			}

			//create label, url for edge
			if (isset($args["edgelabel"])){
				//edgelabel is specified, make it distinct from the url
				$header .= "\"".trim($row[$from])."\"->\"".trim($row[$to])."\"[label=\"".trim($row[$edgelabel])."\" URL=\"".$row[$edge]."\"];\n";
			} else {
				$header .= "\"".trim($row[$from])."\"->\"".trim($row[$to])."\"[label=\"".trim($row[$edge])."\" URL=\"".$row[$edge]."\"];\n";
			}
		}
		$header .= "}\n";
		$header .= "</graphviz>\n";
		return array($header, 'noparse' => false, 'isHtml' => false);
	}

}

/**
 * Maps result format. Uses the Semantic maps extension
 * accepts lat log combination or point. title column is used for markers
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlMaps extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		$args = $this->getParameters();
		$data = $this->getData();

		$width = isset($args["width"]) ? $args["width"] : SparqlOutputFormat::$DEFAULT_WIDTH;
		$height = isset($args["height"]) ? $args["height"] : SparqlOutputFormat::$DEFAULT_HEIGHT;
		$output = "{{#display_points:\n";
		foreach($data as $row) {
			if (isset($row["point"])) {
				$point = $row["point"];
				$arr = explode('"', $point);
				if (count($arr) > 1) {
					$point = $arr[1];
				}
			} else if (isset($row["lat"]) && isset($row["lon"])) {
				$lat = $row["lat"];
				$lon = $row["lon"];

				$arr = explode('"', $lat);
				if (count($arr) > 1) {
					$lat = $arr[1];
				}
				$arr = explode('"', $lon);
				if (count($arr) > 1) {
					$lon = $arr[1];
				}
				$point = $lat . ", " . $lon;
			}
			// fix .0 coords
			$fix = explode(' ', $point);
			$pp = "";
			foreach($fix as $coord) {
				if ($this->startsWith($coord, '.')) {
					$coord = "0".$coord;
				}
				if ($this->startsWith($coord, '-.')) {
					$coord = "-0.".substr($coord, 2);
				}
				$pp .= " ".$coord;
			}
			$point = trim($pp);

			//if (trim($point) != "0 0") {
			//now with more support for 0 0!
			if ((trim($point) != "0 0") && (trim($point) != "0, 0") && (trim($point) != "0 N, 0 E")) {
				/*Changed by Chris to support Semantic Maps format
				 Originally we used coordinates that were strings,
				 now we use properties that are types of Geographic Coordinates
				 at least within SMW.  In the triplestore they still show up as strings.
				 */
				//$coord = str_replace(" ", ",", $point)."~".$row["title"].";";
				$coord = $point."~".$row["title"].";";
				$output .= $coord;
			}
		}
		//get rid of the last semicolon on the list of points
		//The maps extension doesn't like any stray semicolons hanging around
		$output = substr($output,0,-1);
		$output .= "\n| width=".$width."\n| height=".$height."\n}}";

		wfDebugLog('SPARQL_LOG', "#===MAPS===\n".$output);

		return array($output, 'noparse' => false, 'isHtml' => false);
	}

	function startsWith($haystack, $needle){
		return strpos($haystack, $needle) === 0;
	}
}


/**
 * Google Visualisation Format
 * produces http://code.google.com/apis/visualization/documentation/gallery/geomap.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlGeoMap extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("map");
		// add scripts
		$output = "<script type=\"text/javascript\">/*<![CDATA[*/ google.load('visualization', '1', {packages: ['geomap']});function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable();" .
        	"v = new google.visualization.GeoMap(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}

/**
 * Google Visualisation Format
 * produces http://code.google.com/apis/visualization/documentation/gallery/piechart.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlPieChart extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("pie");
		$output = "<script type=\"text/javascript\">/*<![CDATA[*/ google.load('visualization', '1', {packages: ['corechart']});function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable(); " .
        	"v = new google.visualization.PieChart(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}

/**
 * Google Visualisation Format
 * produces http://code.google.com/apis/visualization/documentation/gallery/scatterchart.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlScatterChart extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("scatter");
		// add scripts
		$output = "<script type=\"text/javascript\">/*<![CDATA[*/ google.load('visualization', '1', {packages: ['corechart']});function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable();" .
        	"v = new google.visualization.ScatterChart(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}

/**
 * Google Visualisation Format
 * produces http://code.google.com/apis/visualization/documentation/gallery/orgchart.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlOrgChart extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("org");
		// add scripts
		$output = "<script type=\"text/javascript\">/*<![CDATA[*/ google.load('visualization', '1', {packages: ['orgchart']});function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable();" .
        	"v = new google.visualization.OrgChart(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}


/**
 * Google Visualisation Format
 * produces http://code.google.com/apis/visualization/documentation/gallery/areachart.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlAreaChart extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("area");
		// add scripts
		$output = "<script type=\"text/javascript\">/*<![CDATA[*/ google.load('visualization', '1', {packages: ['corechart']});function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable();" .
        	"v = new google.visualization.AreaChart(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}


/**
 * Google Visualisation Format
 * produces http://code.google.com/apis/visualization/documentation/gallery/barchart.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlBarChart extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("bar");
		// add scripts
		$output = "<script type=\"text/javascript\">/*<![CDATA[*/ google.load('visualization', '1', {packages: ['corechart']});function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable();" .
        	"v = new google.visualization.BarChart(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}

/**
 * Google Visualisation Format
 * produces http://code.google.com/apis/visualization/documentation/gallery/columnchart.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlColumnChart extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("column");
		// add scripts
		$output = "<script type=\"text/javascript\">/*<![CDATA[*/ google.load('visualization', '1', {packages: ['corechart']});function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable();" .
        	"v = new google.visualization.ColumnChart(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}


/**
 * Google Visualisation Format
 * produces http://code.google.com/apis/visualization/documentation/gallery/linechart.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlLineChart extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("line");
		// add scripts
		$output = "<script type=\"text/javascript\">/*<![CDATA[*/ google.load('visualization', '1', {packages: ['corechart']});function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable();" .
        	"v = new google.visualization.LineChart(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}

/**
 * Google Visualisation Format
 * produces http://www.drasticdata.nl/DrasticTreemapGApi/index.html
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlTreeMap extends SparqlOutputFormat {

	public function __construct($args) {
		parent::__construct($args);
	}

	public function getOutput() {
		global $wgOut;
		$args = $this->getParameters();
		$gds_url = $this->getEndpointRequestUrlForType(SparqlExtension::$GDS_TYPE);
		$id = uniqid("treemap");
		// add scripts
		$output = "<script type=\"text/javascript\" src=\"http://enipedia.tudelft.nl/scripts/DrasticTreemapGApi/DrasticTreemapGApi.js\"></script>\n";
		$output.= "<script type=\"text/javascript\">/*<![CDATA[*/ google.load(\"visualization\", \"1\");google.load(\"swfobject\", \"2.2\"); function draw_".$id."() {" .
        	"var q = new google.visualization.Query(fixUrlForSparqlExtension('".$gds_url."')); q.send(handle_".$id."); }" .
            "function handle_".$id."(r) { if (r.isError()) return; var d = r.getDataTable();" .
        	"v = new drasticdata.DrasticTreemap(document.getElementById('".$id."')); v.draw(d, ".$this->createJSONFromParameters()."); } google.setOnLoadCallback(draw_".$id.");/*]]>*/ </script>";
		$output.= "<div id=\"".$id."\" style=\"height: ".$this->getParameter("height")."px; width: ".$this->getParameter("width")."px;\"></div>";
		return $output;
	}
}


