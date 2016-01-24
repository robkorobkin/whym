<?php

	Class whymAdminAPI extends whymAPI {
		
		function printJS(){
			parent::printJS();
			echo file_get_contents('../client/whym_admin.js');
		}
		
		function printCSS(){
			parent::printCSS();
			echo file_get_contents('../client/whym_admin.css');
		}		
	
		function loginUser(){

			// validate and extract input
			parent::validate(["access_token"]);
			extract($this -> request);
			

			$user = parent::loginUser();

			$pages = parent::getPagesFromFacebook($access_token);
			if(count($pages) > 0){
				foreach($pages as $page){
					$org = $this -> getOrganizationByFbId($page['organizationFbId']);
					if($org) $user["orgs"][] = $org;
				}
			}
			
			return $user;
		}

		function getPagesForUser(){

			// validate and extract input
			parent::validate(["access_token"]);
			extract($this -> request);

			$response = array();

			$pages = parent::getPagesFromFacebook($access_token);
			if(count($pages) > 0){
				foreach($pages as $page){
					$org = $this -> getOrganizationByFbId($page['organizationFbId']);
					if(!$org) $response[] = $page;
				}
			}

			return $response;
		}


		function createOrganization(){

			// validate and extract input
			parent::validate(["access_token", "organizationFbId"]);
			extract($this -> request);


			// verify that the organization does not already exist
			if($this -> getOrganizationByFbId($organizationFbId)){
				parent::fail("ERROR: Looks like an organization already exists for that page.");	
			} 

			
			// verify that the user has access to the page via fb
			$target_page = false;
			$pages = parent::getPagesFromFacebook($access_token);
			if(count($pages) > 0){
				foreach($pages as $page){
					if($page["organizationFbId"] == $organizationFbId){
						$target_page = $page;
					}
				}
			}
			if(!$target_page) parent::fail("ERROR: You don't appear to have permission to that page.");


			// Yay!  No org yet and user has access!
			$organizationId = $this -> db -> insert($target_page, "organizations");
			$_SESSION['permittedOrgs'][] = $organizationId;
			return $this -> getOrganizationByOrganizationId($organizationId);

		}


		function updateOrganization(){
			
			// validate and extract input
			parent::validate(["organization"]);
			extract($this -> request);


		}
		
	
	}