<?

/** 
 * Takes all the info for a player and makes a best search on sc2ranks.
 * Params can include name, region, search type, page to take.
 * This script only get results as presented in sc2rance. Filtering or sorting is done on iphone to reduce server load.
 * We do not know how many searches in one page since that may vary.
 * On iphone, users can filter results for a specific league. In this case, it will still grab one page of data from this script.
 */

require_once('../classes/SC2Search.php');
require_once('../helpers/RestUtils.php');

$content = $_POST['content'];

// If no content, we try to return target url by looking at other params
if ( !isset($content) || $content = '' ) {
	
	// Constants
	$defaultParams = array('region' => 'global',
						   'name' => 'Draco',
						   'type' => 'exact',
						   'page' => '1');
	
	// Get basic parameters
	$options = array();
	$options['region'] = $_GET['region'];	// global, na, eu, sea, krtw, cn
	$options['name'] = $_GET['name'];		// word to search for
	$options['type'] = $_GET['type'];		// exact, contains, starts
	$options['page'] = $_GET['page'];		// starting page
	
	$options = GeneralUtils::getDefaults($defaultParams, $options);
	
	// We need to grab the content with user supplied options
	$targetURL = SC2Search::getTargetURL($options);
	
	// Get contents for results - testing! This part should be done by user
	if ( ENVIROMENT == 'DEVELOPMENT' ) {
		$urlconnect = new URLConnect($targetURL, 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode());
			exit;
		}
		$content = $urlconnect->getContent();
	}else {
		RestUtils::sendResponse(200, $targetURL, '', 'text-plain');
		exit;
	}
}

$sc2search = new SC2Search($content);

if ( ENVIROMENT == 'DEVELOPMENT' ) {
	$sc2search->addThingsToPrint("<h2><a href=\"$targetURL\">$targetURL</a></h2>");
	$sc2search->displayArray();
}else {
	RestUtils::sendResponse(200, $sc2search->getJsonData(), '', 'application/json');
}

?>