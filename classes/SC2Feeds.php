<?php

require_once('global-config.php');
require_once('../helpers/helper-fns.php');
require_once('../helpers/simple_html_dom.php');
require_once('../helpers/RestUtils.php');
require_once('../helpers/URLConnect.php');

/**
 * Handles generating feeds for the app from a variety of source including
 *   from us and from a bunch of Starcraft 2 news site.
 *
 * @author Draco Li
 * @version 1.0
 */
class SC2Feeds {
  
  private $jsonData;
  private $dataToPrint;
  private $options;
  
  public function __construct($options)
	{
	  // Adjust player region for our list
	  $region = $options["region"];
	  if ( $region == "am" || $region == "na" ) {
	    $region = "na";
	  }else if ( $region == "kr" || $region == "tw" ) {
	    $region = "krtw";
	  }else if ( $region == "eu" ){
	    $region = "eu";
	  }else if ( $region == "sea" ){
	    $region = "sea";
  	}else {
  	  $region = "na";
  	}
	  
	  $this->options = $options;
	  $this->options["region"] = $region;
	  
	  // Update everything if asked
	  if ( isset($this->options["update"]) ) {
	    $this->updateAllFeeds();
	  }
  }
  
  public function updateAllFeeds()
  {
    // This function can execute 10min since we might need to retrieve data from many sources
	  set_time_limit(60*10);
	  
    $na = $this->getBnetFeedsForRegion('na');
    $this->saveFeedsForRegion('na', $na);
    
    $eu = $this->getBnetFeedsForRegion('eu');
    $this->saveFeedsForRegion('eu', $eu);
    
    $sea = $this->getBnetFeedsForRegion('sea');
    $this->saveFeedsForRegion('sea', $sea);
    
    $krtw = $this->getBnetFeedsForRegion('krtw');
    $this->saveFeedsForRegion('krtw', $krtw);
  }
  
  public function getJsonData($jsonData = NULL)
	{
	  $ourFeeds = $this->getOurFeeds();
	  $sc2Feeds = $this->getCachedBnetFeedsForRegion($this->options['region']);
	  $totalFeeds = $ourFeeds;
	  if ( $sc2Feeds !== NULL ) {
	    $totalFeeds = array_merge($ourFeeds, $sc2Feeds);
	  }
	  
	  # Sort all of our feeds
	  $totalFeeds = json_decode(json_encode($totalFeeds));
	  usort($totalFeeds, array(__CLASS__, 'defaultFeedsSort'));
	  
	  # Filter our feeds according to our own specs
	  $totalCount = count($totalFeeds);
	  $offset = $this->options['offset'];
	  $amount = $this->options['amount'];
	  $newData = array_slice($totalFeeds, $offset ,$amount);
	  
		return json_encode($newData); 
  }
  
  protected function getCachedBnetFeedsForRegion($region)
  {
    $filepath = $this->getCachedFeedsFilepathForRegion($region);
    if ( file_exists($filepath) ) {
      return json_decode( file_get_contents($filepath) );
    }
    return NULL;
  }
  
  protected function saveFeedsForRegion($region, $feeds)
  {
    $filepath = $this->getCachedFeedsFilepathForRegion($region);
    $requiresSave = false;
    
    $feedsToSave = NULL;
    if ( file_exists($filepath) ) {
      $feedsToSave = json_decode( file_get_contents($filepath) );
    }
    
    if ( $feedsToSave == NULL )
    {
      $feedsToSave = $feeds;
      $requiresSave = true;
    }
    else if ( $feeds !== NULL && count($feeds) > 0 )
    {
      $latestSavedFeed = $feedsToSave[0];
      $newFeeds = array();
      foreach ( $feeds as $feed ) {
        if ( $feed["title"] == $latestSavedFeed->title && 
             $feed["postedDate"] == $latestSavedFeed->postedDate ) {
          break;
        }
        $newFeeds[] = $feed;
        echo "additional feed to be saved has title: " . $feed["title"] . "<br>";
      }
      
      // Combine our new feeds with old ones
      if ( count($newFeeds) > 0 ) {
        $feedsToSave = array_merge($newFeeds, $feedsToSave);
        $requiresSave = true;
      }
    }
    
    if ( $requiresSave ) {
      echo "<pre>" . print_r($feedsToSave, TRUE) . "</pre>";
      file_put_contents($filepath, json_encode($feedsToSave));
    }else {
      echo "no new feeds saved<br>";
    }
  }
  
