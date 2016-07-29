var appInterfaceName = "admin";

var app = angular.module('whymAdminApp', ['LocalStorageModule', 'ui.bootstrap', 'ngAnimate']);

app.controller('whymAdminCtrl', ['$scope', '$http', '$sce', '$rootScope', 'localStorageService', '$uibModal',
	function($scope, $http, $sce, $rootScope, localStorageService, $uibModal){
		
		$scope.init = function(){

			// set window-level pointer to app scope
			$rootScope.appScope = $scope; 
			window.$rootScope = $rootScope;
			window.localStorageService = localStorageService;


			// set scope-level pointers to config and dictionary (for view)
			$scope.dictionary = dictionary;
			$scope.questionnaire = questionnaire;


			// DIY DEPENDENCY INJECTION - NICE AND SIMPLE
			for(var objName in sharedObjects){
				$scope[objName] = sharedObjects[objName];
			}

			// INITIALIZE OBJECTS WITH DEFAULT STATE
			$scope.updateController.init();

			// INIT ORG POINTER
			$scope.org = false; 


			// INIT DROPDOWN.JS
			// $(".select").dropdown({ 
			// 	"autoinit" : ".select",
			// 	"optionClass": "withripple"
			// });
			

			// WHAT HAPPENS WHEN 
			$scope.rootController.loadLoginScreen = function(){
				this.loadView('login');
				$scope.showSidebar = false;
			}

			$scope.rootController.init();
		}
		
		
		$scope.organizationActivator = {
			selectOrganization : function(org){
				$scope.screen = 'approveNew';
				this.selection = org;

			},
			cancel : function(){
				$scope.screen = 'selectNew';
				this.selection = {};				
			},
			activateOrganization : function(){
				$scope.rootController.loadView('loading');
				var request = {
					verb : 'createOrganization',
					access_token: $scope.user.fbAccessToken,
					org : this.selection
				}
				$scope.apiClient.postData(request, function(response){
					$scope.organizationController.setOrganization(response.organization);
					$scope.rootController.loadView('orgProfile');
				});
			}
		}

		$scope.organizationController = {

			newlySelectedOrgId : 0,

			person_search_term : '',

			peopleMode : 'list',

			selected_group : "Select a group...",

			setOrganization : function(org){
				$scope.org = org;
				$scope.user.lastOrganizationId = $scope.org.organizationId;
				$scope.cookieMonster.save();
			},

			openOrganization : function(){
				$scope.rootController.loadView('loading');
				var request = {
					verb : 'AdminGetOrganization',
					organizationId : this.newlySelectedOrgId
				}
				$scope.apiClient.postData(request, function(response){
					$scope.organizationController.setOrganization(response.organization);
					$scope.rootController.loadView('orgProfile');
				});

			},

			getPeople : function(){
				console.log(this.selected_group);
				$scope.rootController.loadView('loading');
				var request = {
					verb : 'AdminGetPeople'
				}
				$scope.apiClient.postData(request, function(response){
					
					$scope.org.people = [];
					$scope.org.displayPeople = [];

					$.each(response.people, function(index, person){
						var person = $scope.acctController.loadUser(person);
						$scope.org.people.push(person);
						$scope.org.displayPeople.push(person);
					});

					$scope.org.user_groups = response.user_groups;

					$scope.rootController.loadView('orgPeople', 'list');
				});
			},

			filterPeople : function(){
				var search_term = this.person_search_term;
				$scope.org.displayPeople = [];
				$.each($scope.org.people, function(index, person){
					if(person.first_name.indexOf(search_term) != -1 || person.last_name.indexOf(search_term) != -1){
						$scope.org.displayPeople.push(person);
					}
				});
			},
			
			openUserGroupAdder : function(){
				$scope.rootController.loadView('orgPeople', 'addUserGroup');
				this.newGroup = {
					user_group_name : ''
				}
				this.needs = {
					user_group_name : false,
					groupNameUsed : false
				}
			},

			addUserGroup : function(){
				if(this.newGroup.user_group_name == ''){
					this.needs.user_group_name = true;
					return;
				}

				// ACTIVATE WHEN USER GROUPS ARE BEING RETURNED
				// $.each(response.user_groups, function(i, group){
				// 	if(group.user_group_name == this.newGroup.user_group_name){
				// 		this.needs.groupNameUsed = true;
				// 		return
				// 	}
				// });

				var user_group_name = this.newGroup.user_group_name;
				var request = {
					user_group_name: user_group_name,
					verb: 'AdminAddUserGroup'
				}
				$scope.apiClient.postData(request, function(response){
					$scope.org.user_groups = response.user_groups;
					$.each(response.user_groups, function(i, group){
						if(group.user_group_name == user_group_name){
							$scope.organizationController.selected_group = group.user_group_id;		
						}
					});
					this.addMembers = true;
					$scope.rootController.loadView('orgPeople', 'list');
				});
			},

			openPerson : function(person){
				$scope.person = person;
				$scope.rootController.loadView('person', 'questionnaire')
			},

			updateOrganization : function(){

				// ToDo: add client-side validation

				$scope.rootController.loadView('loading');
				var request = {
					verb : 'AdminUpdateOrganization',
					org: $scope.org
				}
				$scope.apiClient.postData(request, function(response){
					$scope.organizationController.setOrganization(response.organization);
					$scope.rootController.loadView('orgProfile');
				});
			},


			// TO DO - PUT THE NEXT SIX FUNCTIONS INTO photosController
			loadPhotos : function(){
				$scope.rootController.loadView('loading');
				
				var request = {
					verb : 'AdminGetPhotosForOrg'
				}
				$scope.apiClient.postData(request, function(response){
					$scope.organizationController.loadPhotoData(response.photos);
					$scope.rootController.loadView('orgPhotos', 'activePhotos');
				});
			},

			loadPhotoData : function(photos){
				$scope.org.photos = photos;
				$scope.org.photosDisplay = {0 : [], 1 : []};
				$scope.org.selectedPhotos = {};

				for(var index in photos){
					var photo = photos[index];
					$scope.org.selectedPhotos[photo.photoFbId] = true;
					$scope.org.photosDisplay[index % 2].push(photo);
				}
			},

			loadPhotosFromFacebook : function(url){
				$scope.rootController.loadView('loading');
				if(!url){
					var url = 'https://graph.facebook.com/' + $scope.org.organizationFbId + '/photos/uploaded?access_token=' + $scope.user.fbAccessToken;
				}

				var request = {
					verb : 'AdminGetPhotosFromFacebook',
					url : url
				}
				$scope.apiClient.postData(request, function(response){
					
					var photoData = response.photoData;
					photoData.displaySet = [];
					var rowIndex = -1;

					for(var index in photoData.data){
						if(index % 6 == 0){
							rowIndex++;
							photoData.displaySet[rowIndex] = [];	
						}
						var photo = photoData.data[index];
						photoData.displaySet[rowIndex].push(photo);
						if(parseInt(photo.id) in $scope.org.selectedPhotos) photo.selected = true;
					}

					if(!('previous' in photoData.paging)) photoData.paging.previous = false;
					if(!('next' in photoData.paging)) photoData.paging.next = false;
					$scope.org.photoData = photoData;
					$scope.rootController.loadView('orgPhotos', 'addPhotos');
				});
			},

			selectPhoto : function(photo){

				if(photo.selected){
					if(!confirm("Are you sure you want to remove this photo from your Whym profile?  It will remain in your Facebook.")) return;

					var request = {
						verb : 'AdminRemovePhoto',
						photoFbId : photo.id
					}

					$scope.apiClient.postData(request, function(response){
						photo.selected = false;
					});
				}
	
				else {
	
					photo.selected = true;

					var request = {
						verb : 'AdminSelectPhoto',
						photo : {
							photoFbId : photo.id,
							orgId : $scope.org.organizationId,
						}
					}
					request.photo.caption = ('name' in photo) ? photo.name : '';
					$scope.apiClient.postData(request, function(response){
						$scope.org.selectedPhotos[photo.id] = true;
					});

				}
			},

			removePhoto : function(photo){

				if(!confirm("Are you sure you want to remove this photo from your Whym profile?  It will remain in your Facebook.")) return;

				var request = {
					verb : 'AdminRemovePhoto',
					photoFbId : photo.photoFbId
				}

				$scope.apiClient.postData(request, function(response){
					$scope.organizationController.loadPhotoData(response.photos);
					$scope.rootController.loadView('orgPhotos', 'activePhotos');
				});
			},			

			savePhotoCaptions : function(){
				var request = {
					photos : $scope.org.photos,
					verb : 'AdminUpdateCaptions'
				}
				
				$scope.rootController.loadView('loading');

				$scope.apiClient.postData(request, function(response){
					$scope.organizationController.loadPhotoData(response.photos);
					$scope.rootController.loadView('orgPhotos', 'activePhotos');
				});
			}
		}

		$scope.updateController = {

			events : [],

			feed : [],

			show_select : true,

			editing : false,

			init : function(){

				this.newUpdate = {
					title: '',
					body : '',
					event : false,
					publish_date : ''
				};

				this.needs = [];

				this.editing = false;

				this.show_select = true;

			},

			load : function(){
				$scope.rootController.loadView('loading');

				this.mode = 'editor';
				this.update_type = 'Manual';

				var request = {
					verb : "AdminGetUpdates"
				}

				$scope.apiClient.postData(request, function(response){
					$scope.updateController.loadUpdates(response.updates);
				});
				
			},

			loadUpdates : function(updates){
				$scope.org.updates = updates;
				$scope.org.updatesViewset = [[]];
				var rowIndex = 0;

				for(var index in updates){
					var update = updates[index];
					update.trustedHtml = $sce.trustAsHtml(update.body.replace(/(?:\r\n|\r|\n)/g, '<br />'));
				}

				$scope.updateController.init();
				$scope.rootController.loadView('orgUpdates', 'view');
			},

			postUpdate : function(){

				Utilities.validate(this.newUpdate, ['title', 'body'], this.needs);

				this.newUpdate.update_type = this.update_type;
				if('updateId' in this.newUpdate){
					this.newUpdate.edit_date = Utilities.getNowMysqlTime();
				}
				else {
					this.newUpdate.publish_date = Utilities.getNowMysqlTime();
				}

				this.update_type = 'Manual';
				this.show_select = false;

				delete this.newUpdate.trustedHtml;

				var request = {
					newUpdate : this.newUpdate,
					verb  : 'AdminPostUpdate'
				}
				
				$scope.apiClient.postData(request, function(response){
					$scope.updateController.loadUpdates(response.updates);

					// hacky work-around to re-render select box in js plugin
					$scope.updateController.show_select = true;
					

				})
			},

			textChange : function(){
				var update = this.newUpdate;
				update.trustedHtml = $sce.trustAsHtml(update.body.replace(/(?:\r\n|\r|\n)/g, '<br />'));	
			},

			deleteUpdate : function(update){
				if(!confirm('Are you sure you want to delete this update?')) return;
				var request = {
					updateId : update.updateId,
					edit_date : Utilities.getNowMysqlTime(),
					verb  : 'AdminDeleteUpdate'
				}
				$scope.apiClient.postData(request, function(response){
					$('#update_' + update.updateId).fadeOut();
				})	
			},

			editUpdate : function(update){
				this.newUpdate = update;
				if(!('event' in update)) update.event = false;
				this.show_select = false;
				this.update_type = 'Manual';
				this.editing = true;
				this.mode = 'editor';
			},

			getUpdatesFromFB : function(){

				var update_type = this.update_type;

				if(update_type == 'Manual'){
					this.init();
					this.mode = 'editor';
					return;
				}

				this.mode = 'loading';

				var request = {
					update_type : update_type,
					verb  : 'AdminGetUpdatesFromFB'
				}
				$scope.apiClient.postData(request, function(response){
					switch(update_type){
						case "Event" :
							$scope.updateController.init();
							$scope.updateController.events = response.events;
							$scope.updateController.mode = 'events';
						break;
						case "Page Feed" :
							for(var index in response.feed){
								var post = response.feed[index];
								post.trustedHtml = $sce.trustAsHtml(post.body.replace(/(?:\r\n|\r|\n)/g, '<br />'));
							}
							$scope.updateController.init();
							$scope.updateController.feed = response.feed;
							$scope.updateController.mode = 'feed';
						break;
					}
					
				})	
			},

			selectEvent : function(event){
				this.newUpdate.event = event;
				$scope.updateController.mode = 'editor';
			},

			cancelEvent : function(event){
				if(!('updateId' in this.newUpdate)) {
					console.log(this.newUpdate)
					this.init();
				}
				this.newUpdate.event = false;
				$scope.updateController.mode = 'events';

				if(this.events.length == 0){
					this.mode = 'loading';
					var request = {
						update_type : 'Event',
						verb  : 'AdminGetUpdatesFromFB'
					}
					$scope.apiClient.postData(request, function(response){
						$scope.updateController.events = response.events;
						$scope.updateController.mode = 'events';					
					});
				}
			},

			selectPost : function(post){
				this.newUpdate.body = post.body;
				this.newUpdate.url = post.url;
				$scope.updateController.mode = 'editor';
			},

			cancelPost : function(event){
				this.init();
				$scope.updateController.mode = 'feed';
			},

		}

		$scope.init();

	}
	
]);

// app.controller('whymAdminCtrl_Person', ['$scope', '$uibModalInstance', 'person',
// 	function($scope, $uibModalInstance, person){
// 		console.log(person);
// 		$scope.person = person;

// 		$scope.banana = "BAANANANA";
		
// 		// on "ok" - save person 
// 		$scope.ok = function () {
// 	        $uibModalInstance.close($scope.selected.item);
// 	    };

// 	    // on "cancel" - dismiss modal
// 	    $scope.cancel = function () {
// 	        $uibModalInstance.dismiss('cancel');
// 	    };
// 	}
	
// ]);

