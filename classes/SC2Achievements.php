<?php

require_once('global-config.php');
require_once('../helpers/helper-fns.php');
require_once('../helpers/simple_html_dom.php');
require_once('../helpers/RestUtils.php');
require_once('../helpers/URLConnect.php');

/**
 * Handles all tasks related to player achievements. It only handles parsing Bnet data
 * No support for linking achievements to awards (portrait, decal) to simplify data. 
 * Users must supply the url to parse. This means that there's chance that the url for bnet achievements changes.
 * In this case, the user will receive a 404 error. User can then optionally grab a diotionary of achievement links from this class
 *
 * @author Draco Li
 * @version 1.0
 */
class SC2Achievements {

	private $jsonData;
	private $content;
	private $contentURL;
	private $dataToPrint;
	
	/**
	 * Initializes SC2Achievements by assigning the content to parse. Then perfrom the parse on the content.
	 * If no content is received, we send out an error page.
	 * @param $content the content to parse
	 * @param $url The url of the content to be parsed (the achievement url).
	 * @return void
	 */
	public function __construct($content, $url)
	{
		if ( isset($content) ) {
			
			$this->content = $content;
			$this->contentURL = $url;
		}else {
			// We got nothing - this also ends page
			RestUtils::sendResponse(204);
			exit;
		}
		
		$this->jsonData = json_encode($this->getAchievementsData());
	}
	
	/**
	 * Return an array of achivements sections and url that can be used by user
	 * @param url The player's base url
	 * @return json the player's achievements
	 */
	public static function getAllAchievements($url)
	{
		// Construct an array of achivement sections
		$data = simplexml_load_file('achievements.xml');
		$achievementsData = SC2Achievements::parseInnerAchievements($data->children());
		return json_encode($achievementsData);
	}
	
	/**
	 * A helper recursive function that helps to parse our xml file with unknown number of drilldowns
	 * @param $data The SimpleXMLElement containing an array of data
	 * @return array The parsed array for the inner content.
	 */
	protected static function parseInnerAchievements($data)
	{
		$allAchievements = array();
		
		foreach ( $data as $oneNode ) {
			$oneAchievement = array();
			$oneAchievement['name'] = (string)$oneNode->name;
			if ( isset($oneNode->url) ) {
				$oneAchievement['url'] = (string)$oneNode->url;
			}
			if ( isset($oneNode->content) ) {
				$oneAchievement['content'] = SC2Achievements::parseInnerAchievements($oneNode->content->children());
			}
			$allAchievements[] = $oneAchievement;
		}
		return $allAchievements;		
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
		$this->addThingsToPrint('<h2><a href="' . $this->contentURL . '">' .$this->contentURL . '</a></h2><pre>' . print_r(json_decode($this->getJsonData()), TRUE) . '</pre>');
		
		$fullContent = RestUtils::getHTTPHeader('Testing') . $this->dataToPrint . RestUtils::getHTTPFooter(); 
		RestUtils::sendResponse(200, $fullContent);
	}
	
