<?

/**
 * Get the showcased divisions for a player
 */

require_once('../classes/SC2Division.php');
require_once('../helpers/helper-fns.php');

// Default when no params are passed
$defaultParams = array('url' => 'http://eu.battle.net/sc2/en/profile/12641/2/AlkaduR/ladder/',
								'game' => 'wol',
					             'type' => 'json');
					             
// Get basic parameters
$options = array();
$options['game'] = $_REQUEST['game'];
$options['url'] = $_REQUEST['url']; // player's
$options['content'] = $_POST['content']; // Content of the ladders/leagues page

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
$divisions = $sc2division->getDivisionShowcase();

if ( $options['type'] == 'html' ) {
	GeneralUtils::printObject( $divisions );
}else if ( $options['type'] == 'json' ) {
  GeneralUtils::printJSON( json_encode($divisions) );
}

?>