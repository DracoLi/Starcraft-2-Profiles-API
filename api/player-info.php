<?

/** 
 * This script uses post.
 * Take a player's url and return all the basic info for that user. URL can be bnet or sc2. User must specify whether they want to take bnet data or sc2ranks.
 * Basic sc2ranks data includes:
 * 		* name, region, bnetURL, historyURL(map and normal), achivementPoints, profileImage array with url and dimensions.
 *		* All leagues data available, each league will have name, rank, url, points, wins, losses, league, worldRank, regionRank (both can be null), and bracket (team or random).
 *      * Each league will have players array. Each array have name, race, url (not player's), and bnetURL
 *
 * Bnet data includes:
 *		* leagueWins, customGames, ffa, badgeName, name, achivementPoints, profileImage array with url and dimension, race.
 *		* Also include historyURL(normal), achievementsURL, divisionsURL.
 *		* Each league array will have rank, name, url, wins, losses, (no points), 
 */

require_once('../classes/SC2Player.php');
	
// Get basic parameters
$options = array();
$options['url'] = $_GET['url'];			// player profile url
$options['content'] = $_POST['content'];	// Content of the page

// If development enviroment and no content is provided, we fetch it instead
if ( ENVIROMENT == 'DEVELOPMENT' && (!isset($options['content']) || $options['content'] == '') ) {
	
	$defaultParams = array('url' => 'http://www.sc2ranks.com/us/2955143/GoSuGatored');
	
	// Merge user param with default
	$options = GeneralUtils::getDefaults($defaultParams, $options);
	
	// Get contents for results
	$urlconnect = new URLConnect($options['url'], 100, FALSE);
	if ( $urlconnect->getHTTPCode() != 200 ) {
		RestUtils::sendResponse($urlconnect->getHTTPCode());
		exit;
	}
	$options['content'] = $urlconnect->getContent();
}

$sc2player = new SC2Player($options['content'], $options['url']);
if ( ENVIROMENT == 'DEVELOPMENT' ) {
	$sc2player->displayArray();	
}else {
	RestUtils::sendResponse(200, $sc2player->getJsonData(), '', 'application/json');
}

?>