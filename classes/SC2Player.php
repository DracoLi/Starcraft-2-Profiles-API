<?php

require_once('global-config.php');
require_once('../helpers/helper-fns.php');
require_once('../helpers/simple_html_dom.php');
require_once('../helpers/RestUtils.php');
require_once('../helpers/URLConnect.php');
require_once('SC2Achievements.php');

/**
 * Return all general info related to a starcraft 2 player.
 * This script can take info from both bnet and sc2ranks depending on the url.
 *
 * @author Draco Li
 * @version 1.0
 */
class SC2Player {
		
	private $jsonData;
	private $content;
	private $dataToPrint;
	private $playerURL;
	
	/**
	 * Initializes the SC2Player by assigning the content to parse and the url of the content. Then perfrom the parse.
	 * If no content is received, we send out an error page
	 * @param $content the content of parse
	 * @param $url the url of the content, used to form full links
	 * @return void
	 */
	public function __construct($content, $url = RANKSURL )
	{
		if ( isset($content) ) {
			$this->content = $content;
			$this->playerURL = $url;
		}else {
			// We got nothing - this also ends page
			RestUtils::sendResponse(204);
			return;
		}
		
		if ( SC2Utils::isbnetURL($this->playerURL) ) {
			$this->jsonData = json_encode($this->getBNETPlayerInfo());	
		}else {
			$this->jsonData = json_encode($this->getRanksPlayerInfo());	
		}
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
		$this->addThingsToPrint("<h2><a href=\"". $this->playerURL . "\">" . $this->playerURL . "</a></h2>");
		$this->addThingsToPrint('<pre>' . print_r(json_decode($this->getJsonData()), TRUE) . '</pre>');
		
		$fullContent = RestUtils::getHTTPHeader('Testing') . $this->dataToPrint . RestUtils::getHTTPFooter(); 
		RestUtils::sendResponse(200, $fullContent);
	}
	
