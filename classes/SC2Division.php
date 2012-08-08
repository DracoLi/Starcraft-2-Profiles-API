<?php

require_once('global-config.php');
require_once('../helpers/helper-fns.php');
require_once('../helpers/simple_html_dom.php');
require_once('../helpers/RestUtils.php');
require_once('../helpers/URLConnect.php');

/**
 * Handles all tasks related to divisions.
 * This class can return a list of divisions if passed a BNET player url.
 * This class can also parse division content for both Ranks and BNET.
 *
 * @author Draco Li
 * @version 1.0
 */
class SC2Division {

	private $options;
	
	/**
	 * Initializes the SC2Division by assigning some options.
	 * @return void
	 */
	public function __construct($options)
	{
	  $this->options = $options;	
	}
	
	/**
	 * Parse division info, can be BNET or Ranks
	 *  Paging is enabled
	 */
  public function parseDivision()
  {
    if ( SC2Utils::isbnetURL($this->options['url']) ) {   
			$results = $this->getBNETDivisionInfo();
		}else {
			$results = $this->getRanksDivisionInfo();
		}
		
		$amount = $this->options['amount'];
		$offset = $this->options['offset'];
		return GeneralUtils::prepareForPaging($results, $offset, $amount, 'divisionInfo', 'ranks');
  }
	
	/**
	 * Retrieves a list of divisions for a BNET player
	 * @return Array An array of divisions for the player
	 */
	public function getAllDivisions()
	{
	  $divisions = $this->parseAllDivisionsPage();
		// If we want to grab the first div as well, the structure needs a little adjustment.
    // $getFirstDiv = FALSE;
    //    if ( $this->options['grabFirstDiv'] === 'true' ) {
    //      $allInfo = array();
    //  $allInfo['divisions'] = $divisions;
    //  $sc2division = new SC2Division($content, $url);
    //  $allInfo['firstDivision'] = json_decode($sc2division->getJsonData());
    //    }
	  
	  return $divisions;
	}
	
	/**
	 * Parse information from the division showcase page
	 */
	public function getDivisionShowcase()
	{
	  $divisions = $this->parseShowcaseDivisions();
	  
	  return $divisions;
	}
	
	private function parseShowcaseDivisions()
	{
	  // Get data to be used
		$divisionsHTML = str_get_html($this->options['content']);
		$divisions = array();
		
		$leagueBaseURL = $this->options['url'];
		$regionBaseURL = GeneralUtils::getBaseURL($leagueBaseURL);
		
		$divisionNodes = $divisionsHTML->find('#ladder-spotlight .spotlight');
		foreach ( $divisionNodes as $divisionNode ) {
		  // Get data container
			$oneDivision = array();
			
			// Division league
			$league = $divisionNode->find('.emblem span', 0)->getAttribute('class');
			$startpos = strpos($league, 'badge-') + strlen('badge-');
			$endpos = strrpos($league, 'badge-');
			$league = substr($league, $startpos, $endpos - $startpos);
			$league = trim($league);
			
			// Skip empties
			if ( $league === "none" ) {
			  continue;
			}
			
			$oneDivision['league'] = $league;
			
			// Division bracket
			$bracket = $divisionNode->find('.league strong', 0)->plaintext;
			$bracket = GeneralUtils::parseInt($bracket);
			$oneDivision['bracket'] = $bracket;
			
			// Division players
			$playerNodes = $divisionNode->find('.players li a');
			$players = array();
			foreach ( $playerNodes as $playerNode ) {
			  $onePlayer = array();
			  
			  // Player race
			  $race = $playerNode->getAttribute('class');
			  $startpos = strpos($race, 'race-') + strlen('race-');
			  $race = trim( substr($race, $startpos) );
			  $onePlayer['race'] = $race;
			  
			  // Player name
			  $name = $playerNode->plaintext;
			  $name = trim($name);
			  $onePlayer['name'] = $name;
			  
			  // Player bnetURL
			  $bnetURL = trim( $playerNode->getAttribute('href') );
			  $bnetURL = $regionBaseURL . $bnetURL;
			  $onePlayer['bnetURL'] = $bnetURL;
			  
			  // Player ranksURL
			  $onePlayer['ranksURL'] = SC2Utils::estimateRanksLink($bnetURL);
			  
			  $players[] = $onePlayer;
			}
			$oneDivision['players'] = $players;
			
			// Division type
			if ( count($players) > 1 ) {
			  $oneDivision['type'] = 'team';
			}else {
			  $oneDivision['type'] = 'random';
			}
			
			// Division URL
			$divisionURL = $divisionNode->find('.emblem', 0)->getAttribute('href');
			$endpos = strpos($divisionURL, '#');
			$divisionURL = substr($divisionURL, 0, $endpos);
			$divisionURL = $leagueBaseURL . $divisionURL;
			$oneDivision['bnetURL'] = $divisionURL;
			
			// Division rank
			$divisionString = $divisionNode->find('.division', 0)->plaintext;
			$divisionStats = $divisionNode->find('.division span', 0)->plaintext;
			$endpos = strpos($divisionString, $divisionStats);
			$nameAndRank = substr($divisionString, 0, $endpos);
			$rank = GeneralUtils::parseInt($nameAndRank);
			$oneDivision['rank'] = $rank;
			
			// Division name
			
			// Division wins
			$wins = GeneralUtils::parseInt($divisionStats);
			$oneDivision['wins'] = $wins;
			
			// Division losses
			$startpos = strpos($divisionStats, '/');
			if ( $startpos !== FALSE ) {
			  $losses = substr($divisionStats, $startpos);
			  $losses = GeneralUtils::parseInt($losses);
			  $oneDivision['losses'] = $losses;
			}
			
			$divisions[] = $oneDivision;
		}
		
		return $divisions;
	}
  
