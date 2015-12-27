<?php

	Class whymAPI {
		
		function __construct($config){
			$this -> config = $config;
			$this -> db = new RK_mysql($config['database']);
		}
	
		function printJS(){
			header('Content-type: text/javascript');
			echo 'var dictionary=' . json_encode($this -> config['dictionary']) . ';';
			echo 'var fb_config=' . json_encode($this -> config['facebook']) . ';';
			echo 'var socket_params=' . json_encode($this -> config['socket']) . ';';
			echo file_get_contents('client/whym.js');
		}
		
		function printCSS(){
			header('Content-type: text/css');
			echo file_get_contents('client/whym.css');
		}
		
		



		/************************************************************************************************
		*	API
		*	- loginUser
		*	- updateUser
		*	- postCheckin
		*	- listCheckins
		*
		************************************************************************************************/
	
		function loginUser(){
			extract($this -> request);
			
			// if it's a facebook user, get uid
			$uid = $this -> getUserFromFacebook($access_token);
			
			// PUT OTHER LOGIN MODES HERE
			
			// else - update date accessed
			$update['dateAccessed'] = date("Y-m-d H:i:s");
			$where['uid'] = $uid;
			$this -> db -> update($update, "users", $where);
			
			// store uid to session
			$_SESSION['uid'] = $uid;

			// return user record from database
			return array(
				"user" => $this -> _getUserByUid($uid)
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

			if($user['bio'] != ''){
				$user['isActive'] = 1;
				$user['isNew'] = 0;
			}

			$where = array('uid' => (int) $user['uid']);
			$this -> db -> update($user, "users", $where);

			return $this ->_getUserByUid($user['uid']);
		}
	
		function postCheckin(){
			extract($this -> request);

			// post checkin
			$newCheckin["time"] = date("Y-m-d H:i:s");
			$newCheckin["uid"] = $this -> uid;
			$checkinId = $this -> db -> insert($newCheckin, "checkins");
			
			// update users
			$update['lastCheckinId'] = $checkinId;
			$where['uid'] = $this -> uid;
			$this -> db -> update($update, "users", $where);
			print_r($update);
			print_r($where);

			return $this -> listCheckins();
		}

		function listCheckins(){
			extract($this -> request);
			$checkins = array();

			if(isset($search_params)) {
				$checkins = $this -> _getCheckins($search_params);
			}
			return array(
				"checkins" => $checkins
			);
		}

		function loadChat(){
			extract($this -> request);
			$me = (int) $this -> uid;
			$you = (int) $chat_request['partner'];

			$sql = "SELECT * FROM messages where (senderId=$me and targetId=$you) or (targetId=$me and senderId=$you) order by messageDate ASC";
			$conversation = $this -> db -> get_results($sql);


			$youFull = $this -> _getUserByUid($you);

			// GET RELATIONSHIP? (SKIP FOR NOW)
			//$sql = "SELECT * FROM relationships where "
			//$relationship 

			return array(
				"conversation" => $conversation,
				"partner" => $you
			);

		}

		function recordFriends(){
			extract($this -> request);
			$userId = $_SESSION['uid'];
			foreach($friends as $friendFbId){
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
				"message" => "Updated relationships for " . count($friends) . " people."
			);
		}


		/************************************************************************************************
		*	DATA PROCESSORS
		*	_getUserByFbId
		*	_getUserByUid
		*	_loadUser
		*	_getCheckins
		************************************************************************************************/
	
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

		function _getCheckins($search_params){

			extract($search_params);
			$where_strs = [];
			$orderBy = 'c.time desc';

			// handle sort order - postpone for now
			if(isset($sortOrder)) {
				switch($sortOrder) {
					case "abc" : 
						$orderBy = 'u.last_name asc';
					break;
				}
			}
			else {
				$orderBy = 'c.time desc';
			}

			if(isset($recents)){
				$where_strs[] = "r.lastMessageDate != '0000-00-00 00:00:00'";
				$orderBy = "r.lastMessageDate DESC";
			}
			else {



				if(isset($search_str) && $search_str != "") {
					$where_strs[] = "(u.first_name LIKE '%$search_str%' OR u.last_name LIKE '%$search_str%')";
				}

				if(isset($proximity) && $proximity != '' && isset($here)) {
					extract($here);

					// http://geography.about.com/library/faq/blqzdistancedegree.htm
					// Each degree of latitude is approximately 69 miles apart.
					// At 40° north or south (Portland is at 43 N), the distance between a degree of longitude is 53 miles.
					// So, LAT = +/- (.015 * R); LON = +/- (.02 * R)

					// if browser sends us geocoordinates - if not, maybe get from tracing IP?
					if(isset($lat) && isset($lon)) {
						$p = (int) $proximity;
						$where_strs[] = "c.lat >= " . (float) ($lat - ($p * .015));
						$where_strs[] = "c.lat <= " . (float) ($lat + ($p * .015));
						$where_strs[] = "c.lon >= " . (float) ($lon - ($p * .02));
						$where_strs[] = "c.lon <= " . (float) ($lon + ($p * .02));
					}
				}	

				if(isset($gender) && $gender != '') {
					$opts = array('male', 'female', 'other');
					if(in_array($gender, $opts)) $where_strs[] = "u.gender='$gender'";
				}		

				if(isset($age) && $age != '') {

					$year = (int) date("Y");

					$ageRange = explode('_', $age);

					
					if($ageRange[0] != ''){
						$ageMin = (int) $ageRange[0];
						$birthdayMax = ($year - $ageMin) . date("-m-d");
						$where_strs[] = "u.birthday <= '$birthdayMax'";	
					} 

					if($ageRange[1] != ''){
						$ageMax = (int) $ageRange[1];
						$birthdayMin = ($year - $ageMax) . date("-m-d");
						$where_strs[] = "u.birthday >= '$birthdayMin'";	
					}


				}		

				if(isset($justFriends) && $justFriends == 'true') {
					$where_strs[] = "r.isFriend=1";
				}

			}



			$whereString = implode(' AND ', $where_strs);

			$sql = 'SELECT c.*, u.uid, u.fbid, u.first_name, u.last_name, u.bio, u.birthday, r.numUnread, r.lastMessageDate
					FROM users u
					LEFT JOIN checkins c ON c.checkinid = u.lastCheckinId 
					LEFT JOIN relationships r ON r.selfId = ' . $_SESSION['uid'] . ' and r.otherId = u.uid 
					WHERE r.hasBlocked = false AND ' . $whereString . 
					' ORDER BY ' . $orderBy . ' LIMIT 40';
			
			//echo "\n\n $sql \n\n";

			$results = $this -> db -> get_results($sql);
			
			return $results;
		}
	
		/************************************************************************************************
		*	FACEBOOK
		*	- getUserFromFacebook($access_token)
		*
		************************************************************************************************/
	
		function getUserFromFacebook($access_token){
		
			// look user up server-side
			$url = 	'https://graph.facebook.com/me?access_token=' . $access_token . 
					"&fields=first_name,email,last_name,middle_name,location,birthday,gender";
			$userFromFacebook = json_decode(file_get_contents($url), true);
			
			// handle bad token
			if(!isset($userFromFacebook['id'])){
				 return array(
				 	"status" => "Failure",
				 	"message" => "Access Token Not Valid",
				 );
			}
			 

			// is user in database?
			$fbid = $userFromFacebook['id'];
			$userFromDB = $this -> _getUserByFbId($fbid);
			
			// if so, return the uid
			if($userFromDB) return $userFromDB['uid'];
			
			// if not - add user to database
			$newUser = $userFromFacebook;
			
			// - CREATE NEW USER OBJECT FROM FACEBOOK RETRIEVAL
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
		
			$uid = $this -> db -> insert($newUser, "users");
			return $uid;		
		
		}
	
	
		/************************************************************************************************
		*	SOCKET STUFF
		*	- postMessage($message)
		*
		************************************************************************************************/


		function postMessage($message){

		
			// open the payload
			extract($message); 

			$now = date("Y-m-d H:i:s");

		
			// get target's relationship with sender
			$rToSender = array(
				"selfId" => $targetId,
				"otherId" => $senderId
			);		
			$rToSenderRow = $this -> db -> getOrCreate($rToSender, "relationships");


			// bail if person has been blocked		
			if($rToSenderRow['hasBlocked']) {
				return array(
					"status" => "error",
					"error_message" => "target has blocked you"
				);
			}
		
			// record message
			$insert_id = $this -> db -> insert($message, "messages");
			$message = $this -> db -> get_rowFromObj(array("messageId" => $insert_id), "messages");
		
		
			// update relationship: target -> sender
			$update = array(
				"numUnread" => ($rToSenderRow['numUnread'] + 1),
				'lastMessageDate' => $now,
				"status" => "active"
			);
			$this -> db -> update($update, "relationships", $rToSender);
		

			// create / update relationship: sender -> target
			$rFromSender = array(
				"selfId" => $senderId,
				"otherId" => $targetId
			);
			$update = array(
				"status" => "active",
				"lastMessageDate" => $now,
				"lastCheckedDate" => $now
			);
			$this -> db -> updateOrCreate($update, "relationships", $rFromSender);

			return array(
				"status" => "success",
				"message" => $message
			);

		}
		
	
	
	
	}