<?php

require_once('global-config.php');
require_once('../helpers/helper-fns.php');
require_once('../helpers/simple_html_dom.php');
require_once('../helpers/RestUtils.php');
require_once('../helpers/URLConnect.php');

/**
 * Handles all tasks related to getting histories. It can parse both ranks content and bnet history contnet
 * This class can return the url of the hisotry page if passed a profile link (bnet or ranks).
 * Users can then use this url link to download the page content. Which it then needs this class to parse.
 *
 * @author Draco Li
 * @version 1.0
 */
class SC2History {

	private $jsonData;
	private $content;
	private $contentURL;
	private $dataToPrint;
	
	/**
	 * Initializes SC2Hisotry by assigning the content to parse. Then perfrom the parse.
	 * If no content is received, we send out an error page.
	 * @param $content the content to parse
	 * @param $url The url of the content to be parsed (the history link url). Used to form links and determining which parsing method to use.
	 * @return void
	 */
	public function __construct($content, $url = RANKSURL)
	{
		if ( isset($content) ) {
			
			$this->content = $content;
			$this->contentURL = $url;
		}else {
			// We got nothing - this also ends page
			RestUtils::sendResponse(204);
			exit;
		}
		
		if ( SC2Utils::isbnetURL($this->contentURL) ) {
			$this->jsonData = json_encode($this->getBNETHistory());
		}else {
			$this->jsonData = json_encode($this->getRanksHistory());
		}
	}
	
	/**
	 * Returns bnet's history url based on profile's base url.
	 * @param url The player's base url
	 * @return String the player's history url
	 */
	public static function getBNETHistoryURL($url)
	{
		return $url . 'matches';
	}
	
	public function getJsonData()
	{
		return $this->jsonData;
	}
	
	/**
	 * Print out the json data in array format
	 * @return void
	 */
	public function displayArray()
	{
		$this->addThingsToPrint("<h2><a href=\"" . $this->contentURL . "\">" . $this->contentURL . "</a></h2>");
		$this->addThingsToPrint("<pre>" . print_r(json_decode($this->getJsonData()), TRUE) . "</pre>");
		$fullContent = RestUtils::getHTTPHeader('Testing') . $this->dataToPrint . RestUtils::getHTTPFooter(); 
		RestUtils::sendResponse(200, $fullContent);
	}
	
	/**
	 * Parse ranks content 
	 * @return array json data
	 */
	protected function getRanksHistory()
	{
		// Get data
		$historyHTML = str_get_html($this->content);
		$history = array();
		
		// Get matches data
		$matchesHTML = $historyHTML->find('table', 0);
		$history['matches'] = $this->getRanksMatches($matchesHTML);
		
		// Get map stats data
		$mapHTML = $historyHTML->find('table', 1);
		$history['mapStats'] = $this->getRanksMapStats($mapHTML);
		
		return $history;
	}
	
	/**
	 * Parses the matches history of the player
	 * @return array matches data
	 */
	protected function getRanksMatches($matchesHTML)
	{
		// Get our raw data
		$matchesNode = $matchesHTML->find('tr');
		$allMatches = array();
		
		$currentRow = 0;
		foreach ( $matchesNode as $matchNode ) {

			// Skip row 1 and 2
			if ( $currentRow < 2 ) {
				$currentRow++;
				continue;
			}
						
			$oneMatch = array();
			
			// Get map name
			$mapName = $matchNode->find('.map a', 0)->plaintext;
			$oneMatch['map'] = $mapName;
			
			// Get map bracket
			$type = $matchNode->find('.bracket', 0)->plaintext;
			$isNum = preg_match('/[1-4]/', $type, $matches);
			if ( $isNum > 0 ) {
				$type = GeneralUtils::parseInt($matches[0]);		
			}
			$oneMatch['type'] = $type;
			
			// Get map result
			$result = $matchNode->find('.results span', 0)->plaintext;
			$oneMatch['result'] = $result;
			
			// Get map points
			$pointsNode = $matchNode->find('.points', 0);
			if ( $points ) {
				$points = $pointsNode->plaintext;
				$isNegative = (strpos($points, '-') === FALSE) ? FALSE : TRUE;
				$points = GeneralUtils::parseInt($points);
				if ( $points && $points != '' ) {
					$points = $isNegative ? $points * -1 : $points;
					$oneMatch['points'] = $points;	
				}
			}
			
			// Get map date
			$date = $matchNode->find('.age', 0)->plaintext;
			$oneMatch['date'] = (int)strtotime($date);
			//$oneMatch['date'] = date("F d, Y", $oneMatch['date']);
			
			$allMatches[] = $oneMatch;
		}
		return $allMatches;
	}
	