  private function parseAllDivisionsPage()
  {
	  // Get data to be used
		$divisionsHTML = str_get_html($this->options['content']);
		$divisions = array();
		
		// Remove the leagues from the baseURL for link construction
		$leagueBaseURL = $this->options['url'];
		$startpos = strpos($leagueBaseURL, '/leagues');
		$leagueBaseURL = substr($leagueBaseURL, 0, $startpos);
		
		$divisionNodes = $divisionsHTML->find('#profile-menu li');
		for ( $i = 2; $i < count($divisionNodes); $i++ ) {
			
			// Get data container
			$oneDivision = array();
			$divisionNode = $divisionNodes[$i]->find('a', 0);
			
			// Get division bracket
			$fullwords = $divisionNode->plaintext;
			preg_match('/[1-4]/', $fullwords, $matches);
			$bracket = GeneralUtils::parseInt($matches[0]);
			$oneDivision['bracket'] = $bracket;
			
			// Try get division type - random or normal. for bracket > 1 only
			$divisionID = $divisionNode->getAttribute('data-tooltip', 0);
			$targetNode = $divisionsHTML->find("#profile-right $divisionID .ladder-tooltip", 0);
			$fullwords = $targetNode->plaintext;
			$rankingsWords = $targetNode->find('strong', 0)->plaintext;
			$startpos = strpos($fullwords, $rankingsWords) + strlen($rankingsWords);
			$allNames = substr($fullwords, $startpos);
			$allNames = trim($allNames);
			if ( $oneDivision['bracket'] > 1 ) {
				preg_match_all('/,/', $allNames, $matches);
				$totalPlayers = count($matches[0]) + 1;
				if ( $totalPlayers > 1 ) {
					$oneDivision['type'] = 	'team';
				}else {
					$oneDivision['type'] = 	'random';
				}
			}else {
				$totalPlayers = 1;
			}
			
			// Get division players
			$players = array();
			for ( $j = 0; $j < $totalPlayers; $j++ ) {
				
				$onePlayer = array();
				
				// Get player name, we only have this data from bnet
				if ( $totalPlayers == 1 ) {
					$onePlayer['name'] = $allNames;
					$players[] = $onePlayer;
					break;
				}
				
				if ( $j == 0 ) {
					$startpos = 0;
					$endpos = strpos($allNames, ',');
					$onePlayer['name'] = trim(substr($allNames, $startpos, $endpos));
				}else if ( $j + 1 == $totalPlayers ) {
					$onePlayer['name'] = trim(substr($allNames, $endpos + 1));
				}else {
					$startpos = $endpos + 1;
					$endpos = strpos($allNames, ',', $startpos);
					$onePlayer['name'] = trim(substr($allNames, $startpos, ($endpos - $startpos)));
				}
				
				$players[] = $onePlayer;
			}
			$oneDivision['players'] = $players;
			
			// Get division league
			$classWords = $targetNode->find('.badge', 0)->getAttribute('class');
			$startpos = strpos($classWords, 'badge-') + strlen('badge-');
			$endpos = strpos($classWords, ' ', $startpos);
			$league = substr($classWords, $startpos, ($endpos - $startpos));
			$oneDivision['league'] = strtolower(trim($league));
			
			// Get division rank
			$rank = $targetNode->find('strong', 0)->plaintext;
			preg_match('/\d+/', $rank, $matches);
			$rank = $matches[0];
			$oneDivision['rank'] = GeneralUtils::parseInt($rank);
			
			// Get division url
			$bnetLink = $divisionNode->getAttribute('href');
			$bnetLink = $leagueBaseURL . '/' . $bnetLink;
			$startpos = strpos($bnetLink, '#');
			$bnetLink = substr($bnetLink, 0, $startpos);
			$oneDivision['bnetLink'] = $bnetLink;
			
			$divisions[] = $oneDivision;
		}
		
		return $divisions;
  }
  
