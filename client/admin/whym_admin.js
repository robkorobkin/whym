var appInterfaceName = "admin";

var app = angular.module('whymAdminApp', ['LocalStorageModule', 'ui.bootstrap']);

app.controller('whymAdminCtrl', ['$scope', '$http', '$sce', '$rootScope', '$window',  'localStorageService', '$modal',
	function($scope, $http, $sce, $rootScope, $window, localStorageService, $modal){
		
		

		$scope.init = function(){

			// set window-level pointer to app scope
			window.$scope = $scope; 
			window.localStorageService = localStorageService;


			// set scope-level pointers to config and dictionary (for view)
			$scope.dictionary = dictionary;


			// DIY DEPENDENCY INJECTION - NICE AND SIMPLE
			for(var objName in sharedObjects){
				$scope[objName] = sharedObjects[objName];
			}

			// INITIALIZE OBJECTS WITH DEFAULT STATE
			$scope.updateController.init();

			// INIT ORG POINTER
			$scope.org = false; 


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
				$scope.rootController.loadView('loading');
				var request = {
					verb : 'AdminGetPeople'
				}
				$scope.apiClient.postData(request, function(response){
					$scope.org.people = response.people;
					for(var i = 0; i< 20; i++) $scope.org.people.push(angular.copy(response.people[0]));
					$scope.rootController.loadView('orgPeople');
				});
			},

			openPerson : function(person){
				console.log('trying to opern a person in a modal dialogue')
				$modal.open({					
					template: $('#personTemplate').html(),
					controller: 'whymAdminCtrl_Person',
				});			

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

			init : function(){
				this.newUpdate = {
					title: '',
					body : ''
				};

				this.needs = [];

				this.updateLog = [];
			},

			makePost : function(){
				Utilities.validate(this.newUpdate, ['title', 'body'], this.needs);
				var request = {
					post : this.newUpdate,
					verb  : 'makePost'
				}
				$scope.apiClient.postData(request, function(response){
					$scope.updateController.init();
					$scope.updateController.updateLog = response.updates;
				})
			}
		}

		$scope.init();

	}
	
]);

app.controller('whymAdminCtrl_Person', ['$scope',
	function($scope){
		
		alert('hello from inside the modal controller')
		
	}
	
]);

