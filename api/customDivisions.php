<?php

/**
 * Gets a list of custom divisions specified by us
 */
require_once('../classes/SC2CustomDivision.php');
require_once('../helpers/RestUtils.php');

// Default Params, this is used when cettain options not specified
$defaultParams = array('region' => 'global',
					             'offset' => 0,
					             'amount' => 10,
					             'update' => 'true',
					             'type' => 'json');

// Get basic parameters
$options = array();
$options['region'] = $_GET['region'];
$options['update'] = $_GET['update'];
$options['offset'] = $_GET['offset'];
$options['amount'] = $_GET['amount'];
$options['type'] = $_GET['type'];

// Merge user param with default
$options = GeneralUtils::getDefaults($defaultParams, $options);

$sc2division = new SC2CustomDivision($options);
$divisionsList = $sc2division->getDivisionsList();

if ( $options['type'] == 'html' ) {
  $fullContent = RestUtils::getHTTPHeader('Testing') . "<pre>" . 
                print_r($divisionsList, TRUE) .  "</pre>" . RestUtils::getHTTPFooter(); 
	RestUtils::sendResponse(200, $fullContent);
}else if ( $options['type'] == 'json' ) {
	RestUtils::sendResponse(200, $divisionsList, '', 'application/json');
}

?>