	/**
	 * Parses BNET division content
	 * @return Array the parsed json content in an array
	 */
	private function getBNETDivisionInfo()
	{
	  set_time_limit(60*2);
	  
		// Get data
		$divisionHTML = str_get_html($this->options['content']);
		$divisionData = array();
		$userBaseURL = GeneralUtils::getBaseURL($this->options['url']);
		
		// Get division name
		$nameNode = $divisionHTML->find('.data-label span', 1);
		if ( $nameNode ) {
			$name = $nameNode->plaintext;
			$divisionData['name'] = trim($name);
		}else {
			// Currently only Grandmaster doesn't have a division name
			$divisionData['name'] = "Grandmaster";	
		}
		
		// Get division league
		$fullwords = $divisionHTML->find('.badge-banner span', 0)->getAttribute('class');
		$startpos = strpos($fullwords, 'badge-') + 6;
		$endpos = strpos($fullwords, ' ', $startpos);
		$league = substr($fullwords, $startpos, ($endpos - $startpos));
		$divisionData['league'] = strtolower($league);
		
		// Get division region
		$divisionData['region'] = SC2Utils::playerRegionFromBnetURL($this->options['url']);
		
		// Get division bracket
		$divider = $divisionHTML->find('.data-label span', 0)->plaintext;
		$fullwords = $divisionHTML->find('.data-label', 0)->plaintext;
		$startpos = strpos($fullwords, $divider) + strlen($divider);
		$fullwords = substr($fullwords, $startpos);
		preg_match('/[0-4]/', $fullwords, $matches);
		$bracket = $matches[0];
		$divisionData['bracket'] = GeneralUtils::parseInt($bracket);
		
		// Try to get division type - random or team - for bracket > 1
		if ( $divisionData['bracket'] > 1 ) {
			// Find out total players
			$firstRow = $divisionHTML->find('table tr', 1);
			$totalColumns = count($firstRow->find('td'));
			$bannerAdjustment = $firstRow->find('.banner', 0) ? 1 : 0;
			if ( $divisionData['league'] == 'grandmaster' || $divisionData['league'] == 'master' )
			{
				$minColumns = 6 + $bannerAdjustment;
			}else {
				$minColumns = 5 + $bannerAdjustment;
			}
			
			if ( $totalColumns > $minColumns ) {
				// This is definately not random
				$divisionData['type'] = 'team';	
			}else {
				$divisionData['type'] = 'random';	
			}
		}else {
		  $divisionData['type'] = 'random';	
		}
		
		// Get user rank
		$currentRankNode = $divisionHTML->find('table tr#current-rank', 0);
		$tempAdjustment = $currentRankNode->find('.banner', 0) ? 1 : 0;
		$userRank = $currentRankNode->find('td', 1 + $tempAdjustment)->plaintext;
		preg_match('/\d+/', $userRank, $matches);
		$userRank = $matches[0];
		$divisionData['userRank'] = GeneralUtils::parseInt($userRank);
		
		// Setup league adjustment
		$bracketAdjustment = ($divisionData['type'] == 'team') ? $divisionData['bracket'] : 1;
		
		// Get division rankings
		$currentRow = 0;
		$rankings = array();
		$rankingNodes = $divisionHTML->find('table tr');
		foreach ( $rankingNodes as $rankingNode ) {
			
			// Skip the first row, which is only the header
			if ( $currentRow == 0 ) {
				$currentRow++;
				continue;
			}
			
			$oneRank = array();
			
			// Set up adjustment
			$bannerAdjustment = $rankingNode->find('.banner', 0) ? 1 : 0;
			
			$playerDivision = array();
			{
			  // Get joined date
  			$joinedDate = $rankingNode->find('td', 0 + $bannerAdjustment)->getAttribute('data-tooltip');
  			$playerDivision['joinedDate'] = SC2Utils::joinedDateToTimeStamp($joinedDate, $this->options['url']);
        // $playerDivision['joinedDate'] = GeneralUtils::timeStampToDate($playerDivision['joinedDate']);

  			// Get rank
  			$rank = $rankingNode->find('td', 1 + $bannerAdjustment)->plaintext;
  			preg_match('/(\d+)/', $rank, $matches);
  			$rank = $matches[1];
  			$playerDivision['rank'] = GeneralUtils::parseInt($rank);

  			// Get prev rank
  			$nameNode = $rankingNode->find('td', 2 + $bannerAdjustment);
  			$tooltipDiv = $nameNode->find('a', 0)->getAttribute('data-tooltip');
  			$fullwords = $nameNode->find("$tooltipDiv", 0)->plaintext;
  			$prevWords = $nameNode->find('strong', 0)->plaintext;
  			$nextWords = $nameNode->find('strong', 1)->plaintext;
  			$startpos = strpos($fullwords, $prevWords) + strlen($prevWords);
  			$endpos = strpos($fullwords, $nextWords);
  			$prevRank = substr($fullwords, $startpos, ($endpos - $startpos));
  			$playerDivision['prevRank'] = GeneralUtils::parseInt($prevRank);

  			// Get points
  			$points = $rankingNode->find('td', 2 + $bannerAdjustment + $bracketAdjustment)->plaintext;
  			$playerDivision['points'] = GeneralUtils::parseInt($points);

  			// Get wins
  			$wins = $rankingNode->find('td', 3 + $bannerAdjustment + $bracketAdjustment)->plaintext;
  			$wins = GeneralUtils::parseInt($wins);
  			$playerDivision['wins'] = $wins;

  			// Get losses if exists
  			$lossesNode = $rankingNode->find('td', 4 + $bannerAdjustment + $bracketAdjustment);
  			if ( $lossesNode ) {
  				$losses = GeneralUtils::parseInt($lossesNode->plaintext);
  				$playerDivision['losses'] = $losses;

  				$total = $losses + $wins;
  				if ( $total > 0 ) {
  				  $playerDivision['winRatio'] = (float)($wins / $total);
  				}
  			} 
			}
			
			// Set player division
			$oneRank['division'] = $playerDivision;
			$oneRank['rank'] = $playerDivision['rank'];
			
			// Get characters - name and link and estimated bnet link
			$players = array();
			for ( $i = 0; $i < $bracketAdjustment; $i++ ) {
				
				// Get data
				$onePlayer = array();	
				$charNode = $rankingNode->find('td', 2 + $bannerAdjustment + $i);
				
				// Get char race
				$race = $charNode->find('a', 0)->getAttribute('class');
				$startpos = strpos($race, 'race-') + strlen('race-');
				$race = substr($race, $startpos);
				$onePlayer['race'] = strtolower(trim($race));
				
				// Get char name
				$name = $charNode->find('a', 0)->plaintext;
				$onePlayer['name'] = trim($name);
				
				// Get char bnet link
				$bnetURL = $charNode->find('a', 0)->getAttribute('href');
				$bnetURL = $userBaseURL . $bnetURL;
				$bnetURL = GeneralUtils::encodeURL($bnetURL);
				
				$onePlayer['bnetURL'] = $bnetURL;
				
				// Estimate char ranks link
				$onePlayer['ranksURL'] = SC2Utils::estimateRanksLink($bnetURL);
				
				$players[] = $onePlayer;
			}
			$oneRank['players'] = $players;
			
			$rankings[] = $oneRank;
			
		}
		$divisionData['ranks'] = $rankings;
    
		return $divisionData;
	}
	
