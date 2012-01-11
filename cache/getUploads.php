<?php

$uuid = $_POST['uuid'];
$sessionID = $_POST['sessionID'];
$withUpgrades = $_POST['withUpgrades'];
$carbonSavings = $_POST['carbonSavings'];
$existingCost = $_POST['existingCost'];
$heatEnergy = $_POST['heatEnergy'];
$coolEnergy = $_POST['coolEnergy'];
$zipCode = $_POST['zipCode'];

if ( $_FILES['file']['error'] > 0 ) {
	echo "Return Code: " . $_FILES['file']['error'] . "<br />";	
}else {
	$conn = db_connect();
	
	// Check if user exists
	$result = $conn->query("SELECT uuid FROM users WHERE uuid='$uuid'");
	if ( !$result ) die("Cannot select uuid from users");
	$fileLocation = 'upload/' . $uuid . ".sqlite";
	if ( $result->num_rows == 0 ) {
		
		// New user, we insert new info
		$query = "INSERT INTO users VALUES('$uuid', NOW(), '$fileLocation')";
		$conn->query($query) or die('failed adding new');
	}else {
		// Update existing uuid
		$query = "UPDATE users SET modified='" . time() . "' WHERE uuid='$uuid'";
		$conn->query($query) or die('failed updating');
	}
	
	// Check if session info exists
	$result = $conn->query("SELECT uuid FROM sessions WHERE sessionID='$sessionID'");
	if ( !$result ) die("Cannot select session");
	if ( $result->num_row == 0 ) {
		// Insert new session.
		$query = "INSERT INTO sessions VALUES('$sessionID', '$uuid', '$existingCost', '$withUpgrades', '$carbonSavings', '$heatEnergy', '$coolEnergy', '$zipCode')";
		$conn->query($query) or die ('failed inserting new sessionID');
	}else {
		// Update existing session. No need update uuid	
		$query = "UPDATE sessions SET existingCost='$existingCost', withUpgrades='$withUpgrades', carbonSavings='$carbonSavings', heatEnergy='$heatEnergy', coolEnergy='$coolEnergy', zipCode='$zipCode' WHERE uuid='$uuid'";
		$conn->query($query) or die ('failed updating old sessionID');
	}
	
	// update file
	move_uploaded_file($_FILES['file']['tmp_name'], 'upload/' . $uuid . ".sqlite");
}

function db_connect() {
	$result = new mysqli('iviro.envirolytics.ca', 'envirolytics', 'kokanee11', 'iviroapp');
	if (!$result) {
		throw new Exception('Could not connect to database server');	
	}else {
		return $result;
	}
}

?>