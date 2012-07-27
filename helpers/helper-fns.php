<?php
require_once('../classes/global-config.php');

class SC2Utils {
	
	public static function joinedDateToTimeStamp($joinedDate, $url, $testing = ENVIROMENT)
	{
		// Get player region
		$startpos = strpos($url, 'http://') + 7;
		$endpos = strpos($url, '.', $startpos);
		$region = substr($url, $startpos, ($endpos - $startpos));
		
		// Map region to approriate time format
		preg_match('/\d+/', $joinedDate, $matches);
		$startpos = strpos($joinedDate, $matches[0]);
		$joinedDate = trim(substr($joinedDate, $startpos));
		preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $joinedDate, $matches);
		if ( $region == 'eu' ) {
		  // Because Europe is wierd
			$joinedDate = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
		}else if ( $region == 'us' || $region == 'sea' || $region == 'tw' ) {
			$joinedDate = $matches[3] . '-' . $matches[1] . '-' . $matches[2];
		}else {
		  // China and taiwan already uses the standard
		}
		$timeStamp = (int)strtotime($joinedDate);
		return ($testing == 'DEVELOPMENT') ? date('Y-m-d', $timeStamp) : $timeStamp;
	}
	
	public static function estimateBLink($ranksLink)
	{	
		// Extract region from link
		$startpos = strpos($ranksLink, '.com/') + 5;
		$endpos = strpos($ranksLink, '/', $startpos);
		$region = substr($ranksLink, $startpos, ($endpos - $startpos));
		
		// Extract identifier from link
		$startpos = $endpos + 1;
		$endpos = strpos($ranksLink, '/', $startpos);
		$identifier = substr($ranksLink, $startpos, ($endpos - $startpos));
		
		// Extract name from link
		$startpos = $endpos + 1;
		$name = substr($ranksLink, $startpos);
		
		// Estimate bnet link
		if ( $region == 'cn' ) {
			$bnetLink = 'http://www.battlenet.com.cn/sc2/zh/profile/' . $identifier . '/1/' . $name . '/';
		}else if ( $region == 'tw' ) {
			$bnetLink = 'http://tw.battle.net/sc2/zh/profile/' . $identifier . '/2/' . $name . '/';
		}else if ( $region == 'kr' ) {
			$bnetLink = 'http://kr.battle.net/sc2/ko/profile/' . $identifier . '/1/' . $name . '/';
		}else if ( $region == 'us' ) {
			$bnetLink = 'http://us.battle.net/sc2/en/profile/' . $identifier . '/1/' . $name . '/';
		}else if ( $region == 'la' ) {
			$bnetLink = 'http://us.battle.net/sc2/en/profile/' . $identifier . '/2/' . $name . '/';
		}else if ( $region == 'eu') {
			$bnetLink = 'http://eu.battle.net/sc2/en/profile/' . $identifier . '/1/' . $name . '/';
		}else if ( $region == 'ru' ) {
			$bnetLink = 'http://eu.battle.net/sc2/en/profile/' . $identifier . '/2/' . $name . '/';
		}else if ( $region == 'sea' ) {
		  $bnetLink = 'http://sea.battle.net/sc2/en/profile/' . $identifier . '/1/' . $name . '/';
		}else {
			$bnetLink = 'http://us.battle.net/sc2/en/profile/' . $identifier . '/1/' . $name . '/';
		}
		
		return $bnetLink;
	}
	
	public static function estimateRanksLink($url)
	{
		// Extract profile link
		$startpos = strpos($url, 'profile/') + strlen('profile/');
		$endpos = strpos($url, '/', $startpos);
		$identifier = substr($url, $startpos, ($endpos - $startpos));
		
		// Extract name from link
		$endpos = strrpos($url, '/');
		$startpos = strrpos($url, '/', - 2) + 1;
		$name = substr($url, $startpos, ($endpos - $startpos));
		
		// Extract region from link
		$region = ( strpos($url, '.cn') !== FALSE ) ? 'cn' : FALSE;
		$region = ( $region === FALSE && strpos($url, 'tw') !== FALSE ) ? 'tw' : $region;
		$region = ( $region === FALSE && strpos($url, 'sea') !== FALSE ) ? 'sea' : $region;
		
		if ( $region === FALSE && strpos($url, 'kr') !== FALSE ) {
			$startpos = strpos($url, $identifier) + strlen($identifier) + 1;
			$endpos = strpos($url, '/', $startpos);
			$specialNum = substr($url, $startpos, ($endpos - $startpos));
			$specialNum = GeneralUtils::parseInt($specialNum);
			
			if ( $specialNum == 1 ) {
				$region = 'kr';	
			}else if ( $specialNum == 2 ) {
				$region = 'tw';
			}
		}
		if ( $region === FALSE && strpos($url, 'us') !== FALSE ) {
			$startpos = strpos($url, $identifier) + strlen($identifier) + 1;
			$endpos = strpos($url, '/', $startpos);
			$specialNum = substr($url, $startpos, ($endpos - $startpos));
			$specialNum = GeneralUtils::parseInt($specialNum);
			
			if ( $specialNum == 1 ) {
				$region = 'us';	
			}else if ( $specialNum == 2 ) {
				$region = 'la';
			}
		}
		if ( $region === FALSE && strpos($url, 'eu') !== FALSE ) {
			$startpos = strpos($url, $identifier) + strlen($identifier) + 1;
			$endpos = strpos($url, '/', $startpos);
			$specialNum = substr($url, $startpos, ($endpos - $startpos));
			$specialNum = GeneralUtils::parseInt($specialNum);
			
			if ( $specialNum == 1 ) {
				$region = 'eu';	
			}else if ( $specialNum == 2 ) {
				$region = 'ru';
			}
		}
		
		// Estimate ranks link
		$url = 'http://www.sc2ranks.com/' . $region . '/' . $identifier . '/' . $name;
		
		return $url;
	}

	/**
	 * Determine if an url is a bnet url
	 * @param $url The url to test
	 * @returns TRUE if it is, FALSE if url is not bnet
	 */
	public static function isbnetURL($url)
	{
		if ( strpos($url, 'battle') !== FALSE  ) {
			return TRUE;
		}else if ( strpos($url, 'sc2ranks') !== FALSE ){
			return FALSE;
		}
		return FALSE;
	}

	public static function parseBnetImageInfo($imageStyle, $baseURL)
	{
		$imageData = array();
		
		// Get image url
		$startPos = strpos($imageStyle, 'url(\'') + 5;
		$endPos = strpos($imageStyle, '\')', $startPos);
		$imageURL = $baseURL . substr($imageStyle, $startPos, ($endPos - $startPos));
		$imageData['url'] = $imageURL;
		
		// Get image x
		$startPos = $endPos + 2;
		$endPos = strpos($imageStyle, 'px', $startPos);
		$xValue = substr($imageStyle, $startPos, ($endPos - $startPos));
		$imageData['x'] = GeneralUtils::parseInt(trim($xValue));
		
		// Get image y
		$startPos = $endPos + 2;
		$endPos = strpos($imageStyle, 'px', $startPos);
		$yValue = substr($imageStyle, $startPos, ($endPos - $startPos));
		$imageData['y'] = GeneralUtils::parseInt(trim($yValue));
		
		// Get image width
		$startpos = strpos($imageStyle, 'width:') + 6;
		$endpos = strpos($imageStyle, 'px', $startpos);
		$width = substr($imageStyle, $startpos, ($endpos - $startpos));
		$imageData['width'] = GeneralUtils::parseInt($width);
			
		// Get image height
		$startpos = strpos($imageStyle, 'height:') + 7;
		$endpos = strpos($imageStyle, 'px', $startpos);
		$width = substr($imageStyle, $startpos, ($endpos - $startpos));
		$imageData['height'] = GeneralUtils::parseInt($width);
		
		return $imageData;
	}
	
	public static function playerRegionFromBnetURL($bnetURL) {
	  // Get region
		global $displayRegionMapper;
		
		$startpos = strpos($bnetURL, 'http://');
		if ( $startpos !== false ) {
			$startpos += 7;
			$endpos = strpos($bnetURL, '.', $startpos);
			$region = substr($bnetURL, $startpos, ($endpos - $startpos));
			$region = GeneralUtils::mapKeyToValue($displayRegionMapper, $region);
			return $region;
		}
		return "AM";
  }
}

