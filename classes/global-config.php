<?php

define('ENVIROMENT', 'DEVELOPMENT');			  
define('RANKSURL', 'http://www.sc2ranks.com');

$ranksRegionMapper = array('global' => 'all',
						   'na' => 'am',
						   'krtw' => 'fea');
						   
$displayRegionMapper = array('krtw' => 'KR&TW',
					  	     'AM' => 'NA',
						     'KR/TW' => 'KR&TW',
							 'cn' => 'CN',
							 'www' => 'CN',
							 'us' => 'NA',
							 'ca' => 'NA',
							 'la' => 'NA',
							 'na' => 'NA',
							 'ru' => 'EU',
							 'eu' => 'EU',
							 'sea' => 'SEA',
							 'kr' => 'KR&TW',
							 'tw' => 'KR&TW');
						
// Set the error reporting for all pages
if ( ENVIROMENT == 'DEVELOPMENT' ) {
	error_reporting(E_ALL ^ E_NOTICE);	// Stop notice reporting
}else if ( ENVIROMENT == 'PRODUCTION' ) {
	error_reporting(0);					// Stop all error reporting
}

?>