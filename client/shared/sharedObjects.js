

// SAFE LOGGER
function logger(message){
	if('console' in window && 'log' in console){
		console.log(message)		
	}
}


// build out dictionary
dictionary.days = [];
for(var day = 1; day <= 31; day++) dictionary.days.push(day);

dictionary.years = [];
for(var year = 2015; year > 1915; year--) dictionary.years.push(year);



// define shared objects - these are the backbone of the app
var sharedObjects = {

	// ROOT CONTROLLER
	rootController : {

		init : function(){

			// load settings
			$scope.appName = 'whym';
			

			// initialize view state
			$scope.loaded = false;
			$scope.view = 'loading';
			$scope.header = {
				show : false
			}
			$scope.footer = {
				show : false
			}
			

			//  read state from cookie
			$scope.cookieMonster.load();



			var tmp = window.location.href.split('code=');
			var fb_code =  (tmp.length > 1) ? tmp[1] : false;
			

			// and launch app
			if($scope.sessionInCookie) {
				this.loadInitialView();
				$scope.apiClient.logIntoWhym(); // runs in the background
			}
			else if(fb_code){
				$scope.user = {
			 		fb_code : fb_code			 	
			 	}				
			 	$scope.apiClient.logIntoWhym();
			}
			else {
				this.loadView('login');
			}
		},

		// VIEW MANAGER / ROUTER
		loadView : function(view, screen){

			//logger("trying to load: " + view + ' - ' + screen)
			
			if(view == "loading" || view == "login") $scope.footer.show = false;
			
			// if the user is in the initial account set-up phase, don't show the bottom navigation
			else if('isNew' in $scope.user && parseInt($scope.user.isNew) == 1) $scope.footer.show = false;

			else $scope.footer.show = true;

			$scope.showSidebar = (view != "login");


			$scope.view = view;
			if(screen) $scope.screen = screen;

			// update footer view
			$scope.currentComponent = view;
		},

		// LOAD APP
		loadInitialView : function(){

			$scope.loaded = true;

			// load view - if new, load account interface, otherwise load feed
			if(appInterfaceName == "admin"){
				console.log($scope.user);
				if(parseInt($scope.user.lastOrganizationId) && 'activeOrganization' in $scope.user){
					$scope.org = $scope.user.activeOrganization;
					this.loadView('orgProfile');
				}
				else {
					this.loadView('adder', 'selectNew');
				}							
			}
			else {
				if(parseInt($scope.user.isNew)){
					this.loadView('account', 1);
				}
				else {
					// deep load
					this.loadView('search');
				}
			}

		}
	},


	// MANAGE COOKIE
	cookieMonster : {

		load : function(){

			// load user
			var user = localStorageService.get('user_' + appInterfaceName);		
			$scope.sessionInCookie = (user && 'isNew' in user);
			$scope.user = (user) ? user : {};
			
			
		},

		save : function(){

			if('isNew' in $scope.user) $scope.user.isNew = parseInt($scope.user.isNew);

			localStorageService.set('user_' + appInterfaceName, $scope.user);

		},

		clear : function(){
			localStorageService.set('user_' + appInterfaceName, {});
		}
	},
	

	// API CLIENT OBJECT
	apiClient : {
		
		postData : function(request, f){
			if("user" in $scope){
				request.uid = $scope.user.uid;
				request.access_token = $scope.user.fbAccessToken;
			} 
			if("org" in $scope && !("organizationId" in request)){
				request.organizationId = $scope.org.organizationId;
			}
			request.app = appInterfaceName;
			$.post('server/whym_api.php', request, function(response){
				if('error' in response){
					logger("API ERROR");
					logger(response);
				}
				else if(f) f(response);
				$scope.$digest();
			}, 'json');
		},

		logIntoFacebook : function(){
			window.location = whym_settings.facebook.auth_url;
		},

		logIntoWhym : function(){

			if(!("user" in $scope)){
				logger("tried to login to app without a user in the cookie");
				return;
			}

			//  now that we have an access token, renew server session (in the background)
			var request = {
				verb: "loginUser"
			}


			if(("fbAccessToken" in $scope.user)) {
				request.access_token = $scope.user.fbAccessToken;
			}
			else if("fb_code" in $scope.user){
				request.fb_code = $scope.user.fb_code;
			}
			else {
				logger("tried to login before user was logged into facebook");
				return;
			}


			$scope.apiClient.postData(request, function(response){

				// if their token doesn't validate on our server, log them out
				if("error" in response){ 
					$scope.cookieMonster.clear();
					$scope.rootController.loadView('login');
				}

				// else update session
				else {
					$scope.user = response.user;
					
					//if(parseInt($scope.user.isNew)) $scope.loaded = false;
					$scope.cookieMonster.save();
					
					// if you're just logging in...
					if(!$scope.loaded){
						$scope.rootController.loadInitialView();
					}

					$scope.cookieMonster.save();
				}

				$scope.$digest();
				
			});
		},

		logoutUser : function(){

			if($scope.view != 'login'){
				$scope.rootController.loadView('loading');	
			}

			var logout_url = 'https://www.facebook.com/logout.php?next=' + encodeURIComponent(whym_settings.base_url) + '&access_token=' + $scope.user.fbAccessToken;

			$scope.user = {};
			$scope.loaded = false;
			$scope.sessionInCookie = false;

			$scope.rootController.loadLoginScreen();

			$scope.cookieMonster.clear();	
			
			
			window.location = logout_url;
		}

	},


	// ACCOUNT VIEW CONTROLLER
	acctController : {
		
		needs : {
			first_name : false,
			last_name : false,
			email : false,
			bio : false
		},

		sending: false,

		loadLoginScreen : function(){
			$scope.header.show = false;
			$scope.footer.show = false;
			$scope.loadView('login');				
		},
		
		loadScreen : function(screenNumber){
			$scope.loadView('account', screenNumber)
		},

		saveAndProgress : function(){
			if(this.sending) return;

			$scope.screen = parseInt($scope.screen);

			// validate
			var validate = {
				1 : ['first_name', 'last_name', 'email', 'city'],
				2 : ['bio']
			}
			var fields = validate[$scope.screen];
			if(!Utilities.validate($scope.user, fields, $scope.acctController.needs)) return;

			
			// save updated user
			this.sending = true;
			var request = {
				user: $scope.user,
				verb: 'updateUser'
			}
			
			$scope.apiClient.postData(request, function(user){
				var wasNew = $scope.user.isNew;
				$scope.user = user;
				$scope.cookieMonster.save();

				// iterate to next screen
				$scope.acctController.sending = false;
				if(wasNew){
					$scope.screen++;
					if($scope.screen == 3) {
						//if()
						//$scope.organizationController
					}						
				}
				else {
					$scope.loadView('account', 'menu');
				}
			});
			
					
		}
	
	}
};


var Utilities = {
	
	getMapForPoint : function(point){
	
		// validate
		if(typeof point != 'object' || !('lat' in point) || !('lon' in point)){
			logger("invalid input"); console.log(point);
		}

		var map_url =
			"http://maps.googleapis.com/maps/api/staticmap?center=" 
			+ point.lat + ',' + point.lon + 
			"&zoom=14&size=300x200&sensor=false" +
			"&markers=color:blue|" + point.lat + "," + point.lon;
		return map_url;
	},

	validate : function(form, fields, needs){
		var goAhead = true;
		$.each(fields, function(fIndex, field_name){
			if(form[field_name] == '') {
				needs[field_name] = true;
				goAhead = false;
			}
			else needs[field_name] = false;
		});
		return goAhead;
	}
}
