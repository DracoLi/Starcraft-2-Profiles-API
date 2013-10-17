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


	const CACHEAMOUNT = 200;		// Total number of rankings we wil store. Thus users cannot request more than this.
	const MAX_CACHE_TIME = 1800; 	// Every 30 min update rankings
	const OUR_RANKINGS_PER_PAGE = 30;
	const SC2RANKS_RANKS_PER_PAGE = 100;


	public function __construct($options)
	{
		global $redis;
		$this->options = $options;

		// Check if we have the requested data in cache
		$requestCache = $this->getCachePath();

		// Only use cache if three conditions are met.
		// 1. If the rankings exist
		// 2. Update is not true in passed params
		// 3. If existing cache exsits, the cached time is less than our max cache time
		if ( $redis->exists($requestCache) &&
			 (time() - $redis->get($requestCache . '-mtime')) < SC2Rankings::MAX_CACHE_TIME &&
			 $this->options['update'] != 'true')
		{
			$this->jsonData = $redis->get($requestCache);
		}
		else
		{
			// Get new json data from sc2ranks directly - since we caching results, its okay!
			$resultsArray = $this->getRankingsData();
			if ( $resultsArray === NULL && $redis->exists($requestCache) ) {
				// If we didnt find rankings, use cached (prob due to error connection to bnet)
				$this->jsonData = $redis->get($requestCache);
			}else {
			  	// Save our rankings data (sc2ranks or bnet GM)
				$this->jsonData = json_encode($resultsArray);
				$redis->set($requestCache, $this->jsonData);
				$redis->set($requestCache . '-mtime', time());
			}
		}
	}

	/**
	 * Get the json data within the range specified in options
	 * All requests should go through this
	 * @return json Data within the range
	 */
	public function getJsonData($jsonData = NULL)
	{
		// Check if we have things to send
		if ( $this->jsonData == NULL && $jsonData == NULL) {
			return NULL;
		}

		$targetJsonData = ($jsonData == NULL) ? $this->jsonData : $jsonData;

		// Get the array to need to send
		$jsonArray = json_decode($targetJsonData);

    	// For grandmaster with a specific race we have to sort thorugh it manually
	  	$race = $this->options['race'];
		if ( $this->options['league'] == 'grandmaster' && $race != 'all' )
		{
			// If not all race then only extract the players with the specified race
			$adjustedArray = array();
			foreach ( $jsonArray as $oneRank ) {
				if ( $oneRank['players'][0]['race'] == $race ) {
					$adjustedArray[] = $oneRank;
				}
			}
			$jsonArray = $adjustedArray;
		}

		// Grab our json data within the user specified range
		$offset = $this->options['offset'];
		$amount = $this->options['amount'];
		$ranksData = array_slice($jsonArray, $offset ,$amount);

		// Add in the total amount of feeds to have
	  	$jsonResult = array();
	  	$jsonResult['total'] = count($jsonArray);
	  	$jsonResult['returnedAmount'] = count($ranksData);
	  	$jsonResult['ranks'] = $ranksData;

		return json_encode($jsonResult);
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
		if ( $this->options['league'] == 'grandmaster' && $this->options['game'] == 'wol' ) {
		  $rankingsArray = [];
		}else {
			$rankingsArray = $this->getRanksRankingsData();
		}

		if ( count($rankingsArray) <= 0 ) {
			return array();
		}
		return $rankingsArray;
	}

	protected function getBnetGMRankings($game = NULL, $region = NULL)
	{
		global $displayRegionMapper;

		$region = ($region == NULL) ? $this->options['region'] : $region;
		$game = ($game == NULL) ? $this->options['game'] : $game;

		// Map region to targeted GM link
		if ( $game == 'wol') {
			$gmMapper = array('na' => 'http://www.sc2ranks.com/hots/am/1v1/grandmaster/all',
		                  	'am' => 'http://www.sc2ranks.com/hots/am/1v1/grandmaster/all',
						  					'eu' => 'http://www.sc2ranks.com/hots/eu/1v1/grandmaster/all',
			        				  'sea' => 'http://www.sc2ranks.com/hots/sea/1v1/grandmaster/all',
			        				  'krtw' => 'http://www.sc2ranks.com/hots/fea/1v1/grandmaster/all',
			        				  'tw' => 'http://www.sc2ranks.com/hots/fea/1v1/grandmaster/all',
			        				  'kr' => 'http://www.sc2ranks.com/hots/fea/1v1/grandmaster/all',
        			      		'cn' => 'http://www.sc2ranks.com/hots/cn/1v1/grandmaster/all');
		}else {
			$gmMapper = array('na' => 'http://www.sc2ranks.com/hots/am/1v1/grandmaster/all',
		                  	'am' => 'http://www.sc2ranks.com/hots/am/1v1/grandmaster/all',
						  					'eu' => 'http://www.sc2ranks.com/hots/eu/1v1/grandmaster/all',
			        				  'sea' => 'http://www.sc2ranks.com/hots/sea/1v1/grandmaster/all',
			        				  'krtw' => 'http://www.sc2ranks.com/hots/fea/1v1/grandmaster/all',
			        				  'tw' => 'http://www.sc2ranks.com/hots/fea/1v1/grandmaster/all',
			        				  'kr' => 'http://www.sc2ranks.com/hots/fea/1v1/grandmaster/all',
        			      		'cn' => 'http://www.sc2ranks.com/hots/cn/1v1/grandmaster/all');
		}


		$targetURL = GeneralUtils::mapKeyToValue($gmMapper, $region);
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
		if ( count($rawRankings) <= 2 ) {
			// No results found
			return array();
		}
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

				// Get encoded bnet url
				$bnetLink = $playerNode->getAttribute('href');
				$bnetLink = GeneralUtils::getBaseURL($targetURL) . $bnetLink;
        		$bnetLink = GeneralUtils::encodeURL($bnetLink);

				// Set encoded bnet url
				$onePlayer['bnetURL'] = $bnetLink;

				// Get ranks url
				$onePlayer['ranksURL'] = SC2Utils::estimateRanksLink($bnetLink);

				// Get player's region
				$onePlayer['region'] = SC2Utils::playerRegionFromBnetURL($bnetLink);

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
				$divisor = $oneDivision['wins'] + $oneDivision['losses'];
				$oneDivision['winRatio'] = ($divisor > 0) ? $oneDivision['wins'] / $divisor : 0;

				// Get div league
				$oneDivision['league'] = 'grandmaster';

				// Get div name
				$oneDivision['name'] = $divisionName;

				// Get div region
				//$oneDivision['region'] = $gmRegion;

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

		// NOTE: Bnet GM rankings does not need to be sorted - we take data from BNET for granted
		return $rankingsArray;
	}

	/**
	 * This function gets GM rankings from BNET for a specific region and save it to disk
	 */
	protected function saveGMRankingsForRegion($game, $region) {
		global $redis;
	  $filePath = $this->getCachePath($game, $region);
	  $rankingsArray = $this->getBnetGMRankings($game, $region);
	  $redis->set($filePath, json_encode($rankingsArray));
	  $redis->set($filePath . '-mtime', time());
	}

	/**
	 * This function returns grandmasters rankings across regions
	 *  Rankings data are taken from cache, this method does not retreive data from BNET
	 */
	protected function getCombinedGMRankings($game)
	{
		global $redis;

		// This function can execute longer since we might need to retrieve data from many sources
		set_time_limit(60*10);

		$game = $this->options['game'];

		// Get file path of all our GM data
		$cn = $this->getCachePath($game, 'cn');
		$krtw = $this->getCachePath($game, 'krtw');
		$eu = $this->getCachePath($game, 'eu');
		$na = $this->getCachePath($game, 'am');
		$sea = $this->getCachePath($game, 'sea');

		// This part checks if we have GM data for each region.
		// If not we fetches it from BNET
		if ( !$redis->exists($cn) ) {
		  $this->saveGMRankingsForRegion($game, 'cn');
		}
		if ( !$redis->exists($krtw) ) {
		  $this->saveGMRankingsForRegion($game, 'krtw');
		}
		if ( !$redis->exists($eu) ) {
		  $this->saveGMRankingsForRegion($game, 'eu');
		}
		if ( !$redis->exists($na) ) {
		  $this->saveGMRankingsForRegion($game, 'am');
		}
		if ( !$redis->exists($sea) ) {
		  $this->saveGMRankingsForRegion($game, 'sea');
		}

		// Gets data from file and decode it to objects
		$cn = json_decode($redis->get($cn));
		$krtw = json_decode($redis->get($krtw));
		$eu = json_decode($redis->get($eu));
		$na = json_decode($redis->get($na));
		$sea = json_decode($redis->get($sea));

		$global = array_merge($cn, $krtw, $eu, $na, $sea);

		usort($global, array(__CLASS__, 'defaultRankingsSort'));
		$global = $this->addRankingsField($global);

		return $global;
	}

	protected function getRanksRankingsData()
	{
		$rankingsArray = array();
		$pagesNeeded = ceil(SC2Rankings::CACHEAMOUNT/SC2Rankings::SC2RANKS_RANKS_PER_PAGE);
		for ( $i = 0; $i < $pagesNeeded; $i++ ) {

			// Get an array of raw rankings data
			$targetURL = $this->getTargetRanksURL($i + 1);

			$this->addThingsToPrint("<h2><a href=\"$targetURL\">$targetURL</a></h2><br />");

			// Get our parsed json data
			$rankingsArray = $this->appendOneRanksPage($targetURL, $rankingsArray, $i == 0);
		}

		// This is rquired to turn array objects into actually objects
		$rankingsArray = json_encode($rankingsArray);
		$rankingsArray = json_decode($rankingsArray);

		// Sort our sc2ranks rankings according to our own default sorting algorithm
		if ( sizeof($rankingsArray) > 0 ) {
			usort($rankingsArray, array(__CLASS__, 'defaultRankingsSort'));
		}

		// Insert rankings data into our data. TODO: Improve this
		$rankingsArray = $this->addRankingsField($rankingsArray);

		return $rankingsArray;
	}


	protected function appendOneRanksPage($targetURL, $rankingsArray, $isFirst)
	{
		global $displayRegionMapper;

		// Parse bracket and bracket type
		$rawBracket = $this->options['bracket'];
		list($bracket, $type) = $this->getBracketAndType($rawBracket);
		$league = $this->options['league'];

		// Get contents for results - exit if source is invalid
		// echo $targetURL;
		$urlconnect = new URLConnect($targetURL, 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode(), $this->dataToPrint);
		}

		$contents = $urlconnect->getContent();
		$rawRankings = str_get_html($contents)->find('table tbody tr');

		// Get the provided region
		foreach ( $rawRankings as $oneRanking )
		{
			// Start an ranking JSON object
			$oneRank = array();

			// Handle when no results are present
			$columnSize = sizeof($oneRanking->find('td'));
			if ( $columnSize <= 1 ) {
				continue;
			}

			// Get players for rank
			$divisionRegion = NULL;
			$playersArray = array();
			{
				$playersNodes = $oneRanking->find('.character');
				foreach ( $playersNodes as $playerNode ) {

					// Create a new player
					$onePlayer = array();

					// Some wierd cases where we don't have a friend in a character container
					if ( !$playerNode->find('a', 0) ) {
						continue;
					}

					// Get race
					$raceClass = $playerNode->find('.races', 0)->getAttribute('class');
					$startpos = strpos($raceClass, '-') + 1;
					$endpos = strpos($raceClass, 'races') - 1;
					$race = substr($raceClass, $startpos, $endpos - $startpos);
					$onePlayer['race'] = strtolower($race);

					// Get name
					$nameNode = $playerNode->find('a', 0);
					$name = $nameNode->plaintext;
					if ( strpos($name, 'bar') === 0 ) {
						$name = substr($name, 5);
					}else {
						$name = $name;
					}
					$onePlayer['name'] = $name;

					// Get URL
					$partialLink = $nameNode->getAttribute('href');
					$playerURL = RANKSURL . $partialLink;
					$onePlayer['ranksURL'] = $playerURL;

					// Get estimated bnet url
					$onePlayer['bnetURL'] = SC2Utils::estimateBLink($onePlayer['ranksURL']);

					// Estimate player region based on bnet url
					$onePlayer['region'] = SC2Utils::playerRegionFromBnetURL($onePlayer['bnetURL']);

					if ($divisionRegion === NULL) {
					  $divisionRegion = $onePlayer['region'];
					}

					$playersArray[] = $onePlayer;
				}
			}
			$oneRank['players'] = $playersArray;

			// Get user's best division data
			$oneDivision = array();
			{
				// Get points
				$points = $oneRanking->find('.points', 0)->plaintext;
				$oneDivision['points'] = GeneralUtils::parseInt($points);

				// Get wins - every league expect for 4v4 team does not show wins
				if ( !($bracket == 4 && $type == 'team') ) {
					$wins = $oneRanking->find('.wins', 0)->plaintext;
					$oneDivision['wins'] = GeneralUtils::parseInt($wins);
				}

				if ( count($oneRanking->find('.losses')) > 0 ) {
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

				// Get division region
				$oneDivision['region'] = $divisionRegion;
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
	protected function defaultRankingsSort($a, $b)
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
			// Check if this rank is same as the previous one.
			if ( $i > 0 &&
			   $rankingsArray[$i]->division->points == $rankingsArray[$i-1]->division->points &&
				 $rankingsArray[$i]->division->winRatio == $rankingsArray[$i-1]->division->winRatio &&
				 $rankingsArray[$i]->division->wins == $rankingsArray[$i-1]->division->wins ) {
				$rankingsArray[$i]->rank = $rank - 1;
			}else {
				$rankingsArray[$i]->rank = $rank;
				$rank++;
			}
		}
		return $rankingsArray;
	}

	protected function getCachePath($game = NULL, $region = NULL)
	{
		$fullPath = GeneralUtils::serverBasePath() . DIRECTORY_SEPARATOR .
			'cache' . DIRECTORY_SEPARATOR . 'ranks' . DIRECTORY_SEPARATOR .
			$this->getIdentifierForRequest($game, $region) . '.json';
		return $fullPath;
	}

	/**
	 * Creates an unique identifier for the request.
	 * Currently used as filename for cache
	 */
	protected function getIdentifierForRequest($game = NULL, $region = NULL)
	{
		$region = (isset($region) && !is_null($region)) ? $region : $this->options['region'];
		$game = (isset($game) && !is_null($game)) ? $game : $this->options['game'];
		$identifier = $region . '-' . $game . '-' . $this->options['league'];
		if ( $this->options['league'] != 'grandmaster' ) {
		  $identifier .= '-' . $this->options['race'] . '-' . $this->options['bracket'];
		}
		return $identifier;
	}

	/**
	 * Gets the url for a page num
	 */
	protected function getTargetRanksURL($pageNum)
	{
		global $ranksRegionMapper;

		// Map the region
		$region = GeneralUtils::mapKeyToValue($ranksRegionMapper, $this->options['region']);
		$game = $this->options['game'];

		// Adjust the league, race
		$league = strtolower($this->options['league']);
		$race = strtolower($this->options['race']);
		list($bracket, $type) = $this->getBracketAndType($this->options['bracket']);
		if ( $bracket == 1 ) {
			$type = '';
		}
		$bracket = $bracket . 'v' . $bracket;

		// Final URL
		$ranksURL = RANKSURL . '/' . $game . '/' . $region . '/' . $bracket .  $type . '/' . $league . '/' . $race . '/' . $pageNum;
		return $ranksURL ;
	}

	/**
	 * This function separates a bracket string into the bracket and type component.
	 * Ex: 2r returns 2, and r.
	 */
	protected function getBracketAndType($rawBracket)
	{
	  preg_match('/(\d)/', $rawBracket, $array);
		$bracket = GeneralUtils::parseInt($array[1]);
		preg_match('/(\d)(\w)/', $rawBracket, $array);
		$type = $array[2];
		return array($bracket, $type);
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
