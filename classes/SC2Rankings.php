<?php

require_once('global-config.php');
require_once('../helpers/helper-fns.php');
require_once('../helpers/simple_html_dom.php');
require_once('../helpers/URLConnect.php');
require_once('../helpers/RestUtils.php');

class SC2Rankings {
	
	private $jsonData;
	private $options;
	
	private $dataToPrint;
	
	const CACHEAMOUNT = 100;		// Total number of rankings we wil store. Thus users cannot request more than this.
	const MAX_CACHE_TIME = 1800; 	// Every 30 min update rankings
	
	public function __construct($options) {
		$this->options = $options;
		
		// Check if we have the requested data in cache
		$requestCache = $this->getCachePath();
		
		// Only use cache when it exists, we specify to use cache, and when cached time is less than our max cache time
		if ( file_exists($requestCache) && $this->options['update'] == 0 &&
			  (time() - filemtime($requestCache)) < SC2Rankings::MAX_CACHE_TIME) {
			$this->jsonData = file_get_contents($requestCache);
		}else {
			// Get new json data from sc2ranks directly - since we caching results, its okay!
			$resultsArray = $this->getRankingsData();
			
			if ( $resultsArray == NULL && file_exists($requestCache) ) {
				// We didnt find rankings, use cached
				$this->jsonData = file_get_contents($requestCache);
			}else if( $resultsArray != NULL ) {
				$this->jsonData = json_encode($resultsArray);
				file_put_contents($requestCache, $this->jsonData, LOCK_EX);	
			}
		}
	}
	
	/**
	 * Get the json data within the range specified in options
	 * @return json Data within the range
	 */	
	public function getJsonData()
	{
		// Check if we have things to send
		if ( $this->jsonData == NULL) {
			return NULL;
		}
		
		// Get the array to need to send
		$jsonArray = json_decode($this->jsonData);
		
		// Grab our json data within a range.
		$newData = array();
		$newDataIndex = 0;
		$endIndex = min(($this->options['start'] + $this->options['amount']), count($jsonArray));
		for ( $i = $this->options['start']; $i < $endIndex; $i++ ) {
			$newData[$newDataIndex] = $jsonArray[$i];
			$newDataIndex++;
		}
		
		// Check if we have anything to send
		if ( count($newData) == 0 ) {
			return NULL;
		}
		
		return json_encode($newData);;
	}
	
	/**
	 * For testing, display our output json data in an array with html content
	 */
	public function displayArray()
	{
		$newData = json_decode($this->getJsonData());
		
		$this->addThingsToPrint('<pre>' . print_r($newData, TRUE) . '</pre>');
		
		$fullContent = RestUtils::getHTTPHeader('Testing') . $this->dataToPrint . RestUtils::getHTTPFooter(); 
		RestUtils::sendResponse(200, $fullContent);
	}
	
	/**
	 * This is the function that parses the target url to get our json array
	 * @returns array json array
	 */
	protected function getRankingsData()
	{
		// Create our data holder
		$rankingsArray = array();
		
		if ( $this->options['league'] == 'grandmaster' ) {
			if ( $this->options['region'] == 'global' ) {
				$rankingsArray = $this->getCombinedGMRankings();	
			}else {
				$rankingsArray = $this->getBnetGMRankings();	
			}
		}else {
			$rankingsArray = $this->getRanksRankingsData();	
		}
		
		if ( count($rankingsArray) <= 0 ) {
			return NULL;	
		}
		return $rankingsArray;
	}
	
