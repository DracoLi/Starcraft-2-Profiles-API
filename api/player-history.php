<?

/**
 * Take a player's profile url and get its history.
 * Works for both ranks and bnet data. This function will derive the matches page from the base url
 * Returns a list of histories. Each have name, type, outcome, points, date
 *
 * Store match history to database?
 */

require_once('../classes/SC2History.php');
require_once('../helpers/helper-fns.php');

// Get basic parameters
$options = array();
$options['url'] = $_REQUEST['url'];			// player profile url
$options['content'] = $_REQUEST['content'];	// Content of the page
$options['type'] = $_REQUEST['type'];

$defaultParams = array('url' => 'http://us.battle.net/sc2/en/profile/1655210/1/ArTiFaKs/',
                       'type' => 'json',
                       'content' => '');

// Merge user param with default
$options = GeneralUtils::getDefaults($defaultParams, $options);

// If development enviroment and no content is provided, we fetch it instead
if ( !isset($options['content']) || $options['content'] == '' ) {

	// If user pass bnet url, we send the correct matches url if in production.
	if ( SC2Utils::isbnetURL($options['url']) ) {
		$options['url'] = SC2History::getBNETHistoryURL($options['url']);

		// Since we are in development, we get right url and grab the content
		if ( ENVIROMENT == 'DEVELOPMENT' ) {
			$urlconnect = new URLConnect($options['url'], 100, FALSE);
			if ( $urlconnect->getHTTPCode() != 200 ) {
				RestUtils::sendResponse($urlconnect->getHTTPCode());
				exit;
			}
			$options['content'] = $urlconnect->getContent();
		}else {
			RestUtils::sendResponse(200, $options['url'], '', 'text-plain');
			exit;
		}
	}else {
		if ( ENVIROMENT == 'DEVELOPMENT' ) {
			$urlconnect = new URLConnect($options['url'], 100, FALSE);
			if ( $urlconnect->getHTTPCode() != 200 ) {
				RestUtils::sendResponse($urlconnect->getHTTPCode());
				exit;
			}
			$options['content'] = $urlconnect->getContent();
		}else {
			// We do not accept only a ranks profile url in production
			RestUtils::sendResponse(406);
			exit;
		}
	}
}

$sc2history = new SC2History($options['content'], $options['url']);
if ( $options['type'] == 'html' ) {
 $sc2history->displayArray();
}else if ( $options['type'] == 'json' ){
 GeneralUtils::printJSON($sc2history->getJsonData());
}

?>