	/**
	 * Parses a Ranks player profile. This includes many info that BNET does not have such as region, world rankings, etc.
	 * @return Array
	 */
	private function getRanksPlayerInfo()
	{
		$jsonArray = array();
		$playerHTML = str_get_html($this->content)->find('.charprofile', 0);
		
		// Get profileImage
		$profileImage = array();
		{
			// Get image url
			$profileStyle = $playerHTML->find('.portrait span', 0)->getAttribute('style');
			$startpos = strpos($profileStyle, '("') + 2;
			$endpos = strpos($profileStyle, '")', $startpos);
			$imageURL = RANKSURL . substr($profileStyle, $startpos, ($endpos - $startpos));
			$profileImage['url'] = $imageURL;
			
			// Get image x
			$startpos = strpos($profileStyle, 'scroll') + 6;
			$endpos = strpos($profileStyle, 'px', $startpos);
			$xValue = substr($profileStyle, $startpos, ($endpos - $startpos));
			$profileImage['x'] = GeneralUtils::parseInt($xValue);
			
			// Get image y
			$startpos = $endpos + 2;
			$endpos = strpos($profileStyle, 'px', $startpos);
			$yValue = substr($profileStyle, $startpos, ($endpos - $startpos));
			$profileImage['y'] = GeneralUtils::parseInt($yValue);
			
			// Get image width
			$startpos = strpos($profileStyle, 'width:') + 6;
			$endpos = strpos($profileStyle, 'px', $startpos);
			$width = substr($profileStyle, $startpos, ($endpos - $startpos));
			$profileImage['width'] = GeneralUtils::parseInt($width);
			
			// Get image height
			$startpos = strpos($profileStyle, 'height:') + 7;
			$endpos = strpos($profileStyle, 'px', $startpos);
			$width = substr($profileStyle, $startpos, ($endpos - $startpos));
			$profileImage['height'] = GeneralUtils::parseInt($width);
		}
		$jsonArray['image'] = $profileImage;
		
		// Get name	
		$nameNode = $playerHTML->find('.profile .name', 0);
		$jsonArray['name'] = $nameNode->find('a', 0)->plaintext;
		
		// Get bnetURL
		$jsonArray['bnetURL'] = $nameNode->find('a', 0)->getAttribute('href');
		
		// Get region
		$jsonArray['region'] = SC2Utils::playerRegionFromBnetURL($jsonArray['bnetURL']);
		
		// Get historyURL
		$historyURL = RANKSURL . $playerHTML->find('.profile .maps a', 0)->getAttribute('href');
		$jsonArray['historyURL'] = $historyURL;
		
		// Get achivement points
		$achivementPoints = $playerHTML->find('.profile .achievements span', 0)->plaintext;
		$jsonArray['achivementPoints'] = GeneralUtils::parseInt($achivementPoints);
		
		// Get division data now
		$divisions = array();
		$divisionsNode = $playerHTML->find('.leagues');
		foreach ( $divisionsNode as $oneNode ) {
			
			// Get division bracket
			$bracket = trim($oneNode->find('.headertext', 0)->plaintext);
			preg_match('/\d+/', $bracket, $matches);
			$bracket = GeneralUtils::parseInt($matches[0]);
			
			// Get number of divisions for this division type
			$divisionsForType = count($oneNode->find('td.badge'));
			
			for ( $i = 0; $i < $divisionsForType; $i++ ) {
				
				$oneDivision = array();
				$oneDivision['bracket'] = $bracket;
				
				// Get league
				$badgeClass = $oneNode->find('td.badge span', $i)->getAttribute('class');
				$startpos = strpos($badgeClass, '-') + 1;
				$endpos = strpos($badgeClass, ' ', $startpos);
				$league = substr($badgeClass, $startpos, ($endpos - $startpos));
				$oneDivision['league'] = strtolower($league);
				
				// Get the type of the divison. Team or random
				if ( $oneDivision['bracket'] != 1 ) {
					$type = $oneNode->find('td.bracket', $i)->plaintext;
					$oneDivision['type'] = strtolower($type);	
				}
				
				// Get world rankings if exists
				$worldRankNode = $oneNode->find('td.worldrank', $i);
				$worldRankNode = $worldRankNode->find('.number', 0);
				if ( $worldRankNode ) {
					$worldRank = $worldRankNode->plaintext;
					$oneDivision['worldRank'] = GeneralUtils::parseInt($worldRank);
				}
				
				// Get region rankings if exists
				$regionRankNode = $oneNode->find('td.regionrank', $i);
				$regionRankNode = $regionRankNode->find('.number', 0);
				if ( $regionRankNode ) {
					$regionRank = $regionRankNode->plaintext;
					$oneDivision['regionRank'] = GeneralUtils::parseInt($regionRank);
				}
				
				// Get division points
				$summaryNode = $oneNode->find('td.summary', $i);
				
				$points = $summaryNode->find('.number', 0)->plaintext;
				$oneDivision['points'] = GeneralUtils::parseInt($points);
					
				// Get division wins
				$wins = $summaryNode->find('.green', 0)->plaintext;
				$oneDivision['wins'] = GeneralUtils::parseInt($wins);
				
				// Get division losses
				if ( $oneDivision['league'] == 'grandmaster' || $oneDivision['league'] == 'master' ) {
					$lossesNode = $summaryNode->find('.red', 0);
					if ( $lossesNode ) {
						$losses = $lossesNode->plaintext;
						$oneDivision['losses'] = GeneralUtils::parseInt($losses);
					}
				}
				
				// get division name
				$divisionGeneral = $oneNode->find('.divisionrank', $i);
				
				$name = $divisionGeneral->find('a', 0)->plaintext;
				$oneDivision['name'] = $name;
				
				// get division url
				$divisionURL = $divisionGeneral->find('a', 0)->getAttribute('href');
				$oneDivision['url'] = RANKSURL . $divisionURL;
				
				// get division rank
				$rank = $divisionGeneral->find('.number', 0)->plaintext;
				$oneDivision['rank'] = GeneralUtils::parseInt($rank);
				
				// Get division players
				$players = array();
				{
					// Get number of players for this division
					if ( $oneDivision['type'] == 'team' ) {
						$numPlayers = $oneDivision['bracket'];
					}else {
						$numPlayers = 1;
					}
					
					for ( $j = 0; $j < $numPlayers; $j++ ) {
						
						// Create our data
						$onePlayer = array();
						$targetIndex = 4 * $i + $j;
						$playerNode = $oneNode->find('.character', $targetIndex);
						
						// Get player race
						$playerRace = $playerNode->find('img', 0)->getAttribute('class');
						$onePlayer['race'] = strtolower($playerRace);
						
						// Get player name
						$onePlayer['name'] = trim($playerNode->plaintext);
						
						// Get player url and determine if this player is someone else
						$playerGeneral = $playerNode->find('a', 0);
						if ( $playerGeneral ) {
							// Get player url
							$playerURL = $playerGeneral->getAttribute('href');
							$onePlayer['ranksURL'] = RANKSURL . $playerURL;
							
							// Estimate the other player's bnet link
							$onePlayer['bnetURL'] = SC2Utils::estimateBLink($onePlayer['ranksURL']);
						}else {
							// This player is us, we estimate this player's fav race here. 1v1 has precedence over 2v2, 2v2 over 3v3 etc.
							if ( !isset($jsonArray['race']) ) {
								$jsonArray['race'] = $onePlayer['race'];	
							}
						}
						
						// Add one player to players
						$players[] = $onePlayer;
					
					} // All players added for this division
				}
				// Add players to this division
				$oneDivision['players'] = $players;
				
				// Add this division
				$divisions[] = $oneDivision;
			}
		}
		
		// Add user's divisions
		$jsonArray['divisions'] = $divisions;
		
		
    
		// Finish sc2ranks profile for player
		return $jsonArray;
	}
	
