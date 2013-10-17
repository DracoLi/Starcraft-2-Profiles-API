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

  const PAGES_TO_PARSE = 1;

  public function __construct($options)
	{
	  // Adjust player region for our list
	  $region = $options["region"];
	  if ( $region == "am" || $region == "na" ) {
	    $region = "na";
	  }else if ( $region == "kr" || $region == "tw" ||
	             $region == "krtw") {
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
	  if ( $this->options["update"] == 'true' ) {
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
	  // Form a list of all our feeds
	  $ourFeeds = $this->getOurFeeds();
    $includedOurFeeds = array();
    $foundPromoted = false;
    $promotedFeed = NULL;
    for ($i=0; $i < count($ourFeeds); $i++) {
        $feed = $ourFeeds[$i];
        if ( $feed->promoted && !$foundPromoted ) {
          $promotedFeed = $feed;
          $foundPromoted = true;
        }else {
          $includedOurFeeds[] = $feed;
        }
    }

	  $sc2Feeds = $this->getCachedFeedsForRegion($this->options['region']);
	  $totalFeeds = $includedOurFeeds;
	  if ( $sc2Feeds !== NULL ) {
	    $totalFeeds = array_merge($totalFeeds, $sc2Feeds);
	  }

	  // Sort all of our feeds
	  $totalFeeds = json_decode(json_encode($totalFeeds));
	  usort($totalFeeds, array(__CLASS__, 'defaultFeedsSort'));

	  // Filter our feeds according to our own specs
	  $totalCount = count($totalFeeds);
	  $offset = $this->options['offset'];
	  $amount = $this->options['amount'];
	  $neededFeeds = array_slice($totalFeeds, $offset ,$amount);

    // Get the most recent promoted feed and add it to our list
    if ($offset == 0) {
      $tempArray = array();
      $tempArray[0] = $promotedFeed;
      $neededFeeds = array_merge($tempArray, $neededFeeds);
    }

	  // Add in the total amount of feeds to have
	  $jsonResult = array();
	  $jsonResult['total'] = count($totalFeeds);
	  $jsonResult['feeds'] = $neededFeeds;

		return json_encode($jsonResult);
  }

  protected function getCachedFeedsForRegion($region)
  {
    global $redis;
    $filepath = $this->getCachedFeedsFilepathForRegion($region);
    if ( $redis->exists($filepath) ) {
      return json_decode( $redis->get($filepath) );
    }
    return NULL;
  }

  /**
   * Save feeds for a certain region into a file.
   * This method handles not saving duplicate feeds, only the new ones
   */
  protected function saveFeedsForRegion($region, $feeds)
  {
    global $redis;
    $filepath = $this->getCachedFeedsFilepathForRegion($region);
    $requiresSave = false;

    $feedsToSave = NULL;
    if ( $redis->exists($filepath) ) {
      $feedsToSave = json_decode( $redis->get($filepath) );
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
      }

      // Combine our new feeds with old ones
      if ( count($newFeeds) > 0 ) {
        $feedsToSave = array_merge($newFeeds, $feedsToSave);
        $requiresSave = true;
      }
    }

    if ( $requiresSave ) {
      $redis->set($filepath, json_encode($feedsToSave));
      // file_put_contents($filepath, json_encode($feedsToSave));
    }
  }

  /**
   * Return the cached feeds file path for a certain region
   * This is not for our own feeds
   */
  protected function getCachedFeedsFilepathForRegion($region)
  {
    return GeneralUtils::serverBasePath() . DIRECTORY_SEPARATOR . 'cache' .
              DIRECTORY_SEPARATOR . 'feeds' . DIRECTORY_SEPARATOR . $region . '.json';
  }

  /**
   * Get for BNET feeds for a certain region from BNET directly
   */
  protected function getBnetFeedsForRegion($region)
  {
    // Handle china feed uniquly since parsing is pain
    if ( $region == 'cn' ) {
      // return $this->getBnetFeedsForChina();
      $region = 'na';
    }

    # The ?- avoids any promo splash pages
    $gmMapper = array('na' => 'http://us.battle.net/sc2/en/?-',
		                  'am' => 'http://us.battle.net/sc2/en/?-',
						          'eu' => 'http://eu.battle.net/sc2/en/?-',
        						  'sea' => 'http://sea.battle.net/sc2/en/?-',
        						  'krtw' => 'http://kr.battle.net/sc2/ko/?-',
        						  'cn' => 'http://www.battlenet.com.cn/sc2/zh/?-');
    $baseURL = GeneralUtils::mapKeyToValue($gmMapper, $region);
		$targetURL = $baseURL;
		$pagesToParse = SC2Feeds::PAGES_TO_PARSE;

		// Parse all the pages
		$totalFeeds = array();
    list($feeds, $targetURL) = $this->parseBNETFeedsForURL($targetURL, $region);
    $totalFeeds = array_merge($totalFeeds, $feeds);
		return $totalFeeds;
  }

  /**
   * Parse BNET feeds for a certain URL
   */
  protected function parseBNETFeedsForURL($targetURL, $region)
  {
    // Get content from website
		$urlconnect = new URLConnect($targetURL, 100, FALSE);
		if ( $urlconnect->getHTTPCode() != 200 ) {
			RestUtils::sendResponse($urlconnect->getHTTPCode(), $this->dataToPrint);
		}
		$contents = str_get_html($urlconnect->getContent());

		$feeds = array();
		foreach ( $contents->find('#blog-articles .article-wrapper') as $oneFeedNode )
		{
		  $oneFeed = array();

		  // Get feed title
		  $oneFeed['title'] = trim($oneFeedNode->find('.article-title', 0)->plaintext);

		  // Get feed url
		  $oneFeed['url'] = $targetURL . $oneFeedNode->find('a', 0)->getAttribute('href');

		  // Get feed posted date
      foreach ( $oneFeedNode->find('.article-content meta') as $oneMeta )
      {
        if ( $oneMeta->getAttribute('itemprop') == 'datePublished' ) {
          $postedDate = $oneMeta->getAttribute('content');
          $oneFeed['postedDate'] = (int)strtotime($postedDate);;
        }
      }

		  // Get feed content
		  $content = $oneFeedNode->find('.article-summary', 0)->plaintext;
		  $oneFeed['content'] = trim($content);

		  // Get feed type - same for everything
		  $oneFeed['sourceType'] = "Blizzard";

		  $feeds[] = $oneFeed;
	  }

	  $results = array();
	  $results[0] = $feeds;

	  // Get next page
	  // $nextPath = $contents->find('.blog-paging .button1-next', 0)->getAttribute('href');
	  $results[1] = NULL;

	  return $results;
  }

  /**
   * Get our feeds. No region support right now
   */
  protected function getOurFeeds()
  {
    $feedsPath = GeneralUtils::serverBasePath() . DIRECTORY_SEPARATOR . 'assets' .
              DIRECTORY_SEPARATOR . 'feeds.json';
    $feeds = json_decode(file_get_contents($feedsPath));

    // Process our feeds
    $adjustedFeeds = array();
    foreach ( $feeds as $feed ) {
      $feed->url = "http://" . $_SERVER['HTTP_HOST'] . DIRECTORY_SEPARATOR . 'assets' .
                     DIRECTORY_SEPARATOR . 'feeds' . DIRECTORY_SEPARATOR . $feed->url . '.html';
      $feed->postedDate = (int)strtotime($feed->postedDate);
      $feed->sourceType = "SC2Enhanced";
      $adjustedFeeds[] = $feed;
    }

    return $adjustedFeeds;
  }

  /**
   * Sort feeds by postedDate
   */
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