  protected function getCachedFeedsFilepathForRegion($region)
  {
    return GeneralUtils::serverBasePath() . DIRECTORY_SEPARATOR . 'cache' .
              DIRECTORY_SEPARATOR . 'feeds' . DIRECTORY_SEPARATOR . $region . '.json';
  }
  
  protected function getBnetFeedsForRegion($region)
  {
    // Handle china feed uniquly
    if ( $region == 'cn' ) {
      // return $this->getBnetFeedsForChina();
      $region = 'na';
    }
    
    $gmMapper = array('na' => 'http://us.battle.net/sc2/en/',
		                  'am' => 'http://us.battle.net/sc2/en/',
						          'eu' => 'http://eu.battle.net/sc2/en/',
        						  'sea' => 'http://sea.battle.net/sc2/en/',
        						  'krtw' => 'http://kr.battle.net/sc2/ko/',
        						  'cn' => 'http://www.battlenet.com.cn/sc2/zh/');

		$targetURL = GeneralUtils::mapKeyToValue($gmMapper, $region);
		
		// Get content from website
		$urlconnect = new URLConnect($targetURL, 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode(), $this->dataToPrint);
		}
		$contents = str_get_html($urlconnect->getContent());
		
		$feeds = array();
		foreach ( $contents->find('#news-updates .news-article-inner') as $oneFeedNode )
		{
		  $oneFeed = array();
		  
		  // Get feed title
		  $oneFeed['title'] = trim($oneFeedNode->find('h3 a', 0)->plaintext);
		  
		  // Get feed url
		  $oneFeed['url'] = $targetURL . $oneFeedNode->find('h3 a', 0)->getAttribute('href');
		  
		  // Get feed posted date
		  preg_match('/(\d+)_(\d+)_(\d+)/', $oneFeed['url'], $matches);
		  if ( $region == 'eu' ) {
		    $postedDate = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
		  }else if ( $region == 'krtw' ) {
		    $postedDate = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
		  }else {
		    $postedDate = $matches[3] . '-' . $matches[1] . '-' . $matches[2];
		  }
		  $oneFeed['postedDate'] = (int)strtotime($postedDate);;
		  
		  // Get feed content
		  $content = $oneFeedNode->find('.article-summary p', 0)->plaintext;
		  $oneFeed['content'] = trim($content);
		  
		  // Get feed type - same for everything
		  $oneFeed['sourceType'] = "Blizzard";
		  
		  $feeds[] = $oneFeed;
	  }
	  
	  return $feeds;
  }
  
  protected function getOurFeeds()
  {
    $feedsPath = GeneralUtils::serverBasePath() . DIRECTORY_SEPARATOR . 'assets' .
              DIRECTORY_SEPARATOR . 'feeds/*';
    $feeds = array();
    foreach ( glob($feedsPath) as $file )
    {
      $feed = array();
      $fileContent = file_get_contents($file);
      $doc = str_get_html($fileContent);
      
      // Get feed title
      $feed["title"] = $doc->find('head title', 0)->plaintext;
      
      // Get feed posted date
      $fileName = basename($file);
      $dateString = substr($fileName, 0, strpos($fileName, '.html'));
      $feed["postedDate"] = (int)strtotime($dateString);
      
      // Get feed contentRaw
      $feed["content"] = $doc->find('meta[name=description]', 0)->getAttribute('content');
      
      $feed["url"] = $file;
      
      // Set feed type
      $feed["sourceType"] = "SC2Enhanced";
      
      $feeds[] = $feed;
    }
    
    return $feeds;
  }
  
  protected function defaultFeedsSort($a, $b)
  {
    if ( $a->postedDate > $b->postedDate ) {
      return -1;
    }else if ( $a->postedDate < $b->postedDate ) {
      return 1;
    }else {
      return 0;
    }
    return 0;
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
	 * Quick function to add something to be printed
	 */
	public function addThingsToPrint($things)
	{
		$this->dataToPrint .= $things;	
	}
}

?>