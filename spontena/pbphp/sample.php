<?php
	require "vendor/autoload.php";
	use spontena\pbphp\PBClient;

	# Configuration
	# $baseURL = 'https://aiaas.pandorabots.com';
	# $app_id = '#appid#';
	# $botname = '#botname#';
	# $user_key = '#user_key#';
	
	#Sample Codes
	
	# $pbc = new PBClient($baseURL,$app_id,$user_key);
	
	# List of bots
	#	$botlist = $pbc->getBotsList();
	#	if($botlist->status != "error"){
	#		foreach ($botlist as $obj2) {
	#			echo "---------------------\n";
	#			echo "botname: " . $obj2->botname . "\n";
	#			echo "description: " . $obj2->description . "\n";
	#			echo "language: " . $obj2->language . "\n";
	#			echo "compiled: " . $obj2->compiled . "\n";
	#			echo "open: " . $obj2->open . "\n";
	#		}
	#	}else{
	#		echo "Botslist: " . $botlist->message;
	#	}
	
	
	# Create bot
	#	$createbot = $pbc->create($botname);
	#	if($createbot->status == "ok"){
	#		echo 'Create: ' . $createbot->status . "\n";
	#	}else{
	#		echo 'Create: ' . $createbot->message . "\n";
	#	}
		
	
	# Delete bot
	#	$deletebot = $pbc->delete($botname);
	#	if($deletebot->status == "ok"){
	#		echo "Delete: " . $deletebot->status . "\n";
	#	}else{
	#		echo "Delete: " . $deletebot->message . "\n";
	#	}
	
	
	#List of bot files
	#	$filelist = $pbc->getBotFiles($botname);
	#	if($filelist->status != "error"){
	#		echo "---AIML-------------\n";
	#		foreach ($filelist->files as $file) {
	#			echo "name:" . $file->name . "\n";
	#			echo "size:" . $file->size . "\n";
	#			echo "modified:" . $file->modified . "\n";
	#			echo "loadorder:" . $file->loadorder . "\n";
	#			echo "items:" . $file->items . "\n";
	#			echo "---------------------\n";
	#		}
	#		
	#		echo "---SETS-------------\n";
	#		foreach ($filelist->sets as $set) {
	#			echo "name:" . $set->name . "\n";
	#			echo "size:" . $set->size . "\n";
	#			echo "modified:" . $set->modified . "\n";
	#			echo "items:" . $set->items . "\n";
	#			echo "---------------------\n";
	#		}
	#		
	#		echo "---MAPS-------------\n";
	#		foreach ($filelist->maps as $map) {
	#			echo "name:" . $map->name . "\n";
	#			echo "size:" . $map->size . "\n";
	#			echo "modified:" . $map->modified . "\n";
	#			echo "items:" . $map->items . "\n";
	#			echo "---------------------\n";
	#		}
	#		
	#		echo "---SUBSTITUTIONS-----\n";
	#		foreach ($filelist->substitutions as $sub) {
	#			echo "name:" . $sub->name . "\n";
	#			echo "size:" . $sub->size . "\n";
	#			echo "modified:" . $sub->modified . "\n";
	#			echo "items:" . $sub->items . "\n";
	#			echo "---------------------\n";
	#		}
	#		
	#		echo "---PROPERTIES--------\n";
	#		foreach ($filelist->properties as $pro) {
	#			echo "name:" . $pro->name . "\n";
	#			echo "size:" . $pro->size . "\n";
	#			echo "modified:" . $pro->modified . "\n";
	#			echo "items:" . $pro->items . "\n";
	#		}
	#			
	#		echo "---PDEFAULTS---------\n";
	#		foreach ($filelist->pdefaults as $pde) {
	#			echo "name:" . $pde->name . "\n";
	#			echo "size:" . $pde->size . "\n";
	#			echo "modified:" . $pde->modified . "\n";
	#			echo "items:" . $pde->items . "\n";
	#		}
	#	}else{
	#		echo "getBotFiles: " . $filelist->message . "\n";
	#	}
	
	# Upload file
	#	if ($dir = opendir("#./Directory/#" . $botname)) {
	#		while (($file = readdir($dir)) !== false) {
	#			if ($file != "." && $file != "..") {
	#				$uploadfile = $pbc->upload('#./Directory/#' . $botname . '/' . $file,$botname);
	#					if($uploadfile->status != "error"){
	#						echo "Upload[" . $file . "]: " . $uploadfile->status . "\n";
	#					}else{
	#						echo "Upload[" . $file . "]: " . $uploadfile->message . "\n";
	#					}
	#			}
	#		}
	#		closedir($dir);
	#	}
	
	# Delete bot file
	#	$deletefile = $pbc->deleteBotFile('#filename#','#file-kind#',$botname);
	#	if($deletefile->status == "ok"){
	#		echo "DeleteFile: " . $deletefile->status;
	#	}else{
	#		echo "DeleteFile: " . $deletefile->message;
	#	}
	
	# Compile bot
	#	$compile = $pbc->compile($botname);
	#	if($compile->status == "ok"){
	#		echo "Compile: " . $compile->status . "\n";
	#	}else{
	#		echo "Compile: " . $compile->message . "\n";
	#	}
	
	# Talk to bot
	#	$talk = $pbc->talk('#user input#',$botname);
	#	if($talk->status == "ok"){
	#		foreach ($talk->responses as $responce) {
	#			echo $responce . "\n";
	#		}
	#	}else{
	#		echo "Talk: " . $talk->message . "\n";
	#	}
		
	# Debug bot conversation	
	#	$debug = $pbc->debug('#user input#',$botname);
	#	if($debug->status == "ok"){
	#		echo "Input:";
	#		foreach ($debug->trace[0]->input as $input) {
	#			echo $input . ' ';
	#		}
	#		echo "\n";
	#		
	#		echo "Template: " . $debug->trace[1]->template . "\n";
	#		echo "Filename: " . $debug->trace[1]->filename . "\n";
	#		echo "Matched: ";
	#		foreach ($debug->trace[1]->matched as $matched) {
	#			echo $matched . ' ';
	#		}
	#		echo "\n";
	#
	#		echo "Result: ";
	#		foreach ($debug->trace[2]->result as $result) {
	#			echo $result . ' ';
	#		}
	#		echo "\n";
	#	}else{
	#		echo "Talk: " . $debug->message . "\n";
	#	}
?>