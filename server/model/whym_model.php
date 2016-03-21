<?php

	Class whymModel {


		function __construct($config){
			$this -> config = $config;
			$this -> db = new RK_mysql($config['database']);
		}

		function handleError($message){
			$response['error'] = $message;
			exit(json_encode($response));
		}

		function validateInput($fields){
			foreach($fields as $f){
				if(!isset($this -> request[$f])) $this -> handleError("Invalid input.  Missing: " . $f);
			}
		}

		function validateUser(){
			$this -> validateInput(["access_token", "uid"]);
			extract($this -> request);

			$user = $this -> _getUserByUid($uid);

			if($user['fbAccessToken'] != $access_token || $access_token == '') {
				$response['error'] = "logged out";
				echo json_encode($response);
				exit();
			}

			$this -> uid = (int) $uid;
			$this -> user = $user;
		}


		function checkAdmin($uid, $orgId){
			$sql = 'SELECT * FROM signups where uid=' . (int) $uid . ' and organizationId=' . (int) $orgId . ' and status="admin"';
			$row = $this -> db -> get_row($sql);
			if(count($row) == 0) {
				$this -> handleError("You're trying to take an administrative action on an organization that you don't have access to.");
			}
		}




		/* USER ENDPOINTS */
		function loginUser(){

			// validate and extract input
			extract($this -> request);

			if(isset($fb_code)){
				$access_token = $this -> getAccessTokenFromCode($fb_code);
			}

			if(!isset($access_token)) $this -> handleError("missing access token");
			
			// if it's a facebook user, get uid
			$uid = $this -> getUserFromFacebook($access_token);
			$this -> uid = $uid;
			$this -> updateFriends($uid, $access_token);
			
			// PUT OTHER LOGIN MODES HERE

			// else - update date accessed
			$update['dateAccessed'] = date("Y-m-d H:i:s");
			$update['fbAccessToken'] = $access_token; // get updated access_token from Facebook
			
			$where['uid'] = $uid;
			$this -> db -> update($update, "users", $where);
			
			$user = $this -> _getUserByUid($uid);

			// if we're in the public world, get the user's orgs onload
			if($app == "public"){
				$search_params['mode'] = 'mine';
				$organizations = $this -> _getOrganizationsForUser($search_params);
				
				$user['organizations'] = $organizations;
			}
			
			
			// if we're in the admin world, figure out which orgs the admin has access to
			if($app == "admin"){
				$pages = $this -> getPagesFromFacebook($access_token);
				$user['orgs'] = array();
				$activeOrgs = array();
				if(count($pages) > 0){
					foreach($pages as $page){
						$org = $this -> _getOrganizationByFbId($page['organizationFbId']);
						if($org) {
							$user["orgs"][] = $org;
							$activeOrgs[$org['organizationId']] = true;
						}
						else $user['pages'][] = $page;
					}
				}


				// if the user was just looking at an organization, and they still have access to it, include information on it
				if($user['lastOrganizationId'] != 0 && isset($activeOrgs[$user['lastOrganizationId']])){
					$user["activeOrganization"] = $this -> _getOrganizationByOrganizationId($user['lastOrganizationId']);
				}

				// check relationships
				$sql = 'SELECT * FROM signups where status="admin" and uid=' . (int) $uid;
				$permissionsRaw = $this -> db -> get_results($sql);
				$permissions = array();
				foreach($permissionsRaw as $p) $permissions[$p['organizationId']] = $p;


				// define admin relationships for active organizations
				foreach($activeOrgs as $orgId => $org){

					if(!isset($permissions[$orgId])){

						$adminRelationship = array(
							"uid" => $uid,
							"organizationId" => $orgId,
							"status" => "admin"
						);
						$where = array(
							"uid" => $uid,
							"organizationId" => $orgId,
						);
					 	$this -> db -> updateOrCreate($adminRelationship, "signups", $where);
					}
					if(isset($permissions[$orgId])) unset($permissions[$orgId]);
				}


				// if no longer an admin, de-mote user 
				foreach($permissions as $orgId => $org){
					$update = array("status" => "deactivated");
					$where = array(
						"uid" => $uid,
						"organizationId" => $orgId,
					);
					$this -> db -> update($update, "signups", $where);
				}
			}
			
			// return user record from database
			return array(
				"user" => $user
			);
		}	

		function updateUser(){
			extract($this -> request);
			extract($user["birthday"]);
			$user['dateModified'] = date("Y-m-d H:i:s");
			$user['birthday'] = date("Y-m-d H:i:s", strtotime($year . '-' . $month . '-' . $day));

			// resolve discrepencies between client-side and server-side user models
			unset($user['here']);  // ToDo: store user's last location?
			unset($user['friendsList']);
			unset($user['unreadChatsCount']);
			unset($user['fbAccessToken']);
			unset($user['organizations']);
			unset($user['middle_name']);	// control which fields we get from facebook?



			if(isset($user['availability'])){
				$user['isActive'] = 1;
				$user['isNew'] = 0;

				// process availability

				unset($user['availability']);
			}

			$where = array('uid' => (int) $user['uid']);
			$this -> db -> update($user, "users", $where);
			$user = $this ->_getUserByUid($user['uid']); 
			

			return $user;
		}



		/* PUBLIC ENDPOINTS */
		function toggleSignup(){
			$this -> validateInput(array("organizationId", "signedup"));
			extract($this -> request);
			

			// GET \ CREATE SIGNUP OBJECT
			$relationship = $this -> db -> getOrCreate(array(
				"uid" => $this -> uid,
				"organizationId" => (int) $organizationId
			), "signups");


			// BOUNCE IF USER IS AN ADMIN
			if($relationship['status'] == 'admin') {
				$this -> handleError("Can't toggle status.  User is an administrator for this organization");
			}


			// DEFINE NEW STATUS
			$relationship["status"] = ($signedup == "true") ? "signed up" : "inactive";
			$this -> db -> update(
				array("status" => $relationship["status"]), 
				"signups", 
				array("signupId" => $relationship["signupId"])
			);


			// RETURN UPDATED RELATIONSHIP
			return $relationship;
 		}

		function searchForOrganizations(){
			extract($this -> request);
			$this -> validateInput(array("search_parameters"));
			return array(
				"organizations" => $this -> _getOrganizationsForUser($search_parameters)
			);
		}

		function _getOrganizationsForUser($search_parameters){
			extract($search_parameters);

			// BUILD WHERE STRING
			$where = '';
			$joinBoolean = '';
			$mode = isset($mode) ? $mode : false;
			if($mode == "mine"){
				$joinBoolean .= ' (s.status = "signed up" OR s.status = "admin")';
				$where .= "s.status IS NOT null";
			}
			if($mode == "searchNew"){
				$where .= ' ( (s.status <> "signed up" AND s.status <> "admin") OR s.status IS null)';
			}
			if($where != '') $where = ' AND ' . $where;
			if($joinBoolean != '') $joinBoolean = ' AND ' . $joinBoolean;

			// query
			$fields = 'o.*';
			$sql = "SELECT $fields, s.status, s.lastChecked FROM organizations o
					LEFT JOIN signups s ON s.organizationId = o.organizationId AND s.uid = " . $this -> uid .  $joinBoolean .
					" WHERE o.organizationStatus = 'active' " . $where .
					" LIMIT 40";

			$organizations = $this -> db -> get_results($sql);

			//echo $sql; exit;

			// get inbox counts
			if($mode == "mine"){
				foreach($organizations as $k => $o){
					$sql = 	'SELECT COUNT(*) FROM updates WHERE orgId=' . (int) $o['organizationId'] . 
							' AND publish_date > "' . $o['lastChecked'] . '" AND status="active"';
					$organizations[$k]['newUpdates'] = $this -> db -> get_var($sql);
				}
			}
			
			return $organizations; 	

		}

		function getOrganization(){
			$this -> validateInput(array("organizationId", "client_time"));
			extract($this -> request);
			$orgId = (int) $organizationId;


			// fetch organization
			$sql = 'SELECT * FROM organizations where organizationId=' . $orgId;
			$organization = $this -> db -> get_row($sql);
			if(!$organization) $this -> handleError("Sorry.  No organization found for that ID");

			// get photos
			$sql = 'SELECT * FROM photos where orgId=' . $orgId;
			$organization['photos'] = $this -> db -> get_results($sql);

			// get updates
			$sql = 'SELECT * FROM updates u
					LEFT JOIN events e ON u.event_id = e.event_id
					WHERE u.orgId = ' . $orgId . 
					' ORDER BY u.publish_date DESC';

			$organization['updates'] = $this -> _parseUpdatesList($this -> db -> get_results($sql));


			// update user
			$update = array("lastChecked" => $client_time);
			$where = array(
				"uid" => $this -> uid,
				"organizationId" => $organizationId
			);
			$this -> db -> update($update, "signups", $where);


			return array(
				"organization" => $organization
			);

		}



		/* ADMIN ENDPOINTS */
		function AdminGetOrganization(){
			extract($this -> request);
			$sql = 'SELECT * FROM organizations where organizationId=' . (int) $organizationId;
			$org = $this -> db -> get_row($sql);

			// update user - so it'll re-open here
			$update = array(
				"lastOrganizationId" => (int) $organizationId
			);
			$where = array(
				"uid" => $this -> uid
			);
			$this -> db -> update($update, "users", $where);


			return array(
				"organization" => $org
			);
		}

		function AdminUpdateOrganization(){
			extract($this -> request);
			if($org["organizationId"] != $organizationId) {
				$this -> handleError("tried to modify organization that you don't have access to.");
			}
			$this -> db -> update($org, "organizations", array("organizationId" => $organizationId));
			return $this -> AdminGetOrganization();	
		}

		function createOrganization(){

			// validate and extract input
			//parent::validate(["access_token", "organizationFbId"]);
			extract($this -> request);
			extract($org);

			// verify parameters: $organizationFbId, $access_token

			// verify that the organization does not already exist
			if($this -> _getOrganizationByFbId($organizationFbId)){
				$this -> handleError("Looks like an organization already exists for that page.");	
			} 

			
			// verify that the user has access to the page via fb
			$target_page = false;
			$pages = $this -> getPagesFromFacebook($access_token);
			if(count($pages) > 0){
				foreach($pages as $page){
					if($page["organizationFbId"] == $organizationFbId){
						$target_page = $page;
					}
				}
			}
			if(!$target_page) $this -> handleError("You don't appear to have permission to that page.");


			// Yay!  No org yet and user has access!
			$target_page['organizationStatus'] = 'active';
			$orgId = $this -> db -> insert($target_page, "organizations");


			// record permission immediately
			$adminRelationship = array(
				"uid" => $this -> uid,
				"organizationId" => $orgId,
				"status" => "admin"
			);
			$where = array(
				"uid" => $uid,
				"organizationId" => $orgId,
			);
		 	$this -> db -> updateOrCreate($adminRelationship, "signups", $where);


			return array(
				"organization" => $this -> _getOrganizationByOrganizationId($orgId)
			);
		}

		function AdminGetPeople(){
			$sql = 	'SELECT u.fbId, u.first_name, u.last_name, s.status from users u, signups s ' . 
					'where u.uid=s.uid AND (s.status="signed up" or s.status="admin")' .
					' AND s.organizationId=' . (int) $this -> request['organizationId'];

			$people = $this -> db -> get_results($sql);

			return array(
				"people" => $people
			);
		}
		

		/*************************************************************************************************
		*	PHOTOS
		*
		*************************************************************************************************/

		function AdminGetPhotosForOrg(){
			extract($this -> request);
			$sql = 'SELECT * FROM photos where orgId=' . $organizationId . ' ORDER BY photoId DESC';
			return array(
				"photos" => $this -> db -> get_results($sql)
			);
		}

		function AdminGetPhotosFromFacebook(){
			$this -> validateInput(array("url"));
			extract($this -> request);
			if(strpos($url, "https://graph.facebook.com/") !== 0) $this -> handleError("requested illegal url");
			$response = file_get_contents($url);

			return array(
				"photoData" => json_decode($response, true)
			);
		}

		function AdminSelectPhoto(){
			$this -> validateInput(array('photo'));
			extract($this -> request);

			$photo = array(
				"photoFbId" => (int) $photo['photoFbId'],
				"orgId" => (int) $photo['orgId'],
				"caption" => removeUrlsFromString($photo['caption'])
			);

			$this -> db -> insert($photo, 'photos');

			unset($photo['caption']);
			return $this -> db -> get_FromObj($photo, 'photos');
		}

		function AdminRemovePhoto(){
			$this -> validateInput(array("photoFbId"));
			extract($this -> request);
			$where = array(
				"photoFbId" => (int) $photoFbId,
				"orgId" => $organizationId
			);
			$this -> db -> delete($where, "photos");
			return $this -> AdminGetPhotosForOrg();
		}

		function AdminUpdateCaptions(){
			$this -> validateInput(array('photos'));
			extract($this -> request);
			foreach($photos as $photo){
				$where = array("photoId" => $photo['photoId']);
				$update = array("caption" => $photo['caption']);
				$this -> db -> update($update, "photos", $where);
			}
			return $this -> AdminGetPhotosForOrg();
		}


		/*************************************************************************************************
		*	UPDATES
		*
		*************************************************************************************************/

		function AdminPostUpdate(){
			$this -> validateInput(array('newUpdate'));
			extract($this -> request);

			$event = $newUpdate['event'];
			unset($newUpdate['event']);


			// SAVE UPDATE
			$newUpdate['status'] = 'active';
			$newUpdate['orgId'] = $organizationId;
			unset($newUpdate['$$hashKey']);
			if(!isset($newUpdate['updateId'])){
				$updateId = $this -> db -> insert($newUpdate, 'updates');
			}
			else {

				// IF YOU'RE EDITING, MAKE SURE IT EXISTS...
				$where = array('updateId' => $newUpdate['updateId']);
				$oldRow = $this -> db -> get_FromObj($where, 'updates');
				if(count($oldRow) == 0 || $oldRow['orgId'] != $organizationId) {
					$this -> handleError("The update you are trying to edit either does not exist or you do not have access to it.");
				}

				// AND SAVE
				$updateId = $newUpdate['updateId'];
				$this -> db -> update($newUpdate, 'updates', $where);
			}


			// HANDLE UPDATES ASSOCIATED WITH EVENTS
			if($event != "false"){

				// INSERT OR UPDATE EVENT
				unset($event['$$hashKey']);
				$event['orgId'] = $organizationId;
				$where = array("event_FbId" => $event['event_FbId']);
				$eventId = $this -> db -> updateOrCreate($event, 'events', $where, "event_id");


				// UPDATE POST TO LINK TO EVENT
				$where = array('updateId' => $updateId);
				$update = array("event_id" => $eventId);
				$this -> db -> update($update, 'updates', $where);
			}


			
			

			return $this -> AdminGetUpdates();
		}

		function AdminDeleteUpdate(){
			$this -> validateInput(array('updateId', 'edit_date'));
			extract($this -> request);
			
			// IF YOU'RE EDITING, MAKE SURE IT EXISTS...
			$where = array('updateId' => $updateId);
			$oldRow = $this -> db -> get_FromObj($where, 'updates');
			if(count($oldRow) == 0 || $oldRow['orgId'] != $organizationId) {
				$this -> handleError("The update you are trying to edit either does not exist or you do not have access to it.");
			}

			// AND SAVE
			$oldRow['status'] = 'deleted';
			$oldRow['edit_date'] = $edit_date;
			$this -> db -> update($oldRow, 'updates', $where);
			return array("status" => "success");
		}

		function AdminGetUpdates(){
			extract($this -> request);
			
			$sql = 'SELECT * FROM updates u 
					LEFT JOIN events e ON u.event_id = e.event_id
					WHERE u.orgId=' . $organizationId . ' AND u.status = "active"
					ORDER BY u.updateId DESC LIMIT 100';


			// PROCESS UPDATES LIST INTO COMPLEX DATA OBJECTS
			$updatesRaw = $this -> db -> get_results($sql);
			return array(
				"updates" => $this -> _parseUpdatesList($updatesRaw)
			);
		}

		function AdminGetUpdatesFromFB(){
			$this -> validateInput(array("update_type"));
			extract($this -> request);

			$org = $this -> _getOrganizationByOrganizationId($organizationId);
			$organizationFbId = $org['organizationFbId'];


			switch($update_type){
				case "Page Feed" :
					$url = "https://graph.facebook.com/" . $organizationFbId . "/posts?access_token=" . $access_token;
					$response = file_get_contents($url);
					$feedRaw = json_decode($response, true);
					$response = array();
					foreach($feedRaw['data'] as $feedItem){
						if(!isset($feedItem['message'])) continue;

						$message = $feedItem['message'];
						$url = getFirstUrl($message);
						$message = removeUrlsFromString($message);

						$update = array(
							"body" => $message,
							"url" => $url
						);
						$response[] = $update;
					}
					return array(
						"feed" => $response
					);

				break;

				case "Event" :
					$url = "https://graph.facebook.com/" . $organizationFbId . "/events?access_token=" . $access_token;
					$response = file_get_contents($url);
					$eventsRaw = json_decode($response, true);
					$response = array();

					foreach($eventsRaw['data'] as $event){
					
						$dateObj = DateTime::createFromFormat(DateTime::ISO8601, $event['start_time']);

						$event = array(
							"event_title" => $event['name'],
							"event_location" => $event['place']['name'],
							"event_dateStr" => $dateObj -> format("F j at g:ia"),
							"event_dateISO" => $event['start_time'],
							"event_FbId" => $event['id']
						);
						$response[] = $event;
					}

					return array(
						"events" => $response
					);
				break;

				default:
					$this -> handleError("Requested illegal update type.");
				break;
			}
		}

		function _parseUpdatesList($updatesRaw){
			$updates = array();
			foreach($updatesRaw as $index => $updateRaw){
				$update = array();
				foreach($updateRaw as $field => $value){
					if(strpos($field, "event_") !== 0){
						$update[$field] = $value;
					}
					else $update['event'][$field] = $value;
				}
				if($updateRaw['event_id'] == null) unset($update['event']);

				// PROCESS DATES
				$update['publish_date'] = parseMysqlToDateString($update['publish_date']);
				$update['edit_date'] = parseMysqlToDateString($update['edit_date']);


				$updates[] = $update;
			}
			return $updates;
		}

			


		/************************************************************************************************
		*	FACEBOOK
		*	- getUserFromFacebook($access_token)
		*
		************************************************************************************************/
	
		function _logOutFromFacebook($fb_access_token){
			extract($this -> config);
			
			$api_response = file_get_contents($url);

			if(strpos($api_response, '<!DOCTYPE html>') == 0){
				$response['status'] = 'success';
			}
			else $response['error'] = "FAILURE TO LOG OUT \n\n" . $api_response;

			return $response;
		}

		function getAccessTokenFromCode($fb_code){
			extract($this -> config);

		
			$url = 	'https://graph.facebook.com/v2.5/oauth/access_token?' . 
					'client_id=' . $client['facebook']['appId'] .
					'&redirect_uri=' . urlencode($client['base_url']) . 
					'&client_secret=' . $facebook_secret . 
					'&code=' . $fb_code;

			$response = file_get_contents($url);

			if(!$response || strpos($response, 'access_token') === false) {

				$error = 	"Failed to convert code into access token: \n\n" .
							$url . "\n\n" .
							$response;

				$this -> handleError($error);

			}


			$access_token_data = json_decode($response);

			$access_token = $access_token_data -> access_token;


			// else, get an extended access token
			$url = 	'https://graph.facebook.com' . 
					'/oauth/access_token?grant_type=fb_exchange_token' .
					'&client_id=' . $this -> config['client']["facebook"]["appId"] .
					'&client_secret=' . $this -> config["facebook_secret"] . 
					'&fb_exchange_token=' . $access_token;

			$response = file_get_contents($url);

			if(!$response || strpos($response, 'error') !== false) {
				$error = 	"Unable to get extended access token. \n\n" .
							$url . "\n\n" .
							$response;

				$this -> handleError($error);
			}


			$tmp = explode('&', $response);
			$tmp = $tmp[0];
			$tmp = explode('=', $tmp);
			$longevity_token = $tmp[1];

			return $longevity_token;
		}

		function getUserFromFacebook($access_token){
		
			// look user up server-side
			$url = 	'https://graph.facebook.com/me?access_token=' . $access_token . 
					"&fields=first_name,email,last_name,middle_name,location,birthday,gender";

			$response = file_get_contents($url);

			// handle bad token
			if(!$response || strpos($response, 'error') !== false) {
				$this -> handleError("Bad access token.");
			}			
			
			$userFromFacebook = json_decode($response, true);

			 

			// is user in database?
			$fbid = $userFromFacebook['id'];
			
			
			// Are they already in the database?
			$userFromDB = $this -> _getUserByFbId($fbid);


			// if user in database, return the uid
			if($userFromDB) return $userFromDB['uid'];
			
			


			// IF NOT - CREATE NEW USER OBJECT FROM FACEBOOK RETRIEVAL
			$newUser = $userFromFacebook;

			$newUser['fbAccessToken'] = $access_token;
			
			if(isset($newUser['birthday'])) {
				$newUser["birthday"] = date("Y-m-d H:i:s", strtotime($newUser["birthday"]));
			}
		
			if(isset($newUser['location'])) {
				$location = explode(',', $newUser['location']['name'] );
				$newUser['city'] = $location[0];
				$newUser['state'] = trim(strtoupper($location[1]));
				unset($newUser['location']);
			}
			
			if(isset($newUser['gender'])) {
				$newUser['gender'] = strtoupper($newUser['gender']);
			}

			$newUser['fbid'] = $newUser['id'];
			unset($newUser['id']);
			$newUser['dateCreated'] = date("Y-m-d H:i:s");
			unset($newUser['middle_name']);	// control which fields we get from facebook?

			$uid = $this -> db -> insert($newUser, "users");
			return $uid;				
		}

		function updateFriends($userId, $access_token){

			// update friends list
			$url = 'https://graph.facebook.com/me/friends?limit=5000&access_token=' . $access_token;
			$response = file_get_contents($url);
			if(!$response || strpos($response, 'error') !== false) {
				$this -> handleError("Couldn't get friends list.", true);
			}
			$friendsResponse = json_decode($response, true);
			$friendsList = array();
			foreach($friendsResponse['data'] as $friend){
				$friendsList[] = $friend['id'];
			}

			foreach($friendsList as $friendFbId){

				$friend = $this -> _getUserByFbId($friendFbId);

				$friendId = (int) $friend['uid']; 
				$update = array("isFriend" => 1);
				
				$where = array(
					"selfId" => $userId,
					"otherId" => $friendId
				);
				$this -> db -> updateOrCreate($update, "relationships", $where);

				$where = array(
					"selfId" => $friendId,
					"otherId" => $userId
				);

				$this ->  db -> updateOrCreate($update, "relationships", $where);
			}

			return array(
				"message" => "Updated relationships for " . count($friendsList) . " people."
			);
		}


		function getPagesFromFacebook($user_accessToken){
			$url = 'https://graph.facebook.com/me/accounts?summary=total_count&access_token=' . $user_accessToken;

			$pagesFromFacebook = json_decode(file_get_contents($url), true);


			$response = array();
			foreach($pagesFromFacebook['data'] as $org){

				// if the person doesn't have basic administrative privileges, don't allow them to use it in whym
				if(!in_array("BASIC_ADMIN", $org['perms'])) continue;

				$response[] = array(
					"organizationName" => $org['name'],
					"organizationFbId" => $org["id"]
				);
			}

			return $response;
		}




		/****************************************************************************************************
		*	DATA MODEL
		*	- simple private getters and data processors
		*
		****************************************************************************************************/

		function _getUserByFbId($fbid){
			$sql = 'SELECT * FROM users where fbid=' . (int) $fbid;
			$userFromDB = $this -> db -> get_row($sql);
			return ($userFromDB) ? $this -> _loadUser($userFromDB) : false;
		}

		function _getUserByUid($uid){
			$sql = 'SELECT * FROM users where uid=' . (int) $uid;
			$userFromDB = $this -> db -> get_row($sql);
			return ($userFromDB) ? $this -> _loadUser($userFromDB) : false;
		}
	
		function _loadUser($user){
		
			$birthdayUnix = strtotime($user['birthday']);
		
			if($birthdayUnix != 0){
				$tmp = explode(' ', $user['birthday']);
				$tmp = $tmp[0];
				$tmp = explode('-', $tmp);
				$user['birthday'] = array(
					"year" => (int) $tmp[0],
					"month" => (int) $tmp[1],
					"day" => (int) $tmp[2],
					"string" => date("M d, Y", $birthdayUnix)
				);
			}
			else {
				$user['birthday'] = array(
					"year" => 0,
					"month" => 0,
					"day" => 0,
					"string" => ""
				);
			}

			return $user;
		}

		function _getOrganizationByFbId($fbId){
			$sql = 'SELECT * FROM organizations where organizationFbId=' . (int) $fbId;
			$orgFromDB = $this -> db -> get_row($sql);
			return $orgFromDB;	
		}

		function _getOrganizationByOrganizationId($orgId){
			$sql = 'SELECT * FROM organizations where organizationId=' . (int) $orgId;
			$orgFromDB = $this -> db -> get_row($sql);
			return $orgFromDB;	
		}


	
	}


	// UTILITIES

	// remove urls from strings (cleans txt and captions from FB)
	function getFirstUrl($str) {
 		$U = explode('http', $str);
 		if(count($U) == 1) return "";
 		foreach($U as $k => $link){
 			if($k == 0) continue;
 			$u = explode(' ', $link);
 			$link = $u[0];
 			return 'http' . $link;
 		}
	}

	function removeUrlsFromString($str) {
 		$U = explode('http', $str);
 		if(count($U) == 1) return $str;
 		$newStr = '';
 		foreach($U as $k => $chunk){
 			if($k == 0) {
 				$newStr .= $chunk;
 				continue;
 			}
 			$u = explode(' ', $chunk);
 			unset($u[0]);
 			foreach($u as $txt){
 				$newStr .= ' ' . $txt;
 			}
 		}
 		return $newStr;
	}

	function unitTestUrlFunctions(){
		$test_strings = array(
			"http://www.google.com is a great site.",
			"I lovehttp://www.google.com because it's a great site.",
			"I lovehttp://www.google.com because it's a great site, so's http://www.google.com",
			"I lovehttp://www.google.com"
		);

		foreach($test_strings as $str){
			echo $str . "<br />";
			echo "First Link: " . getFirstUrl($str) . "<br />";
			echo "URL Free: " . removeUrlsFromString($str) . "<br /><br />";
		}
	}



	// RANDOM DATE SHIT
	function parseMysqlToDateString($mysqlDate){
		if($mysqlDate == '0000-00-00 00:00:00') return '';
		$date = DateTime::createFromFormat('Y-m-d H:i:s', $mysqlDate);
		if(!$date) return '';
		return $date -> format("F j") . ' at ' . $date -> format("g:ia");
	}


