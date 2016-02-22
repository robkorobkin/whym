
var appInterfaceName = "public";





var app = angular.module('whymApp', ['LocalStorageModule']);

app.controller('whymCtrl', ['$scope', '$http', '$sce', '$rootScope', '$window',  'localStorageService',
	function($scope, $http, $sce, $rootScope, $window, localStorageService){
		
		

		$scope.init = function(){


			// set window-level pointer to app scope
			window.$scope = $scope; 
			window.localStorageService = localStorageService;


			// initialize view state
			$scope.loaded = false;
			$scope.view = 'loading';
			$scope.header = {
				show : false
			}
			$scope.footer = {
				show : false
			}


			// set scope-level pointers to config and dictionary (for view)
			$scope.dictionary = dictionary;


			// DIY DEPENDENCY INJECTION - NICE AND SIMPLE
			for(var objName in sharedObjects){
				$scope[objName] = sharedObjects[objName];
			}

			// INIT ORG POINTER
			$scope.org = false; 
			$scope.orgNavigator.init();

			$scope.rootController.loadLoginScreen = function(){
				this.loadView('login');
				$scope.showSidebar = false;
			}

			$scope.rootController.init();




			// load map stuff
//			$scope.GeoSystem = new GeoSystem($scope);

			
		}
		

		$scope.orgNavigator = {

			init : function(){
				this.search_parameters = {};
			},

			searchForOrganizations : function(){
				var request = {
					'verb' : 'searchForOrganizations',
					'search_parameters' : this.search_parameters
				}

				$scope.rootController.loadView('loading');

				$scope.apiClient.postData(request, function(response){
					$scope.orgNavigator.orgs = response.organizations;
					$scope.rootController.loadView('list');
				});
			},

			getMyOrganizations : function(){
				var request = {
					'verb' : 'searchForOrganizations',
					'search_parameters' : {
						mode : 'mine'
					}
				}

				this.mode = 'mine';

				$scope.rootController.loadView('loading');

				$scope.apiClient.postData(request, function(response){
					$scope.orgNavigator.orgs = response.organizations;
					$scope.rootController.loadView('list');
				});
			}


		}
		
		$scope.orgController = {
			openOrg : function(org){
				
				// figure out if the user has "signed up" for the organization
				org.signup = {
					signedup : (org.status == "signed up" || org.status == "admin")
				}

				// update the global pointer to the new organization
				$scope.org = org;


				// load the view - maybe add some logic here?
				var screen = 'info';
				$scope.rootController.loadView('org', screen);
			},

			toggleSignup : function(flip){
				
				// don't toggle status if user is an administrator
				if($scope.org.status == "admin"){
					$scope.org.signup.signedup = true;
					return;	
				}


				// if we got here from clicking the label, flip the switch
				if(flip) $scope.org.signup.signedup = !($scope.org.signup.signedup);


				// and fire the API!
				var request = {
				 	signedup : $scope.org.signup.signedup,
				 	verb : 'toggleSignup'
				}
				$scope.apiClient.postData(request, function(response){
					logger(response);
				});
			}


		}
		
		
		$scope.init();

	}
	
]);