	protected function getBnetGMRankings()
	{
		global $displayRegionMapper;
		
		// Map region to targeted GM link
		$gmMapper = array('na' => 'http://us.battle.net/sc2/en/ladder/grandmaster',
						  'eu' => 'http://eu.battle.net/sc2/en/ladder/grandmaster',
						  'sea' => 'http://sea.battle.net/sc2/en/ladder/grandmaster',
						  'krtw' => 'http://kr.battle.net/sc2/ko/ladder/grandmaster',
						  'cn' => 'http://www.battlenet.com.cn/sc2/zh/ladder/grandmaster');

		$targetURL = GeneralUtils::mapKeyToValue($gmMapper, $this->options['region']);
		$this->addThingsToPrint("<h2><a href=\"$targetURL\">$targetURL</a></h2><br />");
		
		// Get contents for results
		$urlconnect = new URLConnect($targetURL, 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode(), $this->dataToPrint);
		}
		$contents = str_get_html($urlconnect->getContent());
		
		// Get name of league
		$fullwords = $contents->find('#sub-header', 0)->plaintext;
		$season = $contents->find('#sub-header span', 0)->plaintext;
		$endpos = strpos($fullwords, $season);
		$divisionName = substr($fullwords, 0, $endpos);
		$divisionName = trim($divisionName);
		
		// Get rankings
		$rankingsArray = array();
		$rawRankings = $contents->find('table tr');
		if ( count($rawRankings) <= 2 ) return NULL;
		$gmRegion = GeneralUtils::mapKeyToValue($displayRegionMapper, $this->options['region']);
		$currentRow = 0;
		
		foreach ( $rawRankings as $oneRankNode ) {
			
			// Skip the table header
			if ( $currentRow == 0 ){
				$currentRow++;
				continue;	
			}
			
			$oneRank = array();
			$rowAdjustment = $oneRankNode->find('.banner', 0) ? 1 : 0;
			
			// Get players
			$playersArray = array();
			{
				$onePlayer = array();
				
				// Get race
				$playerNode = $oneRankNode->find('td', 2 + $rowAdjustment);
				$playerNode = $playerNode->find('a', 0);
				$race = $playerNode->getAttribute('class');
				$startpos = strpos($race, 'race-') + strlen('race-');
				$race = trim(substr($race, $startpos));
				$onePlayer['race'] = $race;
				
				// Get name
				$name = trim($playerNode->plaintext);
				$onePlayer['name'] = $name;
				
				// Get estimate ranks url
				$bnetLink = $playerNode->getAttribute('href');
				$bnetLink = GeneralUtils::getBaseURL($targetURL) . $bnetLink;
				$onePlayer['url'] = SC2Utils::estimateRanksLink($bnetLink);
				
				// Get bnet url
				$onePlayer['bnetLink'] = $bnetLink;
				
				$playersArray[] = $onePlayer;
			}
			$oneRank['players'] = $playersArray;
			
			
			// Get division data
			$oneDivision = array();
			{
				// Get joined date
				$joinedDate = $oneRankNode->find('td', 0 + $rowAdjustment)->getAttribute('data-tooltip');
				$oneDivision['joinedDate'] = SC2Utils::joinedDateToTimeStamp($joinedDate, $targetURL);
				//$oneDivision['joinedDate'] = date('j/n/Y', $oneDivision['joinedDate']); // Testing
				
				// Get division region
				$oneDivision['region'] = $gmRegion;
			
				// Get points
				$points = $oneRankNode->find('td', 3 + $rowAdjustment)->plaintext;
				$oneDivision['points'] = GeneralUtils::parseInt($points);
				
				// Get wins
				$wins = $oneRankNode->find('td', 4 + $rowAdjustment)->plaintext;
				$oneDivision['wins'] = GeneralUtils::parseInt($wins);
				
				// Get losses
				$losses = $oneRankNode->find('td', 5 + $rowAdjustment)->plaintext;
				$oneDivision['losses'] = GeneralUtils::parseInt($losses);
				
				// Get win ratio
				$diviser = $oneDivision['wins'] + $oneDivision['losses'];
				$oneDivision['winRatio'] = ($diviser > 0) ? $oneDivision['wins'] / $diviser : 0;
				
				// Get div league
				$oneDivision['league'] = 'grandmaster';
				
				// Get div name
				$oneDivision['name'] = $divisionName;
				
				// Get div rank
				$rank = $oneRankNode->find('td', 1 + $rowAdjustment)->getAttribute('data-raw');
				$oneDivision['rank'] = GeneralUtils::parseInt($rank);
				$oneRank['rank'] = $oneDivision['rank'];
				
				// Get prev rank
				$tooltipDiv = $playerNode->getAttribute('data-tooltip');
				$fullwords = $playerNode->parent()->find("$tooltipDiv", 0)->plaintext;
				$prevWords = $playerNode->parent()->find('strong', 0)->plaintext;
				$nextWords = $playerNode->parent()->find('strong', 1)->plaintext;
				$startpos = strpos($fullwords, $prevWords) + strlen($prevWords);
				$endpos = strpos($fullwords, $nextWords);
				$prevRank = substr($fullwords, $startpos, ($endpos - $startpos));
				$oneDivision['prevRank'] = GeneralUtils::parseInt($prevRank);
			}
			$oneRank['division'] = $oneDivision;
			
			$rankingsArray[] = $oneRank;
		}
		