/**
 * Utility functions that can be used anywhere
 */
class GeneralUtils {
	
	/**
	 * Combines user supplied params with the default params
	 * @param $defaultParams 	The default params array
	 * @param $userParams 		The user params array
	 * @returns 				A combined params aray
	 */ 
	public static function getDefaults($defaultParams, $userParams)
	{
	  // Make a combined array based on defaults.
		$combinedArray = array();
		foreach ( $defaultParams as $key=>$value ) {
			if ( !array_key_exists($key, $userParams) || is_null($userParams[$key]) ) {
				// Default since user didnt supply
				$combinedArray[$key] = $value;
			}else {
				// Use user supplied param
				$combinedArray[$key] = $userParams[$key];
			}
		}
		
		// Include any params in user that is not included in default
		foreach ( $userParams as $key=>$value ) {
			if ( !array_key_exists($key, $defaultParams) || is_null($defaultParams[$key]) ) {
				// Default since user didnt supply
				$combinedArray[$key] = $value;
			}
		}
		
		return $combinedArray;
	}

	/**
	 * Parses a string into an int.
	 * This function removes all non-letters and commas.
	 */
	public static function parseInt($string) {
		
		// Remove all commas and dots as it maybe a number separator
		$string = preg_replace('/,/', '', $string);
		// Get the first number
		if(preg_match('/\d+/', $string, $array)) {
			return (int)$array[0];
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Maps a key to mapper and get its value
	 */	
	public static function mapKeyToValue($mapper, $key)
	{
		if ( isset($mapper[$key]) ) {
			return $mapper[$key];
		}else {
			return $key;
		}
	}
	
	public static function printArray($array)
	{
		echo "<br>";
		print_r($array);
		echo "</br>";	
	}
	
	public static function getBaseURL($url)
	{
		$offset = strpos($url, 'http://');
		if ( $offset !== FALSE ) {
			$offset += strlen('http://');
		}else {
			$offset = 0;	
		}
		$endPos = strpos($url, '/', $offset);
		$baseURL = substr($url, 0, $endPos);
		return $baseURL;
	}
	
	public static function encodeURL($url)
	{
	  $parsed = parse_url($url);
		$pathComponents = explode('/', $parsed['path']);
		foreach ( $pathComponents as $i => $comp ) {
		  $pathComponents[$i] = urlencode($comp);
		}
		$path = implode('/', $pathComponents);
		return $parsed['scheme'] . "://" . $parsed['host'] . $path;
	}
}
?>