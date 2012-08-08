<?php

require_once('global-config.php');
require_once('../helpers/helper-fns.php');
require_once('../helpers/simple_html_dom.php');
require_once('../helpers/RestUtils.php');
require_once('../helpers/URLConnect.php');

/**
 * Handles all tasks related to custom divisions.
 *
 * @author Draco Li
 * @version 1.0
 */
class SC2CustomDivision {

	private $options;
	
	/**
	 * Initializes the SC2Division by assigning the content to parse. Then perfrom the parse.
	 * If no content is received, we send out an error page
	 * @param $content the content of parse
	 * @param $url The url of the content to be parsed (the division link url). Used to determine if its BNET data or ranks data.
	 * @return void
	 */
	public function __construct($options)
	{
		$this->options = $options;
		
		if ( $options['update'] == 'true' ) {
		  $this->updateCustomDivisions();
		}
	}
  
  /**
   * Gets the rankings data for just one custom division
   */
  public function getDivisionData()
  {
    $divURL = $this->options['url'];
    $offset = $this->options['offset'];
    $amount = $this->options['amount'];
    
    $customDivisions = json_decode( file_get_contents($this->getCachePath()) );
    $targetDivision = NULL;
    foreach ( $customDivisions as $customDivision ) {
      if ( $customDivision->url === $divURL ) {
        $targetDivision = $customDivision;
      }
    }
    
    $endIndex = min( $offset + $amount, count($targetDivision->ranks) );
    
    $ranks = array();
    for ( $i = $offset; $i < $endIndex; $i++ ) {
      $ranks[] = $targetDivision->ranks[$i];
    }
    
    $targetDivision->ranks = $ranks;
		return $targetDivision;
  }
  
  /**
   * Get a list of custom divisions that we have on file
   */
	public function getDivisionsList()
	{
	  $offset = $this->options['offset'];
    $amount = $this->options['amount'];
    
    $customDivisions = $this->getCustomDivisionList();
    $endIndex = min( $offset + $amount, count($customDivisions) );
    
    $result = array();
    for ( $i = $offset; $i < $endIndex; $i++ ) {
      $result[] = $customDivisions[$i];
    }
    
    if ( count($result) == 0 ) {
      return NULL;
    }
    
		return $result;
	}
	
	/**
	 * Update all the current divisions we have saved in the assets folder
	 */
	private function updateCustomDivisions()
	{
	  // This function can execute longer since we might need to retrieve data from many sources
	  set_time_limit(60*10);
	  
	  $customDivisions = $this->getCustomDivisionList();
    
	  foreach ( $customDivisions as $customDivision ) {
	    $nextPage = $customDivision->url;
	    
	    // Get all ranks for this custom divisions by going through all pages
	    $divisionRanks = array();
	    while ( $nextPage !== NULL ) {
	      list($ranks, $nextPage) = $this->parseCustomDivisionPage($nextPage);
	      $divisionRanks = array_merge($divisionRanks, $ranks);
	    }
  		
  		// Save contents for this custom division
  		$customDivision->ranks = $divisionRanks;
  		
	  }// End one custom division
	  
	  // Save all our updated custom divisions to file
	  file_put_contents($this->getCachePath(), json_encode($customDivisions));
	}
	
	/**
	 * Parse one custom division
	 * @param URL String the url of the division page to parse
	 * @return Array The ranks from the page
	 */
	private function parseCustomDivisionPage($divisionPage)
	{
	  $pageRanks = array();
	  
	  // Get contents for results
		$urlconnect = new URLConnect($divisionPage, 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode(), $this->dataToPrint);
		}
		$contents = str_get_html($urlconnect->getContent());
		
