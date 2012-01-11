<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Untitled Document</title>
</head>

<body>
<?php

include('../classes/SC2Rankings.php');

$options = array('league' => 'grandmaster',
				 'type' => 1,
				 'race' => 'all',
				 'update' => true);
$allRegions = array('cn', 'krtw', 'kr', 'na', 'eu');

foreach ( $allRegions as $region ) {
	$options['region'] = $region;
	
	$temp = new SC2Rankings($options);
}
?>
</body>
</html>