	/**
	 * Parses a BNET player profile. This includes aditional info such as career stats that Ranks do not have
	 * @return Array
	 */
	private function getBNETPlayerInfo()
	{
		$userBaseURL = GeneralUtils::getBaseURL($this->playerURL);
		
		// Get data
		$playerHTML = str_get_html($this->content)->find('#profile-wrapper', 0);
		$jsonArray = array();
		
		// Get profileImage
		$imageStyle = $playerHTML->find('#profile-header #portrait span', 0)->getAttribute('style');
		$jsonArray['image'] = SC2Utils::parseBnetImageInfo($imageStyle, $userBaseURL);
		
		// Get name
		$jsonArray['name'] = $playerHTML->find('#profile-header h2 a', 0)->plaintext;
		
		// Save bnetURL
		
		// This work around to get the current BNET url is here else we get messed-up
		// results on our client because somehow the chinese characters are encoded twice.
		$bnetURL = $playerHTML->find('#profile-menu li a', 0)->getAttribute('href');
		$bnetURL = GeneralUtils::getBaseURL($this->playerURL) . $bnetURL;
		$bnetURL = GeneralUtils::encodeURL($bnetURL);
		
		$jsonArray['bnetURL'] = $bnetURL;
			
		// Get estimated ranks url
		$jsonArray['ranksURL'] = SC2Utils::estimateRanksLink($bnetURL);
		
		// Save history url
		$jsonArray['historyURL'] = $bnetURL . "matches";
		
		// Save ladder's url
		$jsonArray['leaguesURL'] = $bnetURL . "ladder/leagues";
		
		// Save ladder's showcase url
		$jsonArray['leaguesShowcaseURL'] = $bnetURL . "ladder/";
		
		// Save achievements urls
		$achieve = new SC2Achievements(NULL, $jsonArray['bnetURL']);
		$achievementsArray = $achieve->getAllAchievementLinks();
		$jsonArray['achievementsURL'] = $achievementsArray;
		
		// Get region
		$jsonArray['region'] = SC2Utils::playerRegionFromBnetURL($jsonArray['bnetURL']);
		
		// Get achivement points
		$achivementPoints = $playerHTML->find('#profile-header h3', 0)->plaintext;
		$jsonArray['achivementPoints'] = GeneralUtils::parseInt($achivementPoints);
		
		// Season stats
		{
			$seasonStats = $playerHTML->find('#season-snapshot', 0);
			
			// Get games tis season
			$gamesThisSeason = $seasonStats->find('.stat-block h2', 0)->plaintext;
			$jsonArray['gamesThisSeason'] = GeneralUtils::parseInt($gamesThisSeason);
				
			// Most played mode
			$mostPlayedMode = $seasonStats->find('.stat-block h2', 1)->plaintext;
			$jsonArray['mostPlayedMode'] = GeneralUtils::parseInt($mostPlayedMode);
			
			// Total career games
			$careerGames = $seasonStats->find('.stat-block h2', 2)->plaintext;
			$jsonArray['careerGames'] = GeneralUtils::parseInt($careerGames);
			
			// Most played race
			$mostPlayedRace = $seasonStats->find('.stat-block h2', 3)->plaintext;
			$mostPlayedRaceKey = $seasonStats->find('.module-body', 0)->getAttribute('class');
			$startpos = strpos($mostPlayedRaceKey, 'snapshot-') + strlen('snapshot-');
			$mostPlayedRaceKey = trim(substr($mostPlayedRaceKey, $startpos));
			$jsonArray['mostPlayedRaceString'] = $mostPlayedRace;
			$jsonArray['mostPlayedRaceKey'] = $mostPlayedRaceKey;
			
		}
		
		// Career stats
		{
		  $careerStats = $playerHTML->find('#career-stats', 0);
		  
		  // For solo
		  $bestSolo = $careerStats->find('#best-finish-SOLO', 0);

		  $soloString = $bestSolo->plaintext;
		  $careerWords = $bestSolo->find('strong', 0)->plaintext;
		  $timesWords = $bestSolo->find('strong', 1)->plaintext;
		  $leagueWords = $bestSolo->find('strong', 2)->plaintext;
		  
		  // Solo league key and rank
		  $bestSoloLeague = $careerStats->find('.badge-item', 0);
		  $bestSoloLeague = $bestSoloLeague->find('.badge span', 0)->getAttribute('class');
		  $startpos = strpos($bestSoloLeague, 'badge-') + strlen('badge-');
		  $soloLeague = substr($bestSoloLeague, $startpos);
		  $endpos = strpos($soloLeague, 'badge-') - 1;
		  $soloLeagueKey = trim( substr($soloLeague, 0, $endpos) );
		  $jsonArray['bestSoloLeagueKey'] = $soloLeagueKey;
		  
		  if ( $soloLeagueKey !== 'none' )
		  {
		    $badgeRank = GeneralUtils::parseInt($bestSoloLeague);
		    $jsonArray['bestSoloBadgeImageRank'] = $badgeRank;

  		  // Team league string
  		  $startpos = strpos($soloString, $careerWords) + strlen($careerWords);
  		  $endpos = strpos($soloString, $timesWords);
  		  $soloLeagueValue = trim(substr($soloString, $startpos, $endpos - $startpos));
  		  $jsonArray['bestSoloLeagueString'] = $soloLeagueValue;

  		  // Team times achieved
  		  $startpos = $endpos + strlen($timesWords);
  		  $endpos = strpos($soloString, $leagueWords);
  		  $timesAchieved = substr($soloString, $startpos, $endpos - $startpos);
  		  $timesAchieved = GeneralUtils::parseInt($timesAchieved);
  		  $jsonArray['bestSoloTimesAchieved'] = $timesAchieved;

  		  // Team current league
  		  $startpos = $endpos + strlen($leagueWords);
  		  $soloTeamLeague = trim(substr($soloString, $startpos));
  		  $jsonArray['currentSoloLeagueString'] = $soloTeamLeague;
		  }
		  
		  
		  // For team
		  $bestTeam = $careerStats->find('#best-finish-TEAM', 0);

		  $teamString = $bestTeam->plaintext;
		  $careerWords = $bestTeam->find('strong', 0)->plaintext;
		  $timesWords = $bestTeam->find('strong', 1)->plaintext;
		  $leagueWords = $bestTeam->find('strong', 2)->plaintext;
		  
		  // Team league key and rank
		  $bestTeamLeague = $careerStats->find('.badge-item', 1);
		  $bestTeamLeague = $bestTeamLeague->find('.badge span', 0)->getAttribute('class');
		  $startpos = strpos($bestTeamLeague, 'badge-') + strlen('badge-');
		  $teamLeague = substr($bestTeamLeague, $startpos);
		  $endpos = strpos($teamLeague, 'badge-') - 1;
		  $teamLeagueKey = trim( substr($teamLeague, 0, $endpos) );
		  $jsonArray['bestTeamLeagueKey'] = $teamLeagueKey;
		  if ( $teamLeagueKey !== "none" )
		  {
		    $badgeRank = GeneralUtils::parseInt($bestTeamLeague);
  		  $jsonArray['bestTeamBadgeImageRank'] = $badgeRank;

  		  // Team league string
  		  $startpos = strpos($teamString, $careerWords) + strlen($careerWords);
  		  $endpos = strpos($teamString, $timesWords);
  		  $teamLeagueValue = trim(substr($teamString, $startpos, $endpos - $startpos));
  		  $jsonArray['bestTeamLeagueString'] = $teamLeagueValue;

  		  // Team times achieved
  		  $startpos = $endpos + strlen($timesWords);
  		  $endpos = strpos($teamString, $leagueWords);
  		  $timesAchieved = substr($teamString, $startpos, $endpos - $startpos);
  		  $timesAchieved = GeneralUtils::parseInt($timesAchieved);
  		  $jsonArray['bestTeamTimesAchieved'] = $timesAchieved;

  		  // Team current league
  		  $startpos = $endpos + strlen($leagueWords);
  		  $currentTeamLeague = trim(substr($teamString, $startpos));
  		  $jsonArray['currentTeamLeagueString'] = $currentTeamLeague;
		  }
		  
		  // Campaign
		  $badgeNode = $careerStats->find('.campaign', 0);
		  $badgeClass = $badgeNode->find('.badge', 0)->getAttribute('class');
		  $startpos = strpos($badgeClass, 'badge') + strlen('badge');
		  $campaignKey = trim(substr($badgeClass, $startpos));
		  $campaignString = trim($badgeNode->find('.rank', 0)->plaintext);
		  $jsonArray['campaignKey'] = $campaignKey;
		  $jsonArray['campaignString'] = $campaignString;
		}
		
		// // Add user's divisions
		//    $divisions = array();
		//    $divisionsNode = $playerHTML->find('.module-body .snapshot');
		//    foreach ( $divisionsNode as $divisionNode ) {
		//      
		//      $divClass = $divisionNode->getAttribute('class');
		//      if ( strpos($divClass, 'empty-season') !== FALSE ) {
		//        // If this onedivion is empty, continue to next
		//        continue;
		//      }
		//      
		//      $oneDivision = array();
		//      
		//      // Get division league
		//      $badge = $divisionNode->find('a .badge', 0)->getAttribute('class');
		//      $startpos = strpos($badge, 'badge-') + strlen('badge-');
		//      $endpos = strpos($badge, ' ', $startpos);
		//      $league = substr($badge, $startpos, ($endpos - $startpos));
		//      $oneDivision['league'] = strtolower($league);
		//      
		//      // Get division bracket
		//      $divisionID = $divisionNode->find('.ladder', 0)->getAttribute('data-tooltip');
		//      preg_match('/\d/', $divisionID, $matches);
		//      $oneDivision['bracket'] = GeneralUtils::parseInt($matches[0]);
		//      
		//      // Get division name
		//      $fullWords = $divisionNode->find("$divisionID div", 1)->plaintext;
		//      $nameLabel = $divisionNode->find("$divisionID div strong", 0)->plaintext;
		//      $rankLabel = $divisionNode->find("$divisionID div strong", 1)->plaintext;
		//      if ( $oneDivision['league'] != 'grandmaster' ) {
		//        $startpos = strpos($fullWords, $nameLabel) + strlen($nameLabel);
		//        $endpos = strpos($fullWords, $rankLabel, $startpos);
		//        $divisionName = substr($fullWords, $startpos, ($endpos - $startpos));
		//        $oneDivision['name'] = trim($divisionName);
		//      }else {
		//        $oneDivision['name'] = "Grandmaster";
		//      }
		//      
		//      // Get division url
		//      $divisionURL = $divisionNode->find('.ladder a', 0)->getAttribute('href');
		//      $divisionURL = $userBaseURL . $divisionURL;
		//      $oneDivision['url'] = $divisionURL;
		//      
		//      // Get division rank
		//      $startpos = strpos($fullWords, $rankLabel) + strlen($rankLabel);
		//      $divisionRank = substr($fullWords, $startpos);
		//      $oneDivision['rank'] = GeneralUtils::parseInt($divisionRank);
		//  
		//      // Get division wins
		//      $theNodes = $divisionNode->find('.graph-bars .totals');
		//      if ( count($theNodes) > 1 ) {
		//        // Losses exists
		//        $totalGames = $divisionNode->find('.graph-bars .totals', 0)->plaintext;
		//        $totalGames = GeneralUtils::parseInt($totalGames);
		//        $wins = $divisionNode->find('.graph-bars .totals', 1)->plaintext;
		//        $wins = GeneralUtils::parseInt($wins);
		//        $losses = $totalGames - $wins;
		//        
		//        $oneDivision['wins'] = $wins;
		//        $oneDivision['losses'] = $losses;
		//      }else {
		//        // Only wins
		//        $wins = $divisionNode->find('.graph-bars .totals', 0)->plaintext;
		//        $wins = GeneralUtils::parseInt($wins);
		//        $oneDivision['wins'] = $wins;
		//      }
		//      
		//      $divisions[] = $oneDivision;
		//    }
		//    
		//    $jsonArray['divisions'] = $divisions;
		
		// Finish bnet profile for player
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