		// Bnet GM rankings does not need to be sorted - we take data from BNET for granted
		
		return $rankingsArray;
	}
	
	protected function getCombinedGMRankings()
	{
		$cn = $this->getCachePath('cn');
		$krtw = $this->getCachePath('krtw');
		$eu = $this->getCachePath('eu');
		$na = $this->getCachePath('na');
		$sea = $this->getCachePath('sea');

		$cn = json_decode(file_get_contents($cn));
		$krtw = json_decode(file_get_contents($krtw));
		$eu = json_decode(file_get_contents($eu));
		$na = json_decode(file_get_contents($na));
		$sea = json_decode(file_get_contents($sea));
		
		$global = array_merge($cn, $krtw, $eu, $na, $sea);
		
		usort($global, array(__CLASS__, 'defaultSort'));
		$global = $this->addRankingsField($global);
		
		return $global;
	}
	
	protected function getRanksRankingsData()
	{
		$pagesNeeded = ceil(SC2Rankings::CACHEAMOUNT/100);
		
		for ( $i = 0; $i < $pagesNeeded; $i++ ) {
			
			// Get an array of raw rankings data
			$targetURL = $this->getTargetURL($i + 1);
			
			$this->addThingsToPrint("<h2><a href=\"$targetURL\">$targetURL</a></h2><br />");
			
			// Get our parsed json data
			$rankingsArray = $this->appendOneRanksPage($targetURL, $rankingsArray, $i == 0);
		}
		
		// This is rquired to turn array objects into actually objects
		$rankingsArray = json_encode($rankingsArray);
		$rankingsArray = json_decode($rankingsArray);
		
		// Sort our sc2ranks rankings according to our own default sorting algorithm
		usort($rankingsArray, array(__CLASS__, 'defaultSort'));
		
		// Insert rankings data into our data. TODO: Improve this
		$rankingsArray = $this->addRankingsField($rankingsArray);
		
		return $rankingsArray;
	}
	
	
	protected function appendOneRanksPage($targetURL, $rankingsArray, $isFirst)
	{
		global $displayRegionMapper;
		$bracket = $this->options['bracket'];
		$type = $this->options['raceType'];
		$league = $this->options['league'];
		
		// Get contents for results - exit if source is invalid
		$urlconnect = new URLConnect($targetURL, 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode(), $this->dataToPrint);
		}
		
		$contents = $urlconnect->getContent();
		$rawRankings = str_get_html($contents)->find('.tblrow');
		
		// Reload region value - used when region is not global
		$regionValue = strtoupper(GeneralUtils::mapKeyToValue($displayRegionMapper, $this->options['region']));
		foreach ( $rawRankings as $oneRanking )
		{
			// Start an ranking JSON object
			$oneRank = array();
			
			// Get players for rank
			$playersArray = array();
			{
				$playersNodes = $oneRanking->find('.character');
				foreach ( $playersNodes as $playerNode ) {
					
					// Create a new player
					$onePlayer = array();
					
					// Get race
					$onePlayer['race'] = strtolower($playerNode->find('img', 0)->getAttribute('class'));
					
					// Get name
					$nameTag = $playerNode->find('a', 0);
					$onePlayer['name'] = $nameTag->plaintext;
					
					// Get URL
					$partialLink = $nameTag->getAttribute('href');
					$playerURL = RANKSURL . $partialLink;
					$onePlayer['url'] = $playerURL;
					
					// Get estimated bnet url
					$onePlayer['bnetURL'] = SC2Utils::estimateBLink($onePlayer['url']);
					
					$playersArray[] = $onePlayer;
				}	
			}
			$oneRank['players'] = $playersArray;
				
			// Get user's best division data
			$oneDivision = array();
			{
				// Get division region
				if ( $this->options['region'] == 'global' )
				{
					// Use the region supplied by source and map it for display
					$regionValue = $oneRanking->find('.region', 0)->plaintext;
					$regionValue = strtoupper(GeneralUtils::mapKeyToValue($displayRegionMapper, $regionValue));
				}
				$oneDivision['region'] = $regionValue;
				
				// Get points
				$points = $oneRanking->find('.points', 0)->plaintext;
				$oneDivision['points'] = GeneralUtils::parseInt($points);
				
				// Get wins - every league expect for 4v4 team does not show wins
				if ( !($bracket == 4 && $type == 'team') ) {
					$wins = $oneRanking->find('.wins', 0)->plaintext;
					$oneDivision['wins'] = GeneralUtils::parseInt($wins);
				}
				
				// Get losses - all masters random or master team with bracker < 4 have losses
				if ( ($league == 'master' && $type == 'random') || 
					 ($league == 'master' && $type == 'team' && $bracket < 4) ) {
					$losses = $oneRanking->find('.losses', 0)->plaintext;
					$oneDivision['losses'] = GeneralUtils::parseInt($losses);
					$diviser = $oneDivision['wins'] + $oneDivision['losses'];
					$oneDivision['winRatio'] = ($diviser > 0) ? $oneDivision['wins'] / $diviser : 0;	// Manually calculate win ratio
				}
				
				// Get winRaio for master 4v4 random since it does have wins and losses
				if ( $league == 'master' && $bracket == 4 && $type == 'team' ) {
					$oneDivision['winRatio'] = floatval($oneRanking->find('.ratio', 0)->plaintext) / 100;
				}
				
				// Save division league - since we cannot have all league, using supplied param is sufficient
				$oneDivision['league'] = $league;
				
				if ( ($bracket <= 2 && $this->options['region'] != 'global') || $type == 'random' ) {
		
					// Get division name
					$oneDivision['name'] = $oneRanking->find('.division a', 0)->plaintext;
									
					// Get division rank
					if ( $bracket == 1 || $type == 'random' ) {
						$allContent = $oneRanking->find('.division', 0)->plaintext;
						$rankPosition = strpos($allContent, '#') + 1;
						$rankLength = strpos($allContent, ')') - $rankPosition;
						$divisionRank = substr($allContent, $rankPosition, $rankLength);
						$oneDivision['rank'] = GeneralUtils::parseInt($divisionRank);
					}
					
					// Get division url
					$divisionURL = RANKSURL . $oneRanking->find('.division a', 0)->getAttribute('href');
					$oneDivision['url'] = $divisionURL;
				}	
			}			
			$oneRank['division'] = $oneDivision;
			
			// Add a new rank to our array
			$rankingsArray[] = $oneRank;
		}
		
		return $rankingsArray;
	}
	
	/**
	 * Default sort by points and if points are equal then sort by win ratio. 
	 * If no win ratio then sort by # of wins. If wins are same then rank is then the same as well
	 */
	protected function defaultSort($a, $b)
	{		
		$finalCompare = 0;
		
		$pointsA = $a->division->points;
		$pointsB = $b->division->points;
			
		$winRatioA = $a->division->winRatio;
		$winRatioB = $b->division->winRatio;
			
		$winsA = $a->division->wins;
		$winsB = $b->division->wins;
			
		$nameA = $a->players[0]->name;
		$nameB = $b->players[0]->name;
		
		// Bigger points come first
		if ( $pointsA > $pointsB ) {
			$finalCompare = -1;
		}else if ( $pointsA < $pointsB ) {
			$finalCompare = 1;
		}
		
		// Compare win ratio
		if ( $finalCompare == 0 && !is_null($winRatioA)) {
			if ( $winRatioA > $winRatioB ) {
				$finalCompare = -1;
			}else if ( $winRatioA < $winRatioB ) {
				$finalCompare = 1;	
			}
		}
		
		if ( $finalCompare == 0 && !is_null($winsA) ) {
			// Compare wins #
			if ( $winsA > $winsB ) {
				$finalCompare = -1;
			}else if ( $winsA < $winsB ) {
				$finalCompare = 1;
			}
		}
		
		// Nothing is found still - just compare name then
		if ( $finalCompare == 0 ) {
			$finalCompare = strnatcmp($nameA, $nameB);
		}
		return $finalCompare;
	}
	
	protected function addRankingsField($rankingsArray)
	{
		$rank = 1;
		for ( $i = 0; $i < count($rankingsArray); $i++ ) {
			
			if ( $i > 0 && $points == $rankingsArray[$i-1]->division->points && 
				 $rankingsArray[$i]->division->winRatio == $rankingsArray[$i-1]->division->winRatio &&
				 $rankingsArray[$i]->division->wins == $rankingsArray[$i-1]->division->wins ) {
				$rankingsArray[$i]->rank = $rank - 1;
			}else {
				$rankingsArray[$i]->rank = $rank;
				$rank += 1;
			}
		}
		return $rankingsArray;
	}
	
	protected function getCachePath($region = NULL)
	{
		$currentpath = $_SERVER['DOCUMENT_ROOT'] .  $_SERVER['PHP_SELF'];
		$currentpath = dirname($currentpath);
		$endpos = strrpos($currentpath, '/');
		if ( $endpos === FALSE ) {
			$endpos = strrpos($currentpath, '\\');
		}
		$basePath = substr($currentpath, 0, $endpos);
		$fullPath = $basePath . DIRECTORY_SEPARATOR . 
						'cache' . DIRECTORY_SEPARATOR . $this->getIdentifierForRequest($region) . '.json';
		return $fullPath;
	}
	
	/** 
	 * Creates an unique identifier for the request. 
	 * Currently used as filename for cache
	 */
	protected function getIdentifierForRequest($region = NULL)
	{
		$region = (isset($region) && !is_null($region)) ? $region : $this->options['region'];
		return $region . '-' . $this->options['league'] . '-' 
				. $this->options['bracket'] . $this->options['raceType'] . '-' . $this->options['race'];
	}
	
	/** 
	 * Gets the url for a page num
	 */
	protected function getTargetURL($pageNum)
	{
		global $ranksRegionMapper;
		
		// Map the region
		$region = GeneralUtils::mapKeyToValue($ranksRegionMapper, $this->options['region']);
		
		// Adjust the league, race
		$league = strtolower($this->options['league']);
		$race = strtolower($this->options['race']);
		$bracket = GeneralUtils::parseInt($this->options['bracket']);
		if ( $this->options['raceType'] == 'random' && $this->options['bracket'] > 1 ) {
			$type = 'R';
		}
		
		// Final URL
		$ranksURL = RANKSURL . '/ranks/';
		$ranksURL .= $region . '/' . $league . '/' . $bracket .  $type . '/' . $race . '/points' . '/' . ($pageNum - 1)* 100;
		
		return $ranksURL ;
	}
	
	/**
	 * Quick function to add something to be printed
	 */
	public function addThingsToPrint($things)
	{
		$this->dataToPrint .= $things;	
	}
}

?>