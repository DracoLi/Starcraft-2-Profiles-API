<?php

/**
 * Retrieves a rankings page on ranks and caches it. 
 * For GM rankings, we use crontab to auto cache. For SC2Ranks, users initiate caching.
 * Since we are caching, we can visit ranks directly.
 */
require_once('../classes/SC2Rankings.php');

// Default Params, this is used when cettain options not specified
$defaultParams = array('region' => 'global',
					   'league' => 'grandmaster',
					   'bracket' => '1',
					   'race' => 'all',
					   'offset' => 0,
					   'update' => 'false',
                       'game' => 'wol',
					   'type' => 'json',
					   'amount' => 20);

// Get basic parameters
$options = array();
$options['region'] = $_GET['region'];	// global, na, sea, eu, krtw, cn
$options['league'] = $_GET['league'];	// bronze, silver, gold, platinum, diamond, master, grandmaster
$options['bracket'] = $_GET['bracket'];	// 1, 2t, 2r, 3t, 3r, 4t, 4r
$options['race'] = $_GET['race'];		// all, zerg, protess, terran, random
$options['game'] = $_GET['game'];       // hots, wol
$options['update'] = $_GET['update'];
$options['offset'] = $_GET['offset'];
$options['amount'] = $_GET['amount'];
$options['type'] = $_GET['type'];

// Merge user param with default
$options = GeneralUtils::getDefaults($defaultParams, $options);

$rankingsObject = new SC2Rankings($options);

if ( $options['type'] == 'html' ) {
	$rankingsObject->displayArray();
}else if ( $options['type'] == 'json' ) {
	RestUtils::sendResponse(200, $rankingsObject->getJsonData(), '', 'application/json');
}

?>