	/**
	 * Parse the content to get achivements data
	 * @return Array the json array for the achievements data
	 */
	protected function getAchievementsData()
	{
		// Get contents for results
		$pageHTML = str_get_html($this->content)->find('#profile-right', 0);
		$achievementCategory = array();
		$userBaseURL = GeneralUtils::getBaseURL($targetURL);
		
		// Get general category info (progress)
		$progressNode = $pageHTML->find('.achievements-progress span', 0);
		if ( $progessNode ) {
			$progress = array();
			
			// Get current progress
			$fullwords = $progressNode->plaintext;
			$endpos = strpos($fullwords, '/');
			$current = substr($fullwords, 0, $endpos);
			$current = GeneralUtils::parseInt($current);
			$progress['current'] = $current;
			
			// Get total progress
			$total = substr($fullwords, $endpos + 1);
			$total = GeneralUtils::parseInt($total);
			$progress['total'] = $total;
			
			$achievementCategory['progress'] = $progress;	
		}
		
		// Get all achivements in this category
		$achievements = array();
		$achievementNodes = $pageHTML->find('#achievements-wrapper .achievement');
		foreach ( $achievementNodes as $achievementNode ) {
			$oneAchievement = array();
			
			// Get achievement image info
			$imageWords = $achievementNode->find('.icon span', 0)->getAttribute('style');
			$oneAchievement['image'] = SC2Utils::parseBnetImageInfo($imageWords, $userBaseURL);
			
			// Get achievement name
			$achievementName = $achievementNode->find('.desc span', 0)->plaintext;
			$oneAchievement['name'] = trim($achievementName);
			
			// Get achievement description
			$achievementDescription = $achievementNode->find('.desc', 0)->plaintext;
			$startpos = strpos($achievementDescription, $achievementName) + strlen($achievementName);
			$achievementDescription = substr($achievementDescription, $startpos);
			$oneAchievement['description'] = html_entity_decode(trim($achievementDescription));
			
			// Get achivement points
			$points = $achievementNode->find('.meta span', 0)->plaintext; 
			$oneAchievement['points'] = GeneralUtils::parseInt($points);
			
			// Get achievement dateEarned. Date earned records the last time any progress is made on the achievement if its not earned
			$fullwords = $achievementNode->find('.meta', 0)->plaintext;
			$startpos = strpos($fullwords, $points) + strlen($points);
			$dateEarned = substr($fullwords, $startpos);
			if ( strlen(trim($dateEarned)) > 0 ) {
				$oneAchievement['dateEarned'] = SC2Utils::joinedDateToTimeStamp(trim($dateEarned), $this->contentURL, FALSE);
			}
			
			// Get achievement isEarned and isFinished
			$isFinished = FALSE;
			$fullwords = $achievementNode->getAttribute('class');
			$startpos = strpos($fullwords, 'unearned');
			if ( $startpos === FALSE ) {
				$isFinished = TRUE;
			}
			$oneAchievement['isFinished'] = $isFinished;
			
			if ( !$isFinished ) {
				$isEarned = isset($oneAchievement['dateEarned']) ? TRUE : FALSE;
			}else {
				$isEarned = TRUE;
			}
			$oneAchievement['isEarned'] = $isEarned;
			
			// Get achievement progress
			$progessNode = $achievementNode->find('.achievements-progress', 0);
			if ( $progessNode ) {
				// Get data
				$progress = array();
				$fullwords = $progessNode->getAttribute('data-tooltip');
				
				// Get current progress
				$endpos = strpos($fullwords, '/');
				$current = substr($fullwords, 0, $endpos);
				$progress['current'] = GeneralUtils::parseInt($current);
				
				// Get total progress
				$startpos = $endpos + 1;
				$total = substr($fullwords, $startpos);
				$progress['total'] = GeneralUtils::parseInt($total);
				
				$oneAchievement['progress'] = $progress;
			}
		
			// Get series or criteria
			$seriesNode = $achievementNode->find('.series', 0);
			if ( $seriesNode ) {
				// Get criteria or series
				if ( $tilesNode = $seriesNode->find('.series-tiles', 0) ) {
					$series = $this->getSeries($tilesNode, $userBaseURL);
					$oneAchievement['series'] = $series;
				}else if ( $criteriaNode = $seriesNode->find('.series-criteria', 0) ){
					$criteria = $this->getCriteria($criteriaNode);
					$oneAchievement['criteria'] = $criteria;
				}
			}
			// Add this achievement to our array
			$achievements[] = $oneAchievement;
		}
		$achievementCategory['achivements'] = $achievements;
		
		return $achievementCategory;
	}
	
	protected function getSeries($theNode, $userBaseURL)
	{
		$series = array();
		
		// Get series achievements
		$seriesAchievements = array();
		$seriesNodes = $theNode->find('.series-tile');
		foreach ( $seriesNodes as $seriesNode ) {
			$oneSeries = array();
			
			// Get image
			$imageStyle = $seriesNode->find('.icon-frame', 0)->getAttribute('style');
			$oneSeries['image'] = SC2Utils::parseBnetImageInfo($imageStyle, $userBaseURL);
			
			// Get name
			$titleNode = $seriesNode->find('.tooltip-title', 0);
			$name = $titleNode->plaintext;
			$oneSeries['name'] = trim($name);
				
			// Get description
			$description = $titleNode->parentNode()->plaintext;
			$startpos = strpos($description, $name) + strlen($name);
			$description = substr($description, $startpos);
			$oneSeries['description'] = trim($description);
			
			// Get points
			$points = $seriesNode->find('.series-badge', 0)->plaintext;
			$oneSeries['points'] = GeneralUtils::parseInt($points);
			
			// Get isEarned
			$isEarned = FALSE;
			$classwords = $seriesNode->getAttribute('class');
			$pos = strpos($classwords, 'tile-locked');
			if ( $pos === FALSE ) {
				$isEarned = TRUE;
			}
			$oneSeries['isEarned'] = $isEarned;
			
			$series[] = $oneSeries;	
		}
			
		// Finishes series data for this achivements.
		return $series;
	}
	
	protected function getCriteria($theNode)
	{
		$criteria = array();
	
		// Get a list of criteria names
		$criteriaNodes = $theNode->find('li');
		foreach ( $criteriaNodes as $criteriaNode ) {
			$oneCriteria = array();
			
			// Get criteria name
			$oneCriteria['name'] = $criteriaNode->plaintext;
			
			// Get isEarned
			$isEarned = TRUE;
			$fullwords = $criteriaNode->getAttribute('class');
			$pos = strpos($fullwords, 'earned');
			if ( $pos === FALSE ) {
				$isEarned = FALSE;
			}
			$oneCriteria['isEarned'] = $isEarned;
			
			$criteria[] = $oneCriteria;
		}
		
		return $criteria;
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