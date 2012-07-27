<?php

define('ENVIROMENT', 'DEVELOPMENT');			  
define('RANKSURL', 'http://www.sc2ranks.com');

$ranksRegionMapper = array('global' => 'all',
						               'na' => 'am',
						               'krtw' => 'fea');

// Map how BNET or Ranks displays region to what we want to display for our results
$displayRegionMapper = array('krtw' => 'KR&TW',
					  	        'AM' => 'AM',
						          'KR/TW' => 'KR&TW',
							        'cn' => 'CN',
							        'www' => 'CN',
							        'us' => 'AM',
							        'ca' => 'AM',
							        'la' => 'AM',
							        'na' => 'AM',
							        'ru' => 'EU',
							        'eu' => 'EU',
							        'sea' => 'SEA',
							        'kr' => 'KR',
							        'tw' => 'TW');
						
// Set the error reporting for all pages
if ( ENVIROMENT == 'DEVELOPMENT' ) {
	error_reporting(E_ALL ^ E_NOTICE);	// Stop notice reporting
}else if ( ENVIROMENT == 'PRODUCTION' ) {
	error_reporting(0);					// Stop all error reporting
}

?>