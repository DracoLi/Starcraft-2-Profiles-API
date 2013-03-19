<?

/** 
 * Provides feeds for our clients.
 *  Feeds include both feeds from us and Starcraft 2 related feeds from a
 *  variety of websites.
 */

require_once('../classes/SC2Feeds.php');
require_once('../helpers/RestUtils.php');

$options = array();
$options['region'] = $_REQUEST['region'];
$options['offset'] = $_REQUEST['offset'];
$options['game'] = $_REQUEST['game'];
$options['amount'] = $_REQUEST['amount'];
$options['type'] = $_REQUEST['type'];
$options['update'] = $_REQUEST['update'];

// Set default return type
$defaultParams = array(
    'amount' => '10', 
    'offset' => '0', 
    'type' => 'json',
    'region' => 'na',
    'game' => 'hots',
    'update' => 'false'
);
$options = GeneralUtils::getDefaults($defaultParams, $options);

$sc2feeds = new SC2Feeds($options);
if ( $options['type'] == 'html' ) {
  $sc2feeds->displayArray();
}else if ( $options['type'] == 'json' ) {
  RestUtils::sendResponse(200, $sc2feeds->getJsonData(), '', 'application/json');
}

?>