		// Get content for this division
		$allRows = $contents->find('#sortlist tr');
		for ( $i = 1; $i < count($allRows); $i++ ) {
		  $oneRank = array();
		  $rankNode = $allRows[$i];
		  
		  // Get general rank
      $generalRank = GeneralUtils::parseInt( $rankNode->find('.rank', 0)->plaintext );
      $oneRank['rank'] = $generalRank;

  		$divisionRegion == NULL;

  		// Should only be one player, but we use array to stay consistant with rankings result
  		$playersArray = array();
			{
				$playerNode = $rankNode->find('.character0', 0);

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
				$onePlayer['ranksURL'] = $playerURL;

				// Get estimated bnet url
				$onePlayer['bnetURL'] = SC2Utils::estimateBLink($onePlayer['ranksURL']);

				// Estimate player region based on bnet url
				$onePlayer['region'] = SC2Utils::playerRegionFromBnetURL($onePlayer['bnetURL']);

				$divisionRegion = $onePlayer['region'];

				$playersArray[] = $onePlayer;
			}
			$oneRank['players'] = $playersArray;

			// Get user's best division data
			$oneDivision = array();
			{
				// Get points
				$points = $rankNode->find('.points', 0)->plaintext;
				$oneDivision['points'] = GeneralUtils::parseInt($points);

				// Get wins
				$wins = $rankNode->find('.wins', 0)->plaintext;
				$oneDivision['wins'] = GeneralUtils::parseInt($wins);

				// Get losses if provided
				$losses = GeneralUtils::parseInt( $rankNode->find('.losses', 0) );
				if ( $losses !== NULL && strlen($losses) > 0 ) {
				  $oneDivision['losses'] = $losses;
				}

				// Calculate win ratio if we have losses
				if ( isset($oneDivision['losses']) ) {
				  $totalGames = $oneDivision['wins'] + $oneDivision['losses'];
				  $oneDivision['winRatio'] = $oneDivision['wins'] / $totalGames;
				}  

				// Get division league
				$leagueFull = $rankNode->find('.points img', 0)->getAttribute('alt');
				$endpos = strpos($leagueFull, '-');
				$league = strtolower(trim(substr($leagueFull, 0, $endpos)));
				$oneDivision['league'] = $league;

        // Get division region
				$oneDivision['region'] = $divisionRegion;

        // Division url and rank
        if ( $oneDivision['league'] == 'grandmaster' ) {
          // Get division rank
          $fullString = $rankNode->find('.divisiongm', 0)->plaintext;
          $startpos = strpos($fullString, '#') + 1;
          $divisionRank =  substr($fullString, $startpos);
          $divisionRank = GeneralUtils::parseInt( $divisionRank );
          $oneDivision['rank'] = $divisionRank;
          
          // Division name
          $oneDivision['name'] = "Grandmaster";
          
          // TODO: Add gm bnet division url
        }else {
          // Get division rank
          $fullString = $rankNode->find('.division', 0)->plaintext;
          $startpos = strpos($fullString, '#') + 1;
          $endpos = strpos($fullString, ')');
          $divisionRank =  substr($fullString, $startpos, $endpos - $startpos);
          $divisionRank = GeneralUtils::parseInt( $divisionRank );
          $oneDivision['rank'] = $divisionRank;
          
          // Get division name
          $divisionName = $rankNode->find('.division a', 0)->plaintext;
          $oneDivision['name'] = trim($divisionName);
          
          // Get ranks division url
          $poop = $rankNode->find('.division a', 0);
          $divisionURL = $rankNode->find('.division a', 0)->getAttribute('href');
          $oneDivision['ranksURL'] = RANKSURL . $divisionURL;
        }
			} // End one rank row
			$oneRank['division'] = $oneDivision;

			// Add a new rank to our array
			$pageRanks[] = $oneRank;
			
		}// End one rank page
		
		// Determine if we have next page
		$nextPage = NULL;
		if ( count($contents->find('.next a', 0)) > 0 ) {
		  $nextPage = RANKSURL . $contents->find('.next a', 0)->getAttribute('href');
		}
		
		// Return results for both the ranks for this page and also the next page's url
		$results = array();
		$results[] = $pageRanks;
		$results[] = $nextPage;
		return $results;
	}
	
	/**
	 * Retrieves our custom divisions from the assets folder
	 * @return Array The custom divisions we made
	 */
	private function getCustomDivisionList()
	{
	  $fullPath = GeneralUtils::serverBasePath() . DIRECTORY_SEPARATOR . 
			'assets' . DIRECTORY_SEPARATOR . 'custom-divisions.json';
	  return json_decode(file_get_contents($fullPath));
	}
  
	protected function getCachePath()
	{
		$fullPath = GeneralUtils::serverBasePath() . DIRECTORY_SEPARATOR . 
			'cache' . DIRECTORY_SEPARATOR . 'custom-divisions' . DIRECTORY_SEPARATOR . 
			'custom-divisions.json';
		return $fullPath;
	}
}

?>