

// IS MOBILE
$(function(){
	isMobile = ($(window).width() < 768);
})



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

var questionnaire = [
	{
		field : 'bio',
		text : 'Who are you?'
	}
];





// define shared objects - these are the backbone of the app
var sharedObjects = {

	// ROOT CONTROLLER
	rootController : {

		init : function(){

			// load settings
			$rootScope.appScope.appName = 'whym';
			

			// initialize view state
			$rootScope.appScope.loaded = false;
			$rootScope.appScope.view = 'loading';
			$rootScope.appScope.header = {
				show : false
			}
			$rootScope.appScope.footer = {
				show : false
			}
			

			//  read state from cookie
			$rootScope.appScope.cookieMonster.load();



			var tmp = window.location.href.split('code=');
			var fb_code =  (tmp.length > 1) ? tmp[1] : false;
			

			// and launch app
			if($rootScope.appScope.sessionInCookie) {
				this.loadInitialView();
				$rootScope.appScope.apiClient.logIntoWhym(); // runs in the background
			}
			else if(fb_code){
				$rootScope.appScope.user = {
			 		fb_code : fb_code			 	
			 	}				
			 	$rootScope.appScope.apiClient.logIntoWhym();
			}
			else {
				this.loadView('login');
			}
		},

		// VIEW MANAGER / ROUTER
		loadView : function(view, screen){

			//logger("trying to load: " + view + ' - ' + screen)
			
			if(view == "loading" || view == "login") $rootScope.appScope.footer.show = false;
			
			// if the user is in the initial account set-up phase, don't show the bottom navigation
			else if('isNew' in $rootScope.appScope.user && parseInt($rootScope.appScope.user.isNew) == 1) $rootScope.appScope.footer.show = false;

			else $rootScope.appScope.footer.show = true;

			$rootScope.appScope.showSidebar = (view != "login");


			$rootScope.appScope.view = view;
			if(screen) $rootScope.appScope.screen = screen;

			// update footer view
			$rootScope.appScope.currentComponent = view;
		},

		// LOAD APP
		loadInitialView : function(){

			$rootScope.appScope.loaded = true;

			// load view - if new, load account interface, otherwise load feed
			if(appInterfaceName == "admin"){
				console.log($rootScope.appScope.user);
				if(parseInt($rootScope.appScope.user.lastOrganizationId) && 'activeOrganization' in $rootScope.appScope.user){
					$rootScope.appScope.org = $rootScope.appScope.user.activeOrganization;
					this.loadView('orgProfile');
				}
				else {
					this.loadView('adder', 'selectNew');
				}							
			}
			else {
				var user = $rootScope.appScope.user;

				if(parseInt(user.isNew)){
					this.loadView('account', 1);
				}
				else {
					if('organizations' in user){
						var orgs = user.organizations;
						$rootScope.appScope.orgNavigator.loadList(orgs);
					} 
				}
			}

		}
	},


	// MANAGE COOKIE
	cookieMonster : {

		load : function(){

			// load user
			var user = localStorageService.get('user_' + appInterfaceName);		
			$rootScope.appScope.sessionInCookie = (user && 'isNew' in user);
			$rootScope.appScope.user = (user) ? user : {};
			
			
		},

		save : function(){

			if('isNew' in $rootScope.appScope.user) $rootScope.appScope.user.isNew = parseInt($rootScope.appScope.user.isNew);

			localStorageService.set('user_' + appInterfaceName, $rootScope.appScope.user);

		},

		clear : function(){
			localStorageService.set('user_' + appInterfaceName, {});
		}
	},
	

	// API CLIENT OBJECT
	apiClient : {
		
		postData : function(request, f){

			console.log('hello')

			if("user" in $rootScope.appScope){
				request.uid = $rootScope.appScope.user.uid;
				request.access_token = $rootScope.appScope.user.fbAccessToken;
			} 
			if("org" in $rootScope.appScope && !("organizationId" in request)){
				request.organizationId = $rootScope.appScope.org.organizationId;
			}
			request.app = appInterfaceName;
			var api_path = (appInterfaceName == 'admin') ? '../server/whym_api.php' : 'server/whym_api.php';
			$.post(api_path, request, function(response){
				if('error' in response){
					logger("API ERROR");
					logger(response);
				}
				else if(f) f(response);
				$rootScope.appScope.$digest();
			}, 'json');
		},

		logIntoFacebook : function(){
			window.location = whym_settings.facebook.auth_url;
		},

		logIntoWhym : function(){

			if(!("user" in $rootScope.appScope)){
				logger("tried to login to app without a user in the cookie");
				return;
			}

			//  now that we have an access token, renew server session (in the background)
			var request = {
				verb: "loginUser"
			}


			if(("fbAccessToken" in $rootScope.appScope.user)) {
				request.access_token = $rootScope.appScope.user.fbAccessToken;
			}
			else if("fb_code" in $rootScope.appScope.user){
				request.fb_code = $rootScope.appScope.user.fb_code;
			}
			else {
				logger("tried to login before user was logged into facebook");
				return;
			}


			$rootScope.appScope.apiClient.postData(request, function(response){

				// if their token doesn't validate on our server, log them out
				if("error" in response){ 
					$rootScope.appScope.cookieMonster.clear();
					$rootScope.appScope.rootController.loadView('login');
				}

				// else update session
				else {
					$rootScope.appScope.user = response.user;
					$rootScope.appScope.acctController.loadUser();
					
					//if(parseInt($rootScope.appScope.user.isNew)) $rootScope.appScope.loaded = false;
					$rootScope.appScope.cookieMonster.save();
					
					// if you're just logging in...
					if(!$rootScope.appScope.loaded){
						$rootScope.appScope.rootController.loadInitialView();
					}

					$rootScope.appScope.cookieMonster.save();
				}

				$rootScope.appScope.$digest();
				
			});
		},

		logoutUser : function(){

			if($rootScope.appScope.view != 'login'){
				$rootScope.appScope.rootController.loadView('loading');	
			}

			var logout_url = 'https://www.facebook.com/logout.php?next=' + encodeURIComponent(whym_settings.redirect_url) + '&access_token=' + $rootScope.appScope.user.fbAccessToken;

			$rootScope.appScope.user = {};
			$rootScope.appScope.loaded = false;
			$rootScope.appScope.sessionInCookie = false;

			$rootScope.appScope.rootController.loadLoginScreen();

			$rootScope.appScope.cookieMonster.clear();	
			
			
			window.location = logout_url;
		}

	},


	// ACCOUNT VIEW CONTROLLER
	acctController : {

		days : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],

		times : ['morning', 'afternoon', 'evening'],
		
		needs : {
			first_name : false,
			last_name : false,
			email : false,
			bio : false,
			phone : false
		},

		sending: false,

 		loadUser : function(user){
 			if(!user) user = $rootScope.appScope.user;
 			
			// build availability object
			var availability = {};
			$.each(this.days, function(i, day){
				availability[day] = {};
				$.each($rootScope.appScope.acctController.times, function(j, time){
					availability[day][time] = false;
				});
			});

			// load availability from database
			$.each(user.availability_data, function(index, a){
				availability[a.day][a.time] = true;
			});

			// build output object
			var availability_output = [];

			$.each(this.days, function(i, day){
				var hasDay = false;
				var day_obj = { }


				$.each($rootScope.appScope.acctController.times, function(j, time){
					if(availability[day][time]){
						if(!hasDay) {
							day_obj = {
								dayName : day,
								times: []
							}
							hasDay = true;
						}
						day_obj.times.push(time);	
					}
				});

				if(hasDay) {
					day_obj.times_str = day_obj.times.join(', ');
					availability_output.push(day_obj);
				}
			});


			// save into user object
			user.availability = availability;
			user.availability_output = availability_output;
			return user;
		},

		loadLoginScreen : function(){
			$rootScope.appScope.header.show = false;
			$rootScope.appScope.footer.show = false;
			$rootScope.appScope.loadView('login');				
		},
		
		loadScreen : function(screenNumber){
			$rootScope.appScope.rootController.loadView('account', screenNumber)
		},

		saveAndProgress : function(){
			if(this.sending) return;

			$rootScope.appScope.screen = parseInt($rootScope.appScope.screen);

			// validate
			var validate = {
				1 : ['first_name', 'last_name', 'email', 'city', 'phone'],
				2 : ['bio'],
				3 : []
			}
			var fields = validate[$rootScope.appScope.screen];
			if(!Utilities.validate($rootScope.appScope.user, fields, $rootScope.appScope.acctController.needs)) return;

			
			// save updated user
			this.sending = true;
			if($rootScope.appScope.screen == 3){
				$rootScope.appScope.user.availability = {
					days : "none"
				};
			}
			var request = {
				user: $rootScope.appScope.user,
				verb: 'updateUser'
			}
			
			$rootScope.appScope.apiClient.postData(request, function(user){
				var wasNew = $rootScope.appScope.user.isNew;
				$rootScope.appScope.user = user;
				$rootScope.appScope.acctController.loadUser();
				$rootScope.appScope.cookieMonster.save();

				// iterate to next screen
				$rootScope.appScope.acctController.sending = false;
				if(wasNew){
					$rootScope.appScope.screen++;
					if($rootScope.appScope.screen == 4) {
						$rootScope.appScope.orgNavigator.searchForOrganizations()
					}						
				}
				else {
					$rootScope.appScope.rootController.loadView('account', 'menu');
				}
			});
		},

		updateAvailability : function(day, time, flip){

			console.log($rootScope.appScope.user)

			if(flip) $rootScope.appScope.user.availability[day][time] = !($rootScope.appScope.user.availability[day][time]);

			var request = {
				day : day,
				time : time,
				verb : 'updateAvailability'
			}
			$rootScope.appScope.apiClient.postData(request, function(response){
				// don't do anything - model updates on user-action
			})
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
	},

	getNowMysqlTime : function() {
    	var now = new Date();

        var pad = function(num) {
            var norm = Math.abs(Math.floor(num));
            return (norm < 10 ? '0' : '') + norm;
        };
    
	    return now.getFullYear() 
	        + '-' + pad(now.getMonth()+1)
	        + '-' + pad(now.getDate())
	        + ' ' + pad(now.getHours())
	        + ':' + pad(now.getMinutes()) 
	        + ':' + pad(now.getSeconds());
	}
}
