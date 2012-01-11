<?php

class RestUtils
{
	public static function sendResponse($status = 200, $body = '', $header = '', $content_type = 'text/html')
	{
		$status_header = 'HTTP/1.1 ' . $status . ' ' . RestUtils::getStatusCodeMessage($status);	
		
		// set the status
		header($status_header);
		
		// set the content type
		header('Content-type: ' . $content_type . '; charset=utf-8');
		
		// If no erros, we print w/e needed or nothing
		if ( $status == 200 ) {
			echo $body;
			exit;
		}
		
		// Something went wrong, set deafult header
		$header = ($header == '') ? $status . ' : ' . RestUtils::getStatusCodeMessage($status) : $header;
		
		// Something nice to display when page failed
		$message = '';
		switch($status)
		{
			case 401:
				$message = 'You must be authorized to view this page.';
				break;
			case 404:
				$message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
				break;
			case 500:
				$message = 'The server encountered an error processing your request.';
				break;
			case 501:
				$message = 'The requested method is not implemented.';
				break;
			case 502:
				$message = 'Bad Gateway';
				break;
			case 503:
				$message = 'The service is temporarly unavailable';
		}
			
		if ( $status != 200 && $body != '' ) {
			echo RestUtils::getHTTPHeader($header);
			echo '<h2>' . $status . ' : ' . RestUtils::getStatusCodeMessage($status) . '</h2><hr />';
			echo $body;
			echo RestUtils::getHTTPFooter();
			exit;
		}
		
		if ( $status != 200 && $body == '' ) {
			echo RestUtils::getHTTPHeader($header);
			echo '<h2>' . RestUtils::getStatusCodeMessage($status) . '</h2>
								<p>' . $message . '</p><hr />';
			echo RestUtils::getHTTPFooter();
			exit;
		}
	}
	
	public static function getStatusCodeMessage($status)
	{
		// All code and values stored in ini file
		$codes = parse_ini_file('../assets/statusCodes.ini');
		return (isset($codes[$status])) ? $codes[$status] : '';
	}
	
	public static function getHTTPHeader($title = '')
	{
		$header = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
						<html>
							<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<title>' . $title . '</title>
							</head><body>';
		return $header;
	}
	
	public static function getHTTPFooter()
	{
		return '</body></html>';
	}
}
?>