<?php

class URLConnect 
{
    private $info;
	private $content;
    private $url;
	private $onlyHeader;
	private $timeout;

    public function __construct($url, $timeout = 30, $onlyHeader = FALSE)
    {
        $this->url = $url;
		$this->timeout = $timeout;
		$this->onlyHeader = ($onlyHeader) ? TRUE: FALSE;
        $this->getData();
    }

    public function getData() 
    {
		if ( isset($this->url) && $this->url != '' ) {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $this->url);
			curl_setopt($curl, CURLOPT_NOBODY, $this->onlyHeader);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			$this->content = curl_exec($curl);
			$this->info = curl_getinfo($curl);
			curl_close($curl);	
		}
    }
	
    public function getFiletime() 
    {
        return $this->info['filetime'];
    }

	public function getHTTPCode()
	{
		return $this->info['http_code'];
	}
	
	public function getContentType()
	{
		return $this->info['content_type'];
	}
	
	public function getContent()
	{
		return $this->content;
	}
	
	public function getInfo()
	{
		return $this->info;	
	}
}

?>