<?php

if( !defined( 'MEDIAWIKI' ) ) {
    die( 'Not a valid entry point.' );
}

global $IP;
require_once("$IP/extensions/SemanticMediaWiki/includes/storage/SMW_SQLStore2.php");

/**
 * JosekiStore extends SMWSQLStore2 and forwards all update/delete to sparql endpoint.
 * All updates are done via SPARQL Update syntax (http://www.w3.org/TR/sparql11-update/).
 * The class was loosely based on/insipred by RAPStore.
 * @author A.Chmieliauskas@tudeflt.nl , C.B.Davis@tudelft.nl
 * @package SparqlExtension
 */
class JosekiStore extends SMWSQLStore2 {

    public function JosekiStore() {
    }

    /**
     * wraps removeDataForURI
     * @param $subject
     */
    function deleteSubject(Title $subject) {
        $subject_uri = SMWExporter::expandURI($this->getURI($subject));
        $this->removeDataForURI($subject_uri);
         
        return parent::deleteSubject($subject);
    }

    /**
     * deletes triples that have $uri as subject
     * @param $uri
     */
    function removeDataForURI($uri) {
        $sparqlDeleteText = $this->writeDeleteText($uri);
        wfDebugLog('SPARQL_LOG', "#===DELETE===\n".$sparqlDeleteText);
        $response = $this->do_joseki_post($sparqlDeleteText);
        return $response;
    }

    /**
     * Does update. First deletes, then inserts.
     * @param $data
     */
    function updateData(SMWSemanticData $data) {
        $export = SMWExporter::makeExportData($data);

        $sparqlDeleteText = "";
        $sparqlUpdateText = "INSERT DATA {\n";

        // let other extensions add additional RDF data for this page (i.e. Semantic Internal Objects)
        //this code is based on the modifications made on SemanticMediaWiki/includes/export/SMW_OWLExport.php
        $additionalDataArray = array();
        $fullexport = true;
        $backlinks = false;
        
        wfRunHooks( 'smwAddToRDFExport', array( $data->getSubject()->getTitle(), &$additionalDataArray, $fullexport, $backlinks ) );
        
        // this writes update text for each of the Semantic Internal Objects
        foreach ( $additionalDataArray as $additionalData ) {
            $subject_uri = SMWExporter::expandURI($additionalData->getSubject()->getName());
            // remove subject from triple store
            $sparqlDeleteText .= $this->writeDeleteText($subject_uri);
            //add new data associated with internal objects
            $sparqlUpdateText .= $this->writeUpdateText($additionalData->getTripleList());
        }

        $subject_uri = SMWExporter::expandURI($export->getSubject()->getName());
        
        // remove subject from triple store
        $sparqlDeleteText .= $this->writeDeleteText($subject_uri);
        $triple_list = $export->getTripleList();
        $sparqlUpdateText .= $this->writeUpdateText($triple_list);

        $sparqlUpdateText .= "}";
         
        //delete the old triples
        wfDebugLog('SPARQL_LOG', "#===DELETE===\n".$sparqlDeleteText);
        $response = $this->do_joseki_post($sparqlDeleteText);

        //insert the new triples
        wfDebugLog('SPARQL_LOG', "#===INSERT===\n".$sparqlUpdateText);
        $response = $this->do_joseki_post($sparqlUpdateText);

        //can the delete and insert statements be combined, or will this lead to concurrency issues?
   	    return parent::updateData($data);
    }

    /**
     *write the SPARUL command to delete all triples with the subject specified by the uri
     * @param $uri the subject to be deleted
     */
    function writeDeleteText($uri) {
        $sparqlDeleteText = "DELETE { <".$uri."> ?y ?z } WHERE { <".$uri."> ?y ?z  }\n";
        return $sparqlDeleteText;
    }

    /**
     * write the SPARUL command to create an update for the triples
     * @param $triplelist an array of triples
     */
    function writeUpdateText($triplelist) {
        $updateText = "";
        foreach ($triplelist as $triple) {
            $subject = $triple[0];
            $predicate = $triple[1];
            $object = $triple[2];

            $obj_str = "";
            $sub_str = "";
            $pre_str = "";

            if ($object instanceof SMWExpLiteral) {
                $obj_str = "\"".$object->getName()."\"".(($object->getDatatype() == "") ? "" : "^^<".$object->getDatatype().">");
            } else if ($object instanceof SMWExpResource) {
                $obj_str = "<".SMWExporter::expandURI($object->getName()).">";
            } else {
                $obj_str = "\"\"";
            }
            if ($subject instanceof SMWExpResource) {
                $sub_str = "<".SMWExporter::expandURI($subject->getName()).">";
            }
            if ($predicate instanceof SMWExpResource) {
                $pre_str = "<".SMWExporter::expandURI($predicate->getName()).">";
            }
            $updateText .= $sub_str." ".$pre_str." ".$obj_str." .\n";
        }
        return $updateText;
    }

    /**
     * Insert new pages into endpoint. Used to import data.
     * @param $title
     */
    function insertData(Title $title, $pageid) {
        $newpage = SMWDataValueFactory::newTypeIDValue('_wpg');
        $newpage->setValues($title->getDBkey(), $title->getNamespace(), $pageid);
        $semdata = $this->getSemanticData($newpage);
        $this->updateData($semdata);
    }

    /**
     * Move/rename page
     * @param $oldtitle
     * @param $newtitle
     * @param $pageid
     * @param $redirid
     */
    function changeTitle(Title $oldtitle, Title $newtitle, $pageid, $redirid=0) {

        // Save it in parent store now!
        // We need that so we get all information correctly!
        $result = parent::changeTitle($oldtitle, $newtitle, $pageid, $redirid);

        // delete old stuff
        $old_uri = SMWExporter::expandURI($this->getURI($oldtitle));
        $this->removeDataForURI($old_uri);

        $newpage = SMWDataValueFactory::newTypeIDValue('_wpg');
        $newpage->setValues($newtitle->getDBkey(), $newtitle->getNamespace(), $pageid);
        $semdata = $this->getSemanticData($newpage);
        $this->updateData($semdata);

        $oldpage = SMWDataValueFactory::newTypeIDValue('_wpg');
        $oldpage->setValues($oldtitle->getDBkey(), $oldtitle->getNamespace(), $redirid);
        $semdata = $this->getSemanticData($oldpage);
        $this->updateData($semdata,false);

        return $result;
    }

    /**
     * no setup required
     * @param unknown_type $verbose
     */
    function setup($verbose = true) {
        return parent::setup($verbose);
    }


    function drop($verbose = true) {
        return parent::drop();
    }

    /**
     * Communicates with joseki update service via post
     * @param $requestString
     */
    function do_joseki_post($requestString) {
        global $sparqlEndpointConfiguration;
        $postdata = http_build_query(
            array(
                'request' => $requestString
            )
        );
        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context  = stream_context_create($opts);
        $result = file_get_contents($sparqlEndpointConfiguration["update_url"], false, $context);
        return $result;
    }

    /**
     * Having a title of a page, what is the URI that is described by that page?
     * The result still requires expandURI()
     */
    protected function getURI($title) {
        $uri = "";
        if($title instanceof Title) {
            $dv = SMWDataValueFactory::newTypeIDValue('_wpg');
            $dv->setTitle($title);
            $exp = $dv->getExportData();
            $uri = $exp->getSubject()->getName();
        } else {
            // There could be other types as well that we do NOT handle here
        }

        return $uri; // still requires expandURI()
    }
}

