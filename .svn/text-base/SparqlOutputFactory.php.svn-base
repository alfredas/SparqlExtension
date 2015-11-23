<?php
if( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not a valid entry point.' );
}

require_once("SparqlOutputFormat.php");

/**
 * Output factory chooses the output format implementation by the "format" parameter supplied to the extension
 * @author A.Chmieliauskas@tudeflt.nl
 * @package SparqlExtension
 */
class SparqlOutputFactory {

    public static function getOutputForFormat($args) {
        $output = new SparqlOutputFormat($args);
        $format = $output->getFormat();
        
        switch ($format) {
            case "template":
                $output = new SparqlTemplate($args);
                break;
            case "maps":
                $output = new SparqlMaps($args);
                break;
            case "graph":
                $output = new SparqlGraph($args);
                break;
            case "piechart":
                $output = new SparqlPieChart($args);
                break;
            case "barchart":
                $output = new SparqlBarChart($args);
                break;
            case "linechart":
                $output = new SparqlLineChart($args);
                break;
            case "columnchart":
                $output = new SparqlColumnChart($args);
                break;
            case "areachart":
                $output = new SparqlAreaChart($args);
                break;
            case "orgchart":
                $output = new SparqlOrgChart($args);
                break;
            case "scatterchart":
                $output = new SparqlScatterChart($args);
                break;
            case "treemap":
                $output = new SparqlTreeMap($args);
                break;
            case "geomap":
                $output = new SparqlGeoMap($args);
                break;
            case "inline":
                $output = new SparqlInline($args);
                break;
            default:
                $output = new SparqlTable($args);
        }
        return $output->getOutput();
    }
}

