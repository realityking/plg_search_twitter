<?php
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class plgSearchTwitter extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
	
	/**
	 * @return array An array of search areas
	 */
	function onContentSearchAreas()
	{
		static $areas = array(
			'twitter' => 'PLG_SEARCH_TWITTER_LABEL'
		);
		return $areas;
	}
	
	function onContentSearch($text, $phrase='', $ordering='', $areas=null)
	{
		if (is_array($areas)) {
			if (!array_intersect($areas, array_keys($this->onContentSearchAreas()))) {
				return array();
			}
		}

		$text = trim($text);
		if ($text == '') {
			return array();
		}

		return $this->getResults($text);
	}
	
	function getResults ($text) {
		jimport('joomla.cache.cache');
		jimport('joomla.cache.callback');

		$cacheactive = JFactory::getConfig()->getValue('config.caching');
		$cache = & JFactory::getCache('plg_search_twitter');
		$cache->setCaching(true);
		$cache->setLifeTime(15);

		$section = JText::_( 'PLG_SEARCH_TWITTER_LABEL' );
		
		$limit = $this->params->def('search_limit', 5);
		if ($limit > 100) {
			$limit = 100;
		} else if ($limit < 1) {
			$limit = 1;
		}
		
		$uri = 'http://search.twitter.com/search.json?rpp='.$limit.'&q='.urlencode($text);
			
		$tw_username = $this->params->def('tw_username', '');
		if ($tw_username != '') {
			$tw_username = str_replace('@','',$tw_username);
			$uri .= '&from='.$tw_username;
		}
			
		$result_type = $this->params->def('result_type', 0);
		switch ($result_type) {
			case 0:
				break;
			case 1:
				$uri .= '&result_type=recent';
				break;
			case 2:
				$uri .= '&result_type=popular';
				break;
		}

		$timeout        = $this->params->def('timeout', 15);
		$connecttimeout = $this->params->def('connecttimeout', 5);

		$return = array();
		if ($results = $cache->call(array('plgSearchTwitter', 'retriveTweets'), $uri, $timeout, $connecttimeout)) {
			for ($x = 0; $x < sizeof($results); $x++) {
				$obj = new stdClass;
				if ($tw_username == '') {
					$obj->title = JText::_(PLG_SEARCH_TWITTER_TWEET_FROM).' '.$results[$x]["from_user"];
				} else {
					$obj->title = JText::_(PLG_SEARCH_TWITTER_TWEET);
				}
				$obj->text = $results[$x]["text"];
				$obj->section = $section;
				$obj->created = $results[$x]["created_at"];
				$obj->href = 'http://twitter.com/'.$results[$x]["from_user"].'/status/'.$results[$x]["id"];
				$obj->id = 1;
				$obj->count = 1;
				$obj->catid = 1;
				$obj->browsernav = 0;

				$return[] = $obj;
			}
		}
		
		$cache->setCaching($cacheactive);
		
		return $return;
	}

	static function retriveTweets ($uri, $timeout, $connecttimeout) {
		if ($timeout < 0) {
			$timeout = 0;
		}
		if ($connecttimeout < 0) {
			$connecttimeout = 0;
		}
	
		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL, $uri);
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, $connecttimeout);
		curl_setopt($curl_handle, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
		//A generic user agent will be rate limited by Twitter
		curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Joomla '.JVERSION.' (Search Twitter 1.0)');
		if ($json = curl_exec($curl_handle)) {
			curl_close($curl_handle);
			// Workaround for 32 bit systems. The id value is larger than the maximum value an int
			// can hold on 32 bit systems.
			$json = preg_replace( '/id":(\d+)/', 'id":"\1"', $json );  
			if ($contents = json_decode($json, TRUE, 10)) {
				return $results = $contents["results"];
			}
		} else {
			curl_close($curl_handle);
		}
		return false;
	}
}
?>