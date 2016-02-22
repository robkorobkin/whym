<?php

	if(!isset($_REQUEST['app'])){
		exit("What app are we in?");	
	}
	global $app;
	$app = $_REQUEST['app'];

	
	
	require_once("model/whym_config.php");
	require_once("model/framework/rkdatabase.php");
	require_once("model/whym_model.php");
	




///////////////////////////////////
	
	$model = new whymModel($whym_config);

	

	// LOAD STATIC ASSETS
	if(isset($_GET['lib'])) {

		$library = $_GET['lib'];
		
	
		switch($library) {
			
			case "js" : 
				header('Content-type: text/javascript');
				echo 'var dictionary=' . json_encode($whym_config['dictionary']) . ';';
				echo 'var whym_settings = ' . json_encode($whym_config['client']) . ';';
				echo file_get_contents('../client/shared/sharedObjects.js');
				echo file_get_contents('../client/' . $app . '/whym_' . $app . '.js');
			break;
			
			case "css" : 
				header('Content-type: text/css');
				echo file_get_contents('../client/' . $app . '/whym_' . $app . '.css');
			break;

		}

		exit();
	}


	$request = $_POST;
	$model -> request = $request;
	$verb = $request['verb'];
	

	// bounce users who aren't logged in
	$requiresLogin = ($verb != 'loginUser');
	if( $requiresLogin ){
		$model -> validateUser();
	}



	// if invoking an administrative method, make sure they have permission
	if(strpos($verb, "Admin") === 0){
		if(!isset($request['organizationId'])) $model -> handleError("No organization selected");
		$model -> checkAdmin($request['uid'], $request['organizationId']);
	}

	
	$response = $model -> $verb();
	echo json_encode($response);
