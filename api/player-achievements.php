<?php

/**
 * Returns a player's achievements for a given achivement category (url).
 * No support for linking achivements to awards (portrait, decal) to simplify data. 
 */

require_once('../classes/SC2Achievements.php');
require_once('../helpers/RestUtils.php');

// Constants
$defaultParams = array('url' => 'http://us.battle.net/sc2/en/profile/383803/1/BlackCitadel/achievements/category/4325378');

// Get basic parameters
$options = array();
$options['url'] = $_POST['url'];			// URL of the achievement page
$options['content'] = $_POST['content'];	// Content of the achievement page

// Handle cases when no content is provided - Users should always have the right divisions url (we cannot guess it).
if ( is_null($options['content']) || strlen($options['content']) == 0 ) {
	
	if ( ENVIROMENT == 'PRODUCTION' ) {
		
		if ( !is_null($options['url']) && strlen($options['url']) > 0 ) {
			// The user only suplied an url, we will get our achievement section to the user
			$theResult = SC2Achievements::getAllAchievements($options['url']);
			RestUtils::sendResponse(200, $theResult, '', 'application/json');
		}else {
			RestUtils::sendResponse(400); // Must provide content! Bad request!
		}
		exit;
		
	}else {
		
		// Return achievements for the player if url is a bnet profile url
		if ( !is_null($options['url']) && strlen($options['url']) > 0 && strpos($options['url'], 'achievements') === FALSE ) {
			$theResult = SC2Achievements::getAllAchievements($options['url']);
			$fulltext = RestUtils::getHTTPHeader('') . '<h2><a href="' . $options['url'] . '">' . $options['url'] . '</a></h2><pre>' . print_r(json_decode($theResult), TRUE) . '</pre>' . RestUtils::getHTTPFooter();
			RestUtils::sendResponse(200, $fulltext, '', '');
			exit;
		}
		
		$defaultParams = array('url' => 'http://us.battle.net/sc2/en/profile/383803/1/BlackCitadel/achievements/category/3211279');
		$options['url'] = is_null($options['url']) ? $defaultParams['url'] : $options['url'];
		
		// If in development, we fetch the contenct from the target url instead
		$urlconnect = new URLConnect($options['url'], 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode());
			exit;
		}
		$options['content'] = $urlconnect->getContent();
	}
}

$sc2Achievements = new SC2Achievements($options['content'], $options['url']);

if ( ENVIROMENT == 'DEVELOPMENT' ) {
	$sc2Achievements->displayArray();
}else {
	RestUtils::sendResponse(200, $sc2Achievements->getJsonData(), '' , 'application/json');
}

?>