	/**
	 * Parses Ranks division content
	 * @return Array the parsed json content in an array
	 */
	protected function getRanksDivisionInfo()
	{	
		// Get data
		$divisionHTML = str_get_html($this->options['content']);
		$divisionData = array();
		
		// Get division name
		$bracket = $divisionHTML->find('.divisioninfo .bracket', 0)->plaintext;
		$fullwords = $divisionHTML->find('.divisioninfo', 0)->plaintext;
		$startpos = strpos($fullwords, $bracket) + strlen($bracket);
		$endpos = strpos($fullwords, '(', $startpos);
		$name = substr($fullwords, $startpos, ($endpos - $startpos));
		$divisionData['name'] = trim($name);
		
		// Get division league
		$league = $divisionHTML->find('.divisioninfo img', 0)->getAttribute('alt');
		$endpos = strpos($league, '-');
		$league = substr($league, 0, $endpos);
		$divisionData['league'] = strtolower(trim($league));
		
		// Get division bracket
		preg_match('/[1-4]/', $bracket, $matches);
		$bracket = $matches[0];
		$divisionData['bracket'] = GeneralUtils::parseInt($bracket);
		
		// Get division type for only when bracket > 1. Could be team or random
		if ( $divisionData['bracket'] > 1 ) {
			$headerRow = $divisionHTML->find('table tr', 0);
			$numChars = count($headerRow->find('.character'));
			$divisionData['type'] = ($numChars > 1) ? 'team' : 'random';
		}else {
		  $divisionData['type'] = 'random';
		}
		
		// Get division rankings
		$currentRow = 0;
		$rankings = array();
		$rankingNodes = $divisionHTML->find('table tr');
		foreach ( $rankingNodes as $rankingNode ) {
			
			// Skip the first row, which is only the header
			if ( $currentRow == 0 ) {
				$currentRow++;
				continue;
			}
			
			$oneRank = array();
			
			// Get rank
			$rank = $rankingNode->find('.rank', 0)->plaintext;
			$oneRank['rank'] = GeneralUtils::parseInt($rank);
			
			// Get points
			$points = $rankingNode->find('.points', 0)->plaintext;
			$oneRank['points'] = GeneralUtils::parseInt($points);
			
			// Get wins - 4v4 does not have wins or losses
			if ( $divisionData['bracket'] < 4 ) {
				$wins = $rankingNode->find('.wins', 0)->plaintext;
				$oneRank['wins'] = GeneralUtils::parseInt($wins);
			}else if ( $divisionData['league'] == 'master' && $divisionData['bracket'] == 4 ) {
				// Get win ratio for 4v4 masters - best data we have
				$oneRank['winRatio'] = floatval($rankingNode->find('.ratio', 0)->plaintext) / 100;
			}
			
			// Get losses if exists
			$lossesNode = $rankingNode->find('.losses', 0);
			if ( $lossesNode ) {
				$losses = $lossesNode->plaintext;
				$oneRank['losses'] = GeneralUtils::parseInt($losses);
			}
			
			// Get characters - name and link and estimated bnet link
			$players = array();
			$charNodes = $rankingNode->find('.character');
			foreach ( $charNodes as $charNode ) {
				
				$onePlayer = array();
				
				// Get char race
				$race = $charNode->find('img', 0)->getAttribute('class');
				$onePlayer['race'] = strtolower(trim($race));
				
				// Get char name
				$name = $charNode->find('a', 0)->plaintext;
				$onePlayer['name'] = $name;
				
				// Get char ranks link
				$url = $charNode->find('a', 0)->getAttribute('href');
				$url = RANKSURL . $url;
				$onePlayer['ranksURL'] = $url;
				
				// Get char estimate bnet link
				$onePlayer['bnetURL'] = SC2Utils::estimateBLink($onePlayer['ranksURL']);
				
				$players[] = $onePlayer;
			}
			$oneRank['players'] = $players;
			
			$rankings[] = $oneRank;	
		}
		
		return $rankings;
	}
}

?>