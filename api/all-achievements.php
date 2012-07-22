<?php

require_once('../classes/SC2Achievements.php');
require_once('../helpers/RestUtils.php');

$options = array();
$options['type'] = $_REQUEST['type'];
$options['bnetURL'] = $_REQUEST['bnetURL'];
$defaultParams = array('type' => 'json', 
                       'bnetURL' => 'http://eu.battle.net/sc2/en/profile/12641/2/AlkaduR/');
$options = GeneralUtils::getDefaults($defaultParams, $options);

$allAchievements = SC2Achievements::getAllAchievements($options['bnetURL']);

if ( $options['type'] == 'html' ) {
 $data = '<pre>' . print_r(json_decode($allAchievements), TRUE) . '</pre>';
 RestUtils::sendResponse(200, $data, '', 'text/html');
}else if ( $options['type'] == 'json' ){
 RestUtils::sendResponse(200, $allAchievements, '', 'application/json');
}

?>