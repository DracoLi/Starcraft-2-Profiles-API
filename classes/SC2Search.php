<?php

require_once('global-config.php');
require_once('../helpers/helper-fns.php');
require_once('../helpers/simple_html_dom.php');
require_once('../helpers/RestUtils.php');
require_once('../helpers/URLConnect.php');

/**
 * Handles parsing of all search related results on sc2ranks
 * This class can take the search result html to parse and also form the url link to the page to parse if given params
 *
 * @author Draco Li
 * @version 1.0
 */
class SC2Search {
	
	private $content;
	private $league;
	private $options;
	private $dataToPrint;
	
	public function __construct($options)
	{
		$this->options = $options;

		if ( isset($options['content']) ) {
			$this->content = $options['content'];
		}

		if ( isset($options['league']) ) {
			$this->league = $options['league'];
		}
	}

	public function parseSearchResultsContent()
	{
		$rankResults = $this->getRanksSearchResults();
		$rankResults['players'] = $this->adjustForLeague($rankResults['players']);
		return json_encode($rankResults);
	}
	
	// Testing
	public function displayArray()
	{
		$this->addThingsToPrint('<pre>' . print_r(json_decode($this->getJsonData()), TRUE) . '</pre>');
		
		$fullContent = RestUtils::getHTTPHeader('Testing') . $this->dataToPrint . RestUtils::getHTTPFooter(); 
		RestUtils::sendResponse(200, $fullContent);
	}

