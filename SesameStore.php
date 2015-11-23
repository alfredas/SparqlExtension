<?php

if( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not a valid entry point.' );
}

global $IP;
require_once("$IP/extensions/SemanticMediaWiki/includes/storage/SMW_SQLStore2.php");

/**
 * SesameStore extends SMWSQLStore2 and forwards all update/delete to a
 * Sesame server. Communication is done using the HTTP REST communication
 * protocol for Sesame 2 (a superset of SPARQL 1.0 protocol), see:
 *  http://www.openrdf.org/doc/sesame2/system/ch08.html
 *
 * @author jeen.broekstra@wur.nl, A.Chmieliauskas@tudeflt.nl , C.B.Davis@tudelft.nl
 * @package SparqlExtension
 */
class SesameStore extends SMWSQLStore2 {

	public function SesameStore() {
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
	protected function removeDataForURI($uri) {

		$stat = array('s' => $uri);

		$operation = array('remove' => array($stat));

		$transaction = $this->createTransaction($operation);

		$response = $this->sendTransaction($transaction);

		return $response;
	}

	/**
	 * Does update. First deletes, then inserts.
	 * @param $data
	 */
	function updateData(SMWSemanticData $data) {
		$export = SMWExporter::makeExportData($data);

		// let other extensions add additional RDF data for this page (i.e. Semantic Internal Objects)
		//this code is based on the modifications made on SemanticMediaWiki/includes/export/SMW_OWLExport.php
		$additionalDataArray = array();
		$fullexport = true;
		$backlinks = false;

		wfRunHooks( 'smwAddToRDFExport', array( $data->getSubject()->getTitle(), &$additionalDataArray, $fullexport, $backlinks ) );

		$addStatements = array();

		foreach ( $additionalDataArray as $additionalData ) {
			//add new data associated with internal objects
			$addStatements = $this->createStatements($additionalData->getTripleList());
		}


		$subject_uri = SMWExporter::expandURI($export->getSubject()->getName());
		$this->removeDataForURI($subject_uri);

		$triple_list = $export->getTripleList();
		$addStatements = array_merge($addStatements, $this->createStatements($export->getTripleList()));

		
		// create a transaction to execute the updates
		$statements = array('add' => $addStatements );

		$transaction = $this->createTransaction($statements);
		$response = $this->sendTransaction($transaction);
			
		//can the delete and insert statements be combined, or will this lead to concurrency issues?
		return parent::updateData($data);
	}

	private function createStatements($tripleList) {

		$statements = array();

		foreach ($tripleList as $triple) {
			$subject = $triple[0];
			$predicate = $triple[1];
			$object = $triple[2];
				
			$statement = array();
				
			if ($object instanceof SMWExpLiteral) {
				$statement['o'] = $object->getName();
				$dt = $object->getDatatype();
				if (isset($dt) && $dt != "") {
					$statement['datatype_arg'] = "datatype=\"".$dt."\"";
				}
				$statement['is_literal'] = true;
			} else if ($object instanceof SMWExpResource) {
				$statement['o'] = SMWExporter::expandURI($object->getName());
			}
				
			$statement['p'] = SMWExporter::expandURI($predicate->getName());
				
			if ($subject instanceof SMWExpResource) {
				$statement['s'] = SMWExporter::expandURI($subject->getName());
			}

			if (isset($statement['s']) && isset($statement['p']) && isset($statement['o'])) {
				// only add the statement if it is fully defined.
				$statements[] = $statement;
			}
		}
		
		return $statements;
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
	 * build a Sesame transaction XML document given an array with statements to be removed and 
	 * statements to be added. 
	 * 
	 * $statements['remove'], $statements['add']
	 * */
	protected function createTransaction($statements){
		$xml = '<?xml version="1.0"?>'."\n";
		$xml .= '<transaction>'."\n";

		if (!empty($statements['remove']) && is_array($statements['remove'])){
			$clearStats = $statements['remove'];
				
			foreach($clearStats as $statement){
				$xml .= '<remove>'."\n";
				$xml .= $this->createTransactionStatement($statement);
				$xml .= '</remove>'."\n";
			}
		}

		if (!empty($statements['add']) && is_array($statements['add'])){
			foreach($statements['add'] as $statement){
				$xml .= '<add>'."\n";
				$xml .= $this->createTransactionStatement($statement);
				$xml .= '</add>'."\n";
			}
		}

		$xml .= '</transaction>'."\n";
		return $xml;
	}

	private function createTransactionStatement($statement){
		$xml = '';

		//subject
		if (isset($statement['bnode']) && $statement['bnode'] && isset($statement['s'])){
			$xml .= '<bnode>'.$statement['s'].'</bnode> '."\n";
		} else if (!isset($statement['s'])){
			$xml .= '<null /> <!-- subject -->'."\n";
		} else {
			$xml .= '<uri>'.$statement['s'].'</uri> '."\n";
		}
			
		//predicate
		if (!isset($statement['p'])){
			$xml .= '<null /> '."\n";
		} else {
			$xml .= '<uri>'.$statement['p'].'</uri> '."\n";
		}
			
		//object
		if (isset($statement['is_literal']) && $statement['is_literal'] && isset($statement['o'])){
			$extra = (isset($statement['datatype_arg'])) ? $statement['datatype_arg']:'xml:lang="en"';
			$xml .= '<literal '.$extra.' >'.$statement['o'].'</literal> '."\n";
		} else if(!isset($statement['o'])){
			$xml .= '<null />  '."\n";
		} else {
			$xml .= '<uri>'.$statement['o'].'</uri> '."\n";
		}

		return $xml;
	}

	protected function sendTransaction($xml){

		global $sparqlEndpointConfiguration;

		$opts = array('http' =>
		array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-rdftransaction',
                'content' => $xml
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

