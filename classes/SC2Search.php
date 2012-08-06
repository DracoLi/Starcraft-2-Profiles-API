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
	
	private $jsonData;
	private $content;
	private $dataToPrint;
	
	public function __construct($content)
	{
		if ( isset($content) ) {
			$this->content = $content;	
		}else {
			// We got nothing - this also ends page
			RestUtils::sendResponse(204);
			return;
		}
		
		$this->jsonData = json_encode($this->getRanksSearchResults());
	}
	
	public function getJsonData()
	{
		return $this->jsonData;
	}
	
	// Testing
	public function displayArray()
	{
		$this->addThingsToPrint('<pre>' . print_r(json_decode($this->getJsonData()), TRUE) . '</pre>');
		
		$fullContent = RestUtils::getHTTPHeader('Testing') . $this->dataToPrint . RestUtils::getHTTPFooter(); 
		RestUtils::sendResponse(200, $fullContent);
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
				
				// Get losses
				if ( $oneDivision['league'] == 'grandmaster' || $oneDivision['league'] == 'master' ) {
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
	 * Quick function to add something to be printed
	 */
	public function addThingsToPrint($things)
	{
		$this->dataToPrint .= $things;	
	}
	
}

?>