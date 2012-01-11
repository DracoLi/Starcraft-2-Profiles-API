<?

/**
 * Get all divisions and link for a bnet player url.
 */

require_once('../classes/SC2Division.php');
require_once('../helpers/RestUtils.php');

// Default when no params are passed
$defaultParams = array('url' => 'http://us.battle.net/sc2/en/profile/383803/1/BlackCitadel/',
					   'grabFirstDiv' => '0',
					   'content' => '');

// Get basic parameters
$options = array();
$options['url'] = $_POST['url'];					// global, na, sea, eu, krtw, cn
$options['grabFirstDiv'] = $_POST['grabFirstDiv'];	// 0 if do not parse first division as well. 1 parses it and returns it
$options['content'] = $_POST['content'];			// Content of the page

$options = GeneralUtils::getDefaults($defaultParams, $options);

// Handle cases when no content is provided
if ( is_null($options['content']) || strlen($options['content']) == 0 ) {

	// Get and set target url
	$targetURL = SC2Division::getBNETPlayerDivisionsURL($options['url']);
	$options['url'] = $targetURL;
	
	// If production, we return the target url the user needs to retrieve from
	if ( ENVIROMENT == 'PRODUCTION' ) {
		RestUtils::sendResponse(200, $targetURL, '', 'text-plain');
	}else {
		// If in development, we fetch the contenct from the target url instead	
		$urlconnect = new URLConnect($targetURL, 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode());
			exit;
		}
		$options['content'] = $urlconnect->getContent();
	}
}

// Dermine if we need to grab first div
$grabFirst = FALSE;
if ( isset($options['grabFirstDiv']) && $options['grabFirstDiv'] == 1 ) {
	$grabFirst = TRUE;
}

$sc2division = SC2Division::getBNETPlayerDivisionsList($options['content'], $options['url'], $grabFirst);
if ( ENVIROMENT == 'DEVELOPMENT' ) {
	$html = RestUtils::getHTTPHeader('Testing') . "<h2><a href=\"" . $options['url'] . "\">" . $options['url'] . "</a></h2>" . "<pre>" . print_r(json_decode($sc2division), TRUE) . "</pre>" . RestUtils::getHTTPFooter();
	RestUtils::sendResponse(200, $html);
}else {
	RestUtils::sendResponse(200, $sc2division, '', 'application/json');
}

?>