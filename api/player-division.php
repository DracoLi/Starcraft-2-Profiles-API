<?

/** 
 * Take a division url and returns all info for that division
 * Support only sc2ranks division links because we need those sc2rank profile links! So no reason to also use bnet divisions.
 */

require_once('../classes/SC2Division.php');

// Get basic parameters
$options = array();
$options['url'] = $_POST['url'];			// URL of the division page
$options['content'] = $_POST['content'];	// Content of the division page

// Handle cases when no content is provided - Users should always have the right divisions url (we cannot guess it).
if ( is_null($options['content']) || strlen($options['content']) == 0 ) {
	
	if ( ENVIROMENT == 'PRODUCTION' ) {
		RestUtils::sendResponse(400); // Must provide content! Bad request!
	}else {
		$defaultParams = array('url' => 'http://kr.battle.net/sc2/ko/profile/2737020/1/%EC%B4%88%EB%B3%B4%EC%9C%A0%ED%9D%AC/ladder/leagues#current-rank',
							   'content' => '');
		$options = GeneralUtils::getDefaults($defaultParams, $options);

		// If in development, we fetch the contenct from the target url instead
		$urlconnect = new URLConnect($options['url'], 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode());
			exit;
		}
		$options['content'] = $urlconnect->getContent();
	}
}

$sc2division = new SC2Division($options['content'], $options['url']);
if ( ENVIROMENT == 'DEVELOPMENT' ) {
	$sc2division->displayArray();
}else {
	RestUtils::sendResponse(200, $sc2division->getJsonData(), '' , 'application/json');
}

?>