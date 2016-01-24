





var app = angular.module('whymApp', ['LocalStorageModule']);

app.controller('whymCtrl', ['$scope', '$http', '$sce', '$rootScope', '$window',  'localStorageService',
	function($scope, $http, $sce, $rootScope, $window, localStorageService){
		
		

		$scope.init = function(){


			// IMPORT CONFIG INTO SCOPE
			$scope.api_path = api_settings.admin_path;
			$scope.dictionary = dictionary;
			$scope.fb_config = fb_config;
			$scope.appName = 'whym';


			// attach services to $scope for pass by reference
			$scope.$window = $window;


			// initialize view state
			$scope.loaded = false;
			$scope.view = 'loading';
			$scope.header = {
				show : false
			}
			$scope.footer = {
				show : false
			}


			// load map stuff
			$scope.GeoSystem = new GeoSystem($scope);

			
			// load user authenticator
			$scope.UserSystem = new FB_UserSystem($scope);
			
			
			//  load state from cookie - launches app
			$scope.cookieMonster.load();	
		}
		
		
		
		// API CLIENT OBJECT -- ToDo: PUT API PATH IN CONFIG
		$scope.apiClient = {
			
			postData : function(request, f){
				if("user" in $scope) request.uid = $scope.user.uid;
				$.post($scope.api_path, request, function(response){
					if('error' in response && response.error == "logged out"){
						 $scope.cookieMonster.clear();
					}
					else if(f) f(response);
				}, 'json');
			}
		
		}
		
		
		// COOKIE MONSTER - MAINTAIN STATE IN BETWEEN SESSIONS
		$scope.cookieMonster = {
			save : function(){
				$scope.user.here = here;
				localStorageService.set('user', $scope.user);
				if(!$scope.loaded) this.load();
			},
			load : function(){
				var user = localStorageService.get('user');
				if(user  && !$scope.loaded) {

					$scope.user = user;					
					$scope.loaded = true;
					
					if(parseInt($scope.user.isNew)){
						$scope.loadView('account');
					}
					else {
						
					}
					//$scope.$digest();
				}
			},
			clear : function(){
				localStorageService.set('user', false);
				$scope.user = false;
				$scope.loadView("login");
				$scope.$digest();
			}
		}
		

		// VIEW MANAGER / ROUTER
		$scope.loadView = function(view, screen){
			
			if(view == "loading" || view == "login") $scope.footer.show = false;
			else $scope.footer.show = true;
			
			$scope.view = view;
			if(screen) $scope.screen = screen;

			// update footer view
			$scope.currentComponent = view;
			
		}

		
	

		
		
		
		
		$scope.init();

	}
	
]);

