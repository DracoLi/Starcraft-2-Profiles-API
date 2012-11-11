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

$options = array();
$options['content'] = $_REQUEST['content'];
$options['url'] = $_REQUEST['url'];

// If no content, we try to return target url by looking at other params
if ( ( !isset($options['content'] ) || $options['content'] == '' ) && 
     ( !isset($options['url']) || $options['url'] == '' ) ) {
	// Constants
	$defaultParams = array('region' => 'global',
						   'name' => 'Draco',
						   'type' => 'starts', // contains, starts, end, exact
						   'page' => '1');
	
	// Get basic parameters
	$options = array();
	$options['region'] = $_REQUEST['region'];	// global, na, eu, sea, krtw, cn
	$options['name'] = $_REQUEST['name'];		// word to search for
	$options['type'] = $_REQUEST['type'];		// exact, contains, starts
	$options['page'] = $_REQUEST['page'];		// starting page
	
	$options = GeneralUtils::getDefaults($defaultParams, $options);
	
	// We need to grab the content with user supplied options
	$targetURL = SC2Search::getTargetURL($options);
	
	// Get URL to use
	RestUtils::sendResponse(200, $targetURL, '', 'text-plain');
	exit;
}

// Get the content for the user if an url is provided but no content is
if ( isset($options['url']) && strlen($options['url']) > 0 
     && (!isset($options['content']) || strlen($options['content']) == 0) ) {
  // Get contents for provided url
  $urlconnect = new URLConnect($options['url'], 100, FALSE);
  if ( $urlconnect->getHTTPCode() != 200 ) {
  	RestUtils::sendResponse($urlconnect->getHTTPCode());
  	exit;
  }
  $options['content'] = $urlconnect->getContent();
}

// Set default return type
$defaultParams = array('type' => 'json', 'content' => '');
$options = GeneralUtils::getDefaults($defaultParams, $options);

$sc2search = new SC2Search($options['content']);
if ( $options['type'] == 'html' ) {
  $sc2search->addThingsToPrint("<h2><a href=\"$targetURL\">$targetURL</a></h2>");
  $sc2search->displayArray();
}else if ( $options['type'] == 'json' ){
  RestUtils::sendResponse(200, $sc2search->getJsonData(), '', 'application/json');
}
?>