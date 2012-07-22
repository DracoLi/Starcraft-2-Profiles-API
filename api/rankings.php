<?php

/**
 * Retrieves a rankings page on ranks and caches it. 
 * For GM rankings, we use crontab to auto cache. For SC2Ranks, users initiate caching.
 * Since we are caching, we can visit ranks directly.
 */
require('../classes/SC2Rankings.php');

// Default Params, this is used when cettain options not specified
$defaultParams = array('region' => 'na',
					   'league' => 'grandmaster',
					   'bracket' => 1,
					   'raceType' => 'random',
					   'race' => 'all',
					   'start' => 0,
					   'amount' => 100,
					   'update' => true,
					   'type' => 'json');

// Get basic parameters
$options = array();
$options['region'] = $_GET['region'];	// global, na, sea, eu, krtw, cn
$options['league'] = $_GET['league'];	// bronze, silver, gold, platinum, master, grandmaster
$options['bracket'] = $_GET['bracket'];	// 1, 2, 3, 4
$options['raceType'] = $_GET['raceType'];		// Applies when bracket > 1. Can be team or random
$options['race'] = $_GET['race'];		// all, zerg, protess, terran, random

$options['start'] = $_GET['start'];		// index to start
$options['amount'] = $_GET['amount'];	// amount of rankings to grab
$options['update'] = $_GET['update'];	// update our cache

$options['type'] = $_GET['type'];	// update our cache

// Merge user param with default
$options = GeneralUtils::getDefaults($defaultParams, $options);

$rankingsObject = new SC2Rankings($options);

if ( $options['type'] == 'html' ) {
	$rankingsObject->displayArray();
}else if ( $options['type'] == 'json' ) {
	RestUtils::sendResponse(200, $rankingsObject->getJsonData(), '', 'application/json');
}


?>