<?

/** 
 * Takes all the info for a player and makes a best search on sc2ranks.
 * Params can include name, region, search type, page to take.
 * This script only get results as presented in sc2rance. Filtering or sorting is done on iphone to reduce server load.
 * We do not know how many searches in one page since that may vary.
 * On iphone, users can filter results for a specific league. In this case, it will still grab one page of data from this script.
 */

require_once('../classes/SC2Search.php');
require_once('../helpers/helper-fns.php');

// Default when no params are passed
$defaultParams = array('url' => 'http://eu.battle.net/sc2/en/profile/2953729/1/Milkshake',
                       'type' => 'json');
                       
// Get basic parameters
$options = array();
$options['url'] = $_REQUEST['url'];
$options = GeneralUtils::getDefaults($defaultParams, $options);

$rankingsObject = new SC2Search($options);
if ( $options['type'] == 'json' ){
    GeneralUtils::printJSON($rankingsObject->getProfileURLResult());
}

?>