<?

/** 
 * Take a division url and returns all info for that division
 */
require_once('../classes/SC2Division.php');
require_once('../helpers/helper-fns.php');

// Default Params, this is used when cettain options not specified
$defaultParams = array('url' => 'http://us.battle.net/sc2/en/profile/2439371/1/coLMinigun/ladder/leagues',
					   'offset' => 0,
					   'type' => 'json');
					   
// Get basic parameters
$options = array();
$options['url'] = $_REQUEST['url'];			    // URL of the division page. Ranks or BNET.
$options['content'] = $_REQUEST['content'];	// Content of the division page
$options['offset'] = $_REQUEST['offset']; 
$options['amount'] = $_REQUEST['amount'];
$options['type'] = $_REQUEST['type'];

// Merge user param with default
$options = GeneralUtils::getDefaults($defaultParams, $options);

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

$sc2division = new SC2Division($options);
$divisionData = $sc2division->parseDivision();

if ( $options['type'] == 'html' ) {
	GeneralUtils::printObject($divisionData);
}else if ( $options['type'] == 'json' ) {
  GeneralUtils::printJSON(json_encode($divisionData));
}

?>