	/**
	 * Parse the map stats of the player
	 * @return array map stats data
	 */
	protected function getRanksMapStats($mapHTML)
	{
		// Get our raw data
		$mapStatsNode = $mapHTML->find('tr');
		$mapStats = array();
		
		$currentRow = 0;
		foreach ( $mapStatsNode as $statNode ) {
			
			// Skip row 1 and 2
			if ( $currentRow < 2 ) {
				$currentRow++;
				continue;
			}
			
			$oneStat = array();
			
			// Get map name
			$oneStat['map'] = $statNode->find('.map a', 0)->plaintext;
			
			// Get map type (Blizzard or Custom)
			$fullWords = $statNode->find('.map', 0)->plaintext;
			$startpos = strpos($fullWords, '(') + 1;
			$endpos = strpos($fullWords, ')', $startpos);
			$type = substr($fullWords, $startpos, ($endpos - $startpos));
			$oneStat['type'] = $type;
			
			// Get total games
			$total = $statNode->find('.total', 0)->plaintext;
			$oneStat['total'] = GeneralUtils::parseInt($total);
			
			// Get total wins
			$wins = $statNode->find('.wins .number', 0)->plaintext;
			$oneStat['wins'] = GeneralUtils::parseInt($wins);
			
			// Get total losses
			$losses = $statNode->find('.losses .number', 0)->plaintext;
			$oneStat['losses'] = GeneralUtils::parseInt($losses);
			
			// Last played
			$lastGame = $statNode->find('.age', 0)->plaintext;
			$oneStat['lastGame'] = (int)strtotime($lastGame);
			//$oneStat['lastGame'] = date("F d, Y", $oneStat['lastGame']);
			
			$mapStats[] = $oneStat;
		}
		
		return $mapStats;
	}
	
	/**
	 * Parse bnet content 
	 * @return array json data
	 */
	protected function getBNETHistory()
	{	
		// Get contents for results
		$historyNodes = str_get_html($this->content)->find('#match-history tr');
		$histories = array();
		
		$currentRow = 0;
		foreach( $historyNodes as $historyNode ) {
			
			// Skip the first row because its the header
			if ( $currentRow == 0 ) {
				$currentRow++;
				continue;	
			}
			
			$oneHistory = array();
			
			// Get map name
			$mapName = $historyNode->find('td', 1)->plaintext;
			$oneHistory['map'] = html_entity_decode(trim($mapName), ENT_QUOTES);
			
			// Get map type - custom, 1v1, 2v2 , etc
			$type = $historyNode->find('td', 2)->plaintext;
      // $isNum = preg_match('/[1-4]/', $type, $matches);
      // if ( $isNum > 0 ) {
      //  // 1v1,2v2,3v3,4v4
      //  $type = GeneralUtils::parseInt($matches[0]);    
      // }
			$oneHistory['type'] = trim($type);
			
			// Get map outcome string
			$resultNode = $historyNode->find('td', 3);
			$resultString = $resultNode->find('span', 0)->plaintext;
			$oneHistory['outcomeString'] = trim($resultString);
			
			// Get map outcome key
			$outcomeClass = $resultNode->find('span', 0)->getAttribute('class');
			$startpos = strpos($outcomeClass, "-");
			$outcomeKey = substr($outcomeClass, $startpos + 1);
			$oneHistory['outcomeKey'] =  trim($outcomeKey);
			
			// Get map points if exists
			$pointsNode = $resultNode->find('span', 1);
			if ( $pointsNode ) {
				$points = $pointsNode->plaintext;
				$isNegative = (strpos($points, '-') === FALSE) ? FALSE : TRUE;
				$points = GeneralUtils::parseInt($points);
				if ( $points ) {
					$points = $isNegative ? $points * -1 : $points;
					$oneHistory['points'] = $points;
				}
			}
			
			// Get map date
			$date = $historyNode->find('td', 4)->plaintext;
			$oneHistory['date'] = SC2Utils::joinedDateToTimeStamp($date, $this->contentURL);
			
			$histories[] = $oneHistory;
		}
		
		return $histories;
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