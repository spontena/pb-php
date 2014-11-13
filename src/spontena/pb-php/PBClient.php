<?php
	namespace spontena\pbphp;
	require '../../vendor/autoload.php';
	use GuzzleHttp\Client;
	use GuzzleHttp\Exception\RequestException;

class PBClient {
	
	# 
	private $host;		# Pandrabots API Server URL
	private $appid;	    # Application ID
	private $userkey;	# application's user key
	
	# Constructor
	function __construct($url,$appid,$key){
		$this->host = $url;
		$this->appid = $appid;
		$this->userkey = $key;
		$this->Client = new Client();
	}
	
	# List of bots
	function getBotsList(){
		try{
			$response = $this->Client->get($this->host . '/bot/' . $this->appid . '?user_key=' . $this->userkey);
			return json_decode($response->getBody());
		}catch (RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	# Create bot
	function create($botname){
		try{
			$response = $this->Client->put($this->host . '/bot/' . $this->appid . '/' . $botname . '?user_key=' . $this->userkey);
			return json_decode($response->getBody());
		}catch (RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	# Delete bot
	function delete($botname){
		try{
			$response = $this->Client->delete($this->host . '/bot/' . $this->appid . '/' . $botname . '?user_key=' . $this->userkey);
			return json_decode($response->getBody());
		}catch (RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	#List of bot files
	function getBotFiles($botname){
		try{
			$response = $this->Client->get($this->host . '/bot/' . $this->appid . '/' . $botname . '?user_key=' . $this->userkey);
			return json_decode($response->getBody());
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}

	# Upload file
	function openfile($f){
		if (file_exists($f)){
			return fopen($f, 'r');
		}else{
			throw new Exception('No such file or directory.');
		}
	}
	
	function upload($fname,$botname){
		$pathData = pathinfo($fname);
		if($pathData["extension"] == 'aiml'){
			$pops_uploadfile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/file/' . $pathData["filename"] . '?user_key=' . $this->userkey;
		}else if ($pathData["extension"] == 'set' or $pathData["extension"] == 'map' or $pathData["extension"] == 'substitution') {
			$pops_uploadfile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/' . $pathData["extension"] . '/' . $pathData["filename"] . '?user_key=' . $this->userkey;
		}else if($pathData["extension"] == 'pdefaults' or $pathData["extension"] == 'properties'){
			$pops_uploadfile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/'. $pathData["extension"] .'?user_key=' . $this->userkey;
		}else{
			return json_decode('{"status": "error","message": "Invalid file name."}');
		}
		
		try{
			$resource = $this->openfile($fname);
		}catch(Exception $e){
			return json_decode('{"status": "error","message": "' . $e->getMessage() . '"}');
		}
		
		try{
			$response = $this->Client->put($pops_uploadfile_url, ['body' => $resource]);
			return json_decode($response->getBody());
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	#Delete bot file
	function deleteBotFile($fname,$fkind,$botname){
		switch ($fkind) {
			case 'file': $pops_deletefile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/file/' . $fname . '?user_key=' . $this->userkey; break;
			case 'map': $pops_deletefile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/map/' . $fname . '?user_key=' . $this->userkey; break;
			case 'substitution': $pops_deletefile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/substitution/' . $fname . '?user_key=' . $this->userkey; break;
			case 'set': $pops_deletefile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/set/' . $fname . '?user_key=' . $this->userkey; break;
			case 'pdefaults': $pops_deletefile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/pdefaults/' . $fname . '?user_key=' . $this->userkey; break;
			case 'properties': $pops_deletefile_url = $this->host . '/bot/' . $this->appid . '/' . $botname . '/properties/' . $fname . '?user_key=' . $this->userkey; break;
			default: return json_decode('{"status": "error","message": "Invalid file-kind"}'); break;
		}
		
		try{
			$response = $this->Client->delete($pops_deletefile_url);
			return json_decode($response->getBody());
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}

	# Complile bot
	function compile($botname){
		try{
			$response = $this->Client->get($this->host . '/bot/' . $this->appid . '/' . $botname . '/verify?user_key=' . $this->userkey);
			return json_decode($response->getBody());
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}

	# Talk to bot
	function talk($input,$botname,$clientname = '',$sessionid = '', $recent = true) {
		try{
			if(strlen($input) == 0){
				throw new Exception("No input conversation.");
			}
			if(strlen($botname) == 0){
				throw new Exception("No input bot name.");
			}
		}catch(Exception $e){
			return json_decode('{"status": "error","message": "' . $e->getMessage() . '"}');
		}
		
		$pops_talk_url = $this->host . '/talk/' . $this->appid . '/' . $botname;
		$body = ['body' => [ 'input' => $input, 'user_key' => $this->userkey]];
		$body['body'] += (strlen($clientname) != 0) ? ['client_name' => $clientname]:[];
		$body['body'] += (strlen($sessionid) != 0) ?  ['sessionid' => $sessionid]:[];
		$body['body'] += ($recent) ? ['recent' => 'true']:[];
			
		try{	
			$response = $this->Client->post($pops_talk_url, $body);
			return json_decode($response->getBody());
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	# Debug bot conversation
	function debug($input,$botname,$client_name = '',$sessionid = '',$reset = false,$extra = false,$trace = true,$reload = false,$recent = true) {
		try{
			if(strlen($input) == 0){
				throw new Exception("No input conversation.");
			}
			if(strlen($botname) == 0){
				throw new Exception("No input bot name.");
			}
		}catch(Exception $e){
			return json_decode('{"status": "error","message": "' . $e->getMessage() . '"}');
		}
		
		$pops_debug_url = $this->host . '/talk/' . $this->appid . '/' . $botname;
		
		$body = ['body' => [ 'input' => $input, 'user_key' => $this->userkey]];
		$body['body'] += (strlen($clientname) != 0) ? ['client_name' => $clientname]:[];
		$body['body'] += (strlen($sessionid) != 0) ?  ['sessionid' => $sessionid]:[];
		$body['body'] += ($reset) ?  ['reset' => 'true']:[];
		$body['body'] += ($extra) ?  ['extra' => 'true']:[];
		$body['body'] += ($trace) ?  ['trace' => 'true']:[];
		$body['body'] += ($reload) ?  ['reload' => 'true']:[];
		$body['body'] += ($recent) ? ['recent' => 'true']:[];
		
		try{
			$response = $this->Client->post($pops_debug_url, $body);
			return json_decode($response->getBody());
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
}
?>