<?php

/**
 * Returns a player's achievements for a given achivement category (url).
 * No support for linking achivements to awards (portrait, decal) to simplify data.
 */

require_once('../classes/SC2Achievements.php');
require_once('../helpers/RestUtils.php');

// Constants
$defaultParams = array('url' => 'http://us.battle.net/sc2/en/profile/1655210/1/ArTiFaKs/achievements/category/3211278',
                       'game' => 'wol',
                       'type' => 'json');

// Get basic parameters
$options = array();
$options['url'] = $_REQUEST['url'];			// The player's profile URL or an achievement page
$options['content'] = $_REQUEST['content'];	// Content of the achievement page
$options['type'] = $_REQUEST['type'];
$options['game'] = $_REQUEST['game'];

$options = GeneralUtils::getDefaults($defaultParams, $options);

// Figure out if we are providing an achievement url
$isAchievementURL = FALSE;
if ( isset($options['url']) && strpos($options['url'], 'achievements') !== FALSE ) {
  $isAchievementURL = TRUE;
}

// If used by client, we should always have content, if not then its probably development
// And If that's the case we get the content
if ( !isset($options['content']) || strlen($options['content']) == 0 ) {
  $urlconnect = new URLConnect($options['url'], 100, FALSE);
	if ( $urlconnect->getHTTPCode() != 200 ) {
		RestUtils::sendResponse($urlconnect->getHTTPCode());
		exit;
	}
	$options['content'] = $urlconnect->getContent();
}

$sc2Achievements = new SC2Achievements($options['content'], $options['url'], $options['game']);

$resultData = NULL;
if ( $isAchievementURL ) {
  $resultData = $sc2Achievements->getAchievementsData();
}else {
  $resultData = $sc2Achievements->getAllAchievementLinks();
}

if ( $options['type'] == 'html' ) {
  GeneralUtils::printObject( $resultData );
}else if ( $options['type'] == 'json' ) {
  GeneralUtils::printJSON( json_encode($resultData) );
}

?>
