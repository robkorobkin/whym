<?php

	// SET COOKIES TO PERSIST FOR A WEEK (UNLESS MANUALLY LOGGED OUT)
	// VITAL FOR MOBILE - OTHERWISE PERSON GETS LOGGED OUT EVERY TIME THEY CLOSE THE APP
	ini_set('session.cookie_lifetime', 60 * 60 * 24 * 7);  // 7 day cookie lifetime
	session_start();

	
	global $app;


	$config = array();
	if(strpos(getCwd(), "var") !== false) $environment = "prod";
	else $environment = "local";
	
	switch($environment) {
	
		// localhost configuration
		case "local" : 
			$config["database"] = array(
				"servername" => 'localhost', 
				"username" => "root", 
				"password" => "root",
				"database" => "whym"
			);
			
			$config['facebook_secret'] = '71304a0e6f282f03280c799f10e5238e';

			$config["client"] = array(
				'base_url' => 'http://www.localhost.com/biz/whym/app',
				"facebook" => array(
					"appId" => '583531605136818',
				)
			);
		break;
	
		// development environment configuration
		case "prod" : 
			$config["database"] = array(
				"servername" => "localhost",
				"username" => "root", 
				"password" => "mFFf6rbfOh",
				"database" => "whym"
			);

			$config['facebook_secret'] = 'a58d2f9b42477bb9a207d601cd2078a5';

			$config["client"] = array(
				'base_url' => 'http://whymtech.com/',
				"facebook" => array(
					"appId" => '1590175971308287',
				)
			);


			
		break;

	}

	if($app == 'admin') $config['client']['base_url'] .= '/admin.php';

	
	// authentication url
	$perms = 'email,user_friends,user_about_me,user_location, user_birthday, pages_show_list';
	$config['client']['facebook']['perms'] = $perms;

	$config['client']['facebook']['auth_url'] = 
		'https://www.facebook.com/dialog/oauth?client_id=' . $config['client']['facebook']['appId']
		. '&redirect_uri=' . urlencode($config['client']['base_url'])
		. '&scope=' . $perms;


	$config['dictionary']['us_state_abbrevs_names'] = array(
		'AL'=>'ALABAMA',
		'AK'=>'ALASKA',
		'AZ'=>'ARIZONA',
		'AR'=>'ARKANSAS',
		'CA'=>'CALIFORNIA',
		'CO'=>'COLORADO',
		'CT'=>'CONNECTICUT',
		'DE'=>'DELAWARE',
		'DC'=>'DISTRICT OF COLUMBIA',
		'FL'=>'FLORIDA',
		'GA'=>'GEORGIA',
		'HI'=>'HAWAII',
		'ID'=>'IDAHO',
		'IL'=>'ILLINOIS',
		'IN'=>'INDIANA',
		'IA'=>'IOWA',
		'KS'=>'KANSAS',
		'KY'=>'KENTUCKY',
		'LA'=>'LOUISIANA',
		'ME'=>'MAINE',
		'MD'=>'MARYLAND',
		'MA'=>'MASSACHUSETTS',
		'MI'=>'MICHIGAN',
		'MN'=>'MINNESOTA',
		'MS'=>'MISSISSIPPI',
		'MO'=>'MISSOURI',
		'MT'=>'MONTANA',
		'NE'=>'NEBRASKA',
		'NV'=>'NEVADA',
		'NH'=>'NEW HAMPSHIRE',
		'NJ'=>'NEW JERSEY',
		'NM'=>'NEW MEXICO',
		'NY'=>'NEW YORK',
		'NC'=>'NORTH CAROLINA',
		'ND'=>'NORTH DAKOTA',
		'OH'=>'OHIO',
		'OK'=>'OKLAHOMA',
		'OR'=>'OREGON',
		'PA'=>'PENNSYLVANIA',
		'PR'=>'PUERTO RICO',
		'RI'=>'RHODE ISLAND',
		'SC'=>'SOUTH CAROLINA',
		'SD'=>'SOUTH DAKOTA',
		'TN'=>'TENNESSEE',
		'TX'=>'TEXAS',
		'UT'=>'UTAH',
		'VT'=>'VERMONT',
		'VI'=>'VIRGIN ISLANDS',
		'VA'=>'VIRGINIA',
		'WA'=>'WASHINGTON',
		'WV'=>'WEST VIRGINIA',
		'WI'=>'WISCONSIN',
		'WY'=>'WYOMING'
	);

	$config['dictionary']['months'] = array(
		'1' => 'JAN',
		'2' => 'FEB',
		'3' => 'MAR',
		'4' => 'APR',
		'5' => 'MAY',
		'6' => 'JUN',
		'7' => 'JUL',
		'8' => 'AUG',
		'9' => 'SEP',
		'10' => 'OCT',
		'11' => 'NOV',
		'12' => 'DEC'
	);
	
	global $whym_config;
	$whym_config = $config;