	public function getProfileURLResult()
	{
		$vars = 'character[url]=' . $this->options['url'];
		$targetURL = 'http://www.sc2ranks.com/char';
		$urlconnect = new URLConnect($targetURL, 100, FALSE, True, $vars);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode());
		 	exit;
		}
		$content = $urlconnect->getContent();

		// Get raw data
		$domHTML = str_get_html($content);

		// Handle when sc2ranks did not return the search table but the results page
		if ( count($domHTML->find('table.search')) == 0 ) {
			$result = $this->getRanksSinglePageResult($domHTML);
			return json_encode($result);
		}
	}
	
	public static function getTargetURL($options)
	{
		global $ranksRegionMapper;
	
		$region = GeneralUtils::mapKeyToValue($ranksRegionMapper, $options['region']);
	
		$targetURL = 'http://www.sc2ranks.com/search/' . 
					$options['type'] . '/' . $region . 
					'/' . rawurlencode($options['name']) . '/' . ($options['page'] - 1)* 100;
					
		return $targetURL;
	}
	
	protected function getRanksSearchResults()
	{
		// Get raw data
		$domHTML = str_get_html($this->content);

		// Handle when sc2ranks did not return the search table but the results page
		if ( count($domHTML->find('table.search')) == 0) {
			return $this->getRanksSinglePageResult($domHTML);
		}

		// From now on handle when the result returned is a table
		$rawResults = $domHTML->find('.tblrow');

		// Initilize our results array
		$jsonArray = array();
			
		// Get total pages this request
		$totalPages = GeneralUtils::parseInt($domHTML->find('.paginate-top .page', 0)->plaintext);
		$totalPages = $totalPages === FALSE ? 1 : $totalPages;
		$jsonArray['pages'] = $totalPages;
		$jsonArray['players'] = array();
		
		foreach ( $rawResults as $oneResult ) {
			
			$onePlayer = array();
			
			// Add search result player data
			$tempChar = $oneResult->find('.character0 a', 0);
			$charName = $tempChar->plaintext;
			$partialLink = $tempChar->getAttribute('href');
			$charLink = RANKSURL . $partialLink;
			$onePlayer['name'] = $charName;
			$onePlayer['ranksURL'] = $charLink;
			$onePlayer['bnetURL'] = SC2Utils::estimateBLink($onePlayer['ranksURL']);	
			$onePlayer['region'] = SC2Utils::playerRegionFromBnetURL($onePlayer['bnetURL']);
			
			// Get user's best division data
			$oneDivision = array();
			{
				// Get region for player
				$oneDivision['region'] = $onePlayer['region'];
				
				// Get division points
				$tempNode = $oneResult->find('.points', 0);
				$points = $tempNode->find('span', 0)->plaintext;
				$oneDivision['points'] = GeneralUtils::parseInt($points);
				
				// Get division league
				$league = $tempNode->find('img', 0)->getAttribute('alt');
				$endPos = strpos($league, '-');
				$league = substr($league, 0, $endPos);
				$oneDivision['league'] = strtolower($league);
				
				// Get division type
				$typeString = $tempNode->find('span', 1)->plaintext;
				preg_match('/\d+/', $typeString, $matches);
				$type = $matches[0];
				$oneDivision['bracket'] = GeneralUtils::parseInt($type);
				
				// Get wins
				$wins = $oneResult->find('.wins', 0)->plaintext;
				$oneDivision['wins'] = GeneralUtils::parseInt($wins);
				
				if ( count($oneResult->find('.losses')) > 0 ) {
					$losses = $oneResult->find('.losses', 0)->plaintext;
					$oneDivision['losses'] = GeneralUtils::parseInt($losses);
					$diviser = $oneDivision['wins'] + $oneDivision['losses'];
					$oneDivision['winRatio'] = ($diviser > 0) ? $oneDivision['wins'] / $diviser : 0;
				}
				
				// Get division rank
				$allContent = $oneResult->find('.division', 0)->plaintext;
				$startpos = strpos($allContent, '#') + 1;
				$endpos = strpos($allContent, ')');
				$divisionRank = substr($allContent, $startpos, ($endpos - $startpos));
				$oneDivision['rank'] = GeneralUtils::parseInt($divisionRank);
				
				// Get division name
				$oneDivision['name'] = $oneResult->find('.division a', 0)->plaintext;
					
				// Get division url
				$divisionURL = RANKSURL . $oneResult->find('.division a', 0)->getAttribute('href');
				$oneDivision['ranksURL'] = $divisionURL;
			}
			// Add division to one search
			$onePlayer['divisions'] = $oneDivision;
			
			// Add one result to search content
			$jsonArray['players'][] = $onePlayer;
				
		}// End adding all our search results
		
		// Return our fresh json array
		return $jsonArray;
	}
	
	/**
	 * Get SC2Ranks result for a single page research (SC2Ranks in this case returns the player info page instead
	 * of the search table)
	 */
	protected function getRanksSinglePageResult($domHTML)
	{
		$rawResults = $domHTML->find('.charprofile', 0);

		$onePlayer = array();
			
		// Add search result player data
		$profileNode = $rawResults->find('.profile', 0);
		$charName = $profileNode->find('.name a', 0)->plaintext;
		$charLink = $profileNode->find('.name a', 0)->getAttribute('href');
		$onePlayer['name'] = $charName;
		$onePlayer['ranksURL'] = "yo mama";
		$onePlayer['bnetURL'] = $charLink;
		$onePlayer['region'] = SC2Utils::playerRegionFromBnetURL($onePlayer['bnetURL']);
		
		// Get user's best division data
		$firstDivisionNode = $rawResults->find('.leagues', 0);

		$oneDivision = array();
		if ( count($rawResults->find('.leagues .summary', 0)) > 0 )
		{
			// Get region for player
			$oneDivision['region'] = $onePlayer['region'];
			
			// Get division points, wins, looses
			$summaryNode = $firstDivisionNode->find('.summary', 0);
			$points = $summaryNode->find('.number', 0)->plaintext;
			$oneDivision['points'] = GeneralUtils::parseInt($points);
			
			// Get wins
			$wins = $summaryNode->find('.green', 0)->plaintext;
			$oneDivision['wins'] = GeneralUtils::parseInt($wins);
			
			// Get losses
			if ( count($summaryNode->find('.red')) > 0 ) {
				$losses = $summaryNode->find('.red', 0)->plaintext;
				$oneDivision['losses'] = GeneralUtils::parseInt($losses);
				$diviser = $oneDivision['wins'] + $oneDivision['losses'];
				$oneDivision['winRatio'] = ($diviser > 0) ? $oneDivision['wins'] / $diviser : 0;
			}

			// Get division league
			$league = $firstDivisionNode->find('span.badge', 0)->getAttribute('class');
			$startPos = strpos($league, '-') + 1;
			$endPos = strpos($league, ' ', $startPos);
			$league = substr($league, $startPos, $endPos - $startPos);
			$oneDivision['league'] = strtolower($league);

			// Get division type
			$typeString = $firstDivisionNode->find('.headertext', 0)->plaintext;
			preg_match('/\d+/', $typeString, $matches);
			$type = $matches[0];
			$oneDivision['bracket'] = GeneralUtils::parseInt($type);
			
			// Get division rank
			$divisionNode = $firstDivisionNode->find('.divisionrank', 0);
			$divisionRank = $divisionNode->find('.number', 0)->plaintext;
			$oneDivision['rank'] = GeneralUtils::parseInt($divisionRank);
			
			// Get division name
			$oneDivision['name'] = $divisionNode->find('a', 0)->plaintext;
				
			// Get division url
			$divisionURL = RANKSURL . $divisionNode->find('a', 0)->getAttribute('href');
			$oneDivision['ranksURL'] = $divisionURL;
		}

		// Add division to one search
		$onePlayer['divisions'] = $oneDivision;
		
		// Add one result to search content
		$jsonArray = array();
		$jsonArray['pages'] = 1;
		$players = array();
		$players[] = $onePlayer;
		$jsonArray['players'] = $players;
		return $jsonArray;
	}

	protected function adjustForLeague($players)
	{
		$league = $this->league;
		if ( !$league || $league == 'none' ) {
			return $players;
		}

		# Rearrange of array according to leauge compatibility
		$bucketMapper = array(
			'grandmaster' => 1,
			'master' => 2,
			'diamond' => 3,
			'platinum' => 4,
			'gold' => 5,
			'silver' => 6,
			'bronze' => 7
		);
		$searchBuckets = array();
		$targetPoints = $bucketMapper[$league];
		for ( $i=0; $i < count($players); $i++ )
		{ 
			$oneResult = $players[$i];
			$playerLeague = $oneResult['divisions']['league'];
			$resultPoints = $bucketMapper[$playerLeague];
			$compatibility = abs($targetPoints - $resultPoints);
			$searchBuckets[$compatibility][] = $oneResult;
		}
		
		# Combine our buckets
		$adjustedArray = array();
		for ( $i=0; $i < 6; $i++ ) { 
			if ( array_key_exists($i, $searchBuckets) ) {
				foreach ( $searchBuckets[$i] as $searchResult ) {
					$adjustedArray[] = $searchResult;
				}
			}
		}
		
		return $adjustedArray;
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