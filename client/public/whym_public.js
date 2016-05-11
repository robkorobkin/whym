
var appInterfaceName = "public";





var app = angular.module('whymApp', ['LocalStorageModule', 'ui.bootstrap', 'ngAnimate']);

app.controller('whymCtrl', ['$scope', '$http', '$sce', '$rootScope', '$window',  'localStorageService',
	function($scope, $http, $sce, $rootScope, $window, localStorageService){
		
		

		$scope.init = function(){


			// set window-level pointer to app scope
			$rootScope.appScope = $scope; 
			window.$rootScope = $rootScope; 
			window.localStorageService = localStorageService;


			// initialize view state
			$scope.loaded = false;
			$scope.view = 'loading';
			$scope.header = {
				show : false
			};
			$scope.footer = {
				show : false
			};


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
			//	$scope.GeoSystem = new GeoSystem($scope);

			
		}
		

		$scope.orgNavigator = {

			init : function(){
				this.search_parameters = {
					field : 'test',
					mode : 'mine'
				};
			},

			loadList : function(organizations){
				$scope.orgNavigator.orgs = organizations;


				switch(this.search_parameters.mode){
					case 'mine' : 
						$scope.pageHeader = 'My Organizations';
						break;
					case 'searchNew' : 
						$scope.pageHeader = 'Looking to volunteer?';
						break;
				}
				

				var label = '';

				$scope.orgNavigator.orgSets = [];
				var setNumber = -1;
				$.each($scope.orgNavigator.orgs, function(index, org){
					
					if(index % 3 == 0) {
						setNumber++;
						$scope.orgNavigator.orgSets[setNumber] = [];
					}
					$scope.orgNavigator.orgSets[setNumber].push(org);

				});

				$scope.rootController.loadView('list');
			},

			searchForOrganizations : function(mode){
				this.search_parameters.mode = (mode) ? mode :'searchNew';
				var request = {
					'verb' : 'searchForOrganizations',
					'search_parameters' : this.search_parameters
				}

				$scope.rootController.loadView('loading');
				$scope.screen = 'panels';
				

				$scope.apiClient.postData(request, function(response){
					$scope.orgNavigator.loadList(response.organizations);
				});
			},

			getMyOrganizations : function(){
				this.searchForOrganizations('mine');
			}


		}
		





		$scope.orgController = {
			openOrg : function(org){
				var request = {
					organizationId : org.organizationId,
					client_time : Utilities.getNowMysqlTime(),
					verb : 'getOrganization'
				}
				$scope.apiClient.postData(request, function(response){
					var organization = response.organization;

					for(var index in organization.updates){
						var update = organization.updates[index];
						update.trustedHtml = $sce.trustAsHtml(update.body.replace(/(?:\r\n|\r|\n)/g, '<br />'));

						if('event' in update){
							var event = update.event;
							event.url = 'https://www.facebook.com/events/' + event.event_FbId;
						}
						else update.event = false;

					}


					console.log(organization)
					
					$scope.orgController.loadOrg(organization);
				})
			},

			loadOrg : function(org){

				// figure out if the user has "signed up" for the organization
				org.signup.signedup = (org.signup.status == "signed up" || org.signup.status == "admin");
				

				// BUILD FEATURED PHOTOS OBJECT
				if(isMobile){
					org.featuredPhotos = [org.photos[0]];
				}
				else {
					var pList = [];
					for(var i = 0; i < 4; i++){
						if(i in org.photos){
							pList.push(org.photos[i]);
						}
					}
					org.featuredPhotos = pList;
				}


				// update the global pointer to the new organization
				$scope.org = org;
				$scope.update = org.updates[0];

				


				// load the view - maybe add some logic here?
				var screen = 'info';
				$scope.rootController.loadView('org', screen);
				this.showPrimary();
			},

			toggleSignup : function(){
				
				
				// don't toggle status if user is an administrator
				if($scope.org.status == "admin"){
					$scope.org.signup.signedup = true;
					return;	
				}


				// if we got here from clicking the label, flip the switch
				$scope.org.signup.signedup = !($scope.org.signup.signedup);

				// and fire the API!
				var request = {
				 	signedup : $scope.org.signup.signedup,
				 	verb : 'toggleSignup'
				}
				$scope.apiClient.postData(request, function(response){

					var new_list = [];
					$.each($scope.user.organizations, function(i, o){
						if(response.status == 'signed up' || o.organizationId != $scope.org.organizationId){
							new_list.push(angular.copy(o));
						}
					});
					if(response.status == 'signed up'){
						new_list.push($scope.org);
					}
					new_list.sort(function(a,b){
					 	return a.organizationName > b.organizationName;
					})


					$scope.user.organizations = new_list;


				});	
				
			},

			changeMode : function(mode){
				$scope.rootController.loadView('org', mode);
			},

			showSecondary : function(screen){
				$scope.screen = screen;
				$('.appFrame').scrollTop(0);
				$('.organization').animate({ marginLeft: '-100%' }, 250, "linear");
			},

			showPrimary : function(){
				$('.appFrame').scrollTop(0);
				$('.organization').animate({ marginLeft: '0' }, 250, "linear");
			}



		}
		
		
		$scope.init();

	}
	
])
.directive('orgupdate', function() {
  return {
    restrict: 'E',
    templateUrl: 'org_update.html'
  };
});
