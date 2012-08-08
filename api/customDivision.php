<?php

require_once('../classes/SC2CustomDivision.php');
require_once('../helpers/RestUtils.php');

// Default Params, this is used when cettain options not specified
$defaultParams = array('url' => 'http://www.sc2ranks.com/c/2490/all-stars-division/',
					             'offset' => 0,
					             'amount' => 10,
					             'update' => 'true',
					             'type' => 'json');

// Get basic parameters
$options = array();
$options['url'] = $_GET['url'];
$options['update'] = $_GET['update'];
$options['offset'] = $_GET['offset'];
$options['amount'] = $_GET['amount'];
$options['type'] = $_GET['type'];

// Merge user param with default
$options = GeneralUtils::getDefaults($defaultParams, $options);

$sc2division = new SC2CustomDivision($options);
$divisionData = $sc2division->getDivisionData();

if ( $options['type'] == 'html' ) {
  GeneralUtils::printObject( $divisionData );
}else if ( $options['type'] == 'json' ) {
  GeneralUtils::printJSON( json_encode($divisionData) );
}

?>
