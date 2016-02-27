<?php 
	header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
	header("Pragma: no-cache"); // HTTP 1.0.
	header("Expires: 0"); // Proxies.
?><!DOCTYPE html>
<html lang="en">
	<head>

		<!-- SHARED FRAMEWORK -->
		<?php include('client/shared/head.php'); ?>

		<!-- ADMIN-SPECIFIC -->
		<!-- Dropdown.js -->
		<link href="//cdn.rawgit.com/FezVrasta/dropdown.js/master/jquery.dropdown.css" rel="stylesheet">
		<script src="https://cdn.rawgit.com/FezVrasta/dropdown.js/master/jquery.dropdown.js"></script>
		
		<!-- APP -->
		<script src="server/whym_api.php?lib=js&app=admin"></script>
		<link href="server/whym_api.php?lib=css&app=admin" rel="stylesheet" type="text/css" />

	</head>
	
	<body  ng-app="whymAdminApp" ng-controller="whymAdminCtrl">
	
		
		<!-- INTERNAL APP FRAME -->
		<div class="appFrame scrollable" >
		
			
			<!-- SIDEBAR -->
			<div class="sidebar col-sm-3" ng-if="showSidebar">

				<!-- ORGANIZATION SPECIFIC -->
				<div class="organizationMenu" ng-if="org">
					<div class="header" >
						<img ng-src="//graph.facebook.com/{{org.organizationFbId}}/picture" class="organizationImg" />
						<div class="orgTitle">{{org.organizationName}}</div>
					</div>
					<div class="menu">
						<a ng-click="organizationController.getPeople()" ng-class="{active: view == 'orgPeople'}">People</a>
						<a ng-click="updateController.load()" ng-class="{active: view == 'orgUpdates'}">Updates</a>
						<a ng-click="organizationController.loadPhotos()" ng-class="{active: view == 'orgPhotos'}">Photos</a>
						<a ng-click="rootController.loadView('orgProfile')" ng-class="{active: view == 'orgProfile'}">Profile</a>
					</div>
					<div class="orgTitle">--</div>
				</div>

				<!-- UNIVERSAL SIDEBAR -->
				<div class="systemSidebar">
					<a ng-click="rootController.loadView('orgChooser')" ng-if="user.orgs.length != 0">Change Organization</a>
					<a ng-click="apiClient.logoutUser()">Log Out</a>
				</div>


			</div>


			<!-- MAIN CONTENT -->
			<div class="col-sm-9">

				<!-- LOADING INTERFACE -->
				<div class="loadingMain" ng-if="view=='loading'" >
					<p>Loading...</p>
					<img src="client/shared/images/loading.gif" />
				</div>
		
				<!-- LOGIN INTERFACE -->
				<div class="loginFrame" ng-if="view=='login'">
					<div class="loginMain">
						<p>Click below to start using {{appName}}</p>
						<div class="btnFrame">
							<a ng-click="apiClient.logIntoFacebook()">
								<img src="client/shared/images/fbcnct.png" />
							</a>
						</div>
					</div>
				</div>
				
				<!-- ACCOUNT MANAGEMENT INTERFACE -->
				<div ng-if="view == 'account'" class=" primaryFrame">
					<div ng-if="screen == 1">
						<div class="form-group" style="margin: 0" ng-class="{'has-error': acctController.needs.first_name}">
							<label class="control-label" for="user_first_name" >First Name</label>
							<input class="form-control" id="user_first_name" type="text" ng-model="user.first_name" ng-required>
						</div>
						<div class="form-group" style="margin: 0" ng-class="{'has-error': acctController.needs.last_name}">						 
							<label class="control-label" for="user_last_name">Last Name</label>
							<input class="form-control" id="user_last_name" type="text" ng-model="user.last_name">
						</div>

						<div class="form-group" style="margin: 0" ng-class="{'has-error': acctController.needs.email}">						 
							<label class="control-label" for="user_email">Email</label>
							<input class="form-control" id="user_email" type="text" ng-model="user.email">
						</div>

						<div class="row">
							<div class="col-sm-8 col-xs-8">
								<div class="form-group" style="margin: 0" ng-class="{'has-error': acctController.needs.city}">						 
									<label class="control-label" for="user_city">City</label>
									<input class="form-control" id="user_city" type="text" ng-model="user.city">
								</div>
							</div>
							<div class="col-sm-4 col-xs-4 form-group" style="padding-left: 0px">
								<label class="control-label" for="user_state">state</label>
								<select class="form-control" id="user_state" ng-model="user.state">
									<option ng-repeat="(abbrev, state) in dictionary.us_state_abbrevs_names" 	
											ng-value="state" ng-selected="state == user.state">{{abbrev}}</option>
								</select>
							</div>					
						</div>
						
						      
						<div class="row">
							<div class="col-sm-6 col-xs-6 form-group">
								<label for="user_gender" class="control-label">Gender?</label>
								<select id="user_gender" class="form-control" ng-model="user.gender" >
									<option value="MALE">Male</option>
									<option value="FEMALE">Female</option>
									<option value="OTHER">Other</option>
								</select>
							</div>
						</div>

						<div class="row">
							<div class="col-sm-4 col-xs-4 form-group" style="padding-right: 0">
								<label for="user_birthday_month" class="control-label">Month</label>
								<select id="user_birthday_month" class="form-control" ng-model="user.birthday.month" >
									<option ng-repeat="(monthNum, monthName) in dictionary.months"
											ng-value="monthNum" ng-selected="monthNum == user.birthday.month">{{monthName}}</option>
								</select>
							</div>
							<div class="col-sm-4 col-xs-4 form-group">									
								<label for="user_birthday_day" class="control-label">Day</label>
								<select id="user_birthday_day" class="form-control" ng-model="user.birthday.day" >
									<option ng-repeat="dayNum in dictionary.days"
											ng-value="dayNum" ng-selected="dayNum == user.birthday.day">{{dayNum}}</option>
								</select>
							</div>
							<div class="col-sm-4 col-xs-4 form-group" style="padding-left: 0">	
								<label for="user_birthday_year" class="control-label">Year</label>
								<select id="user_birthday_year" class="form-control" ng-model="user.birthday.year" >
									<option ng-repeat="year in dictionary.years"
											ng-value="year" ng-selected="year == user.birthday.year">{{year}}</option>
								</select>																								
							</div>					
						</div>
						<div style="margin: 5px; text-align: center;">
							<a class="btn btn-raised btn-success" ng-click="acctController.saveAndProgress()" ng-if="user.isNew">Next</a>
							<a class="btn btn-raised btn-success" ng-click="acctController.saveAndProgress()" ng-if="!user.isNew">Save</a>
						</div>
					</div>
					
					<div ng-if="screen == 2"> 
						<div class="form-group" ng-class="{'has-error': acctController.needs.bio}">
							<label for="user_bio" class="control-label">Who are you?</label>
							<textarea class="form-control" id="user_bio" ng-model="user.bio" maxlength="300"></textarea>
							<div style="height: 15px;">
								<span class="help-block">{{(300 - user.bio.length)}} chars remaining</span>
							</div>
						</div>
						<div style="margin: 5px; text-align: center;">
							<a class="btn btn-raised btn-success" ng-click="acctController.saveAndProgress()" ng-if="user.isNew">Start Meeting People</a>
							<a class="btn btn-raised btn-success" ng-click="acctController.saveAndProgress()" ng-if="!user.isNew">Save</a>
						</div>
					</div>

					<div ng-if="screen == 'menu'"> 
						<h2 style="text-align: center">My Account</h2>
						<div style="margin: 5px; text-align: center;">
							<a class="btn btn-raised btn-success" ng-click="acctController.loadScreen(1)">Edit Profile</a>
							<br /><br />
							<a class="btn btn-raised btn-success" ng-click="acctController.loadScreen(2)">Edit Bio</a>
							<br /><br />
							<a class="btn btn-raised btn-success" ng-click="apiClient.logoutUser()">Log Out</a>
						</div>
					</div>					
				</div>

				<!-- ORGANIZATION - PEOPLE-->
				<div ng-if="view == 'orgPeople'" class=" primaryFrame orgPeople">
					<div ng-repeat="(index, person) in org.people" class="person col-sm-2" ng-click="organizationController.openPerson(person)">
						<img ng-src="//graph.facebook.com/{{person.fbId}}/picture?height=268&width=268" class="personImg" />
						<div class="name">{{person.first_name}} {{person.last_name}}</div>
					</div>
				</div>

				<!-- ORGANIZATION - UPDATES-->
				<div ng-if="view == 'orgUpdates'" class="primaryFrame updateManager">
					<div ng-if="screen=='input'">

						<!-- MODE SELECTOR -->
						<div class="form-group">
			                <label for="update_type" class="control-label">Update Type</label>
			                <select id="update_type" class="form-control select" ng-model="updateController.update_type" ng-change="updateController.getUpdatesFromFB()">
								<option>Manual</option>
								<option>Event</option>
								<option>Page Feed</option>
							</select>
						</div>

						<!-- PRIMARY FORM INPUT -->
						<div class="primaryFormInput" ng-if="updateController.mode == 'editor'">
							<div class="cancel back" ng-if="updateController.update_type == 'Page Feed'">
								<a ng-click="updateController.cancelPost()">Back</a>
							</div>

							
							<div class="updateSelectHeader">Update your followers:</div>

							<div class="editorFrame clearfix">

								<div class="form-group" style="margin: 0" ng-class="{'has-error': updateController.needs.title}">
									<label class="control-label" for="newUpdate_title" >Title</label>
									<input class="form-control" id="newUpdate_title" type="text" ng-model="updateController.newUpdate.title" ng-required>
								</div>
								<div class="form-group" style="margin: 0" ng-class="{'has-error': updateController.needs.body}">
									<label class="control-label" for="newUpdate_body" >What's on your mind?</label>
									<textarea class="form-control" id="newUpdate_body" ng-model="updateController.newUpdate.body" ng-required
										ng-class="{'eventText': updateController.newUpdate.event}"></textarea>
								</div>

								<div class="form-group">
									<label class="control-label" for="newUpdate_url" >URL \ LINK</label>
									<input class="form-control" id="newUpdate_url" type="text" ng-model="updateController.newUpdate.url" ng-required>
								</div>

								<a class="btn btn-raised btn-success" ng-click="updateController.postUpdate()">POST</a>
							</div>

							<!-- EVENT PREVIEW -->
							<div ng-if="updateController.newUpdate.event">
								<div class="updateSelectHeader">Event:</div>

								<div class="event standalone clearfix">
									<div class="col-sm-3 imgFrame" >
										<img ng-src="//graph.facebook.com/{{updateController.newUpdate.event.event_FbId}}/picture?height=268&width=268" />
									</div>
									<div class="col-sm-9">
										<div class="title">{{updateController.newUpdate.event.event_title}}</div>
										<div class="location">{{updateController.newUpdate.event.event_location}}</div>
										<div class="date">{{updateController.newUpdate.event.event_dateStr}}</div>
									</div>
								</div>
								<div class="cancel">
									<a ng-click="updateController.cancelEvent()">Cancel</a>
								</div>
							</div>
						</div>							

						<!-- EVENT SELECTOR -->
						<div ng-if="updateController.mode == 'events'">

							<div class="updateSelectHeader">Select an event to share on Whym:</div>

							<div class="updateSelectFrame">
								<div ng-repeat="event in updateController.events" class="event clearfix" ng-click="updateController.selectEvent(event)">
									<div class="col-sm-3 imgFrame" >
										<img ng-src="//graph.facebook.com/{{event.event_FbId}}/picture?height=268&width=268" />
									</div>
									<div class="col-sm-9">
										<div class="title">{{event.event_title}}</div>
										<div class="location">{{event.event_location}}</div>
										<div class="date">{{event.event_dateStr}}</div>
									</div>
								</div>
							</div>
						</div>

						<!-- POST SELECTOR -->
						<div ng-if="updateController.mode == 'feed'">
							<div class="updateSelectHeader">Select a post to share on Whym:</div>

							<div class="updateSelectFrame">
								<div ng-repeat="post in updateController.feed" class="post" ng-click="updateController.selectPost(post)">
									{{post.body}}
								</div>
							</div>
						</div>

						<!-- LOADING -->
						<div ng-if="updateController.mode == 'loading'">
							<p>Loading...</p>
							<img src="client/shared/images/loading.gif" />
						</div>

						
					</div>
					<div class="updateList"  ng-if="screen=='view'">
						<div ng-repeat="row in org.updatesViewset" class="clearfix">
							<div ng-repeat="update in row" class="col-sm-6">
								<div class="update">
									<div class="title">{{update.title}}</div>
									<div class="body">{{update.body}}

										<span ng-if="update.url != ''"> - 
											<a href="{{update.url}}">LINK</a>
										</span>
									</div>
									<div class="event standalone clearfix" ng-if="update.event">
										<div class="col-sm-3 imgFrame" >
											<img ng-src="//graph.facebook.com/{{update.event.event_FbId}}/picture?height=268&width=268" />
										</div>
										<div class="col-sm-9">
											<div class="title">{{update.event.event_title}}</div>
											<div class="location">{{update.event.event_location}}</div>
											<div class="date">{{update.event.event_dateStr}}</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ORGANIZATION - PHOTOS-->
				<div ng-if="view == 'orgPhotos'" class=" primaryFrame orgPhotos">

					<div ng-if="screen == 'activePhotos'">

						<div ng-if="org.photos.length == 0">
							Click below to start importing photos into your profile.
						</div>

						<div class="nav clearfix">
							<a class="triggerAdder" ng-click="organizationController.loadPhotosFromFacebook()">
								Add Photos <i class="glyphicon glyphicon-plus"></i>
							</a>
							<a class="btn btn-raised btn-success" ng-click="organizationController.savePhotoCaptions()" ng-if="org.photos.length > 0">SAVE</a>
						</div>
						<div class="pictureFrame row">
							<div ng-repeat="(index, photoList) in org.photosDisplay" class="col-sm-6 photoColumn">
								<div ng-repeat="(index, photo) in photoList" class="activePhotoFrame ">
									<div class="activePhoto clearfix">
										<div class="deletePhoto" ng-click="organizationController.removePhoto(photo)">X</div>
										<img ng-src="https://graph.facebook.com/{{photo.photoFbId}}/picture?type=normal" class="col-sm-6" style="padding-left: 0"/>
										<textarea class="caption col-sm-6" ng-model="photo.caption"></textarea>
									</div>
									<div ng-if="index % 2 == 0" style="clear: both"></div>
								</div>
							</div>
						</div>
					</div>

					<div ng-if="screen == 'addPhotos'">
						<a ng-click="organizationController.loadPhotos()" class="backBtn">&lt;&lt; BACK</a>
						<div class="nav">
							<div class="col-sm-6">
								<a ng-if="org.photoData.paging.previous" ng-click="organizationController.loadPhotosFromFacebook(org.photoData.paging.previous)">PREV</a>
							</div>
							<div class="col-sm-6" style="text-align: right;">
								<a ng-if="org.photoData.paging.next" ng-click="organizationController.loadPhotosFromFacebook(org.photoData.paging.next)">NEXT</a>
							</div>
						</div>
						<div class="pictureFrame">
						 	<div ng-repeat="(index, row) in org.photoData.displaySet" class="row">
								<div ng-repeat="(index, photo) in row" class="person col-sm-2" ng-click="organizationController.selectPhoto(photo)">
									<img ng-src="https://graph.facebook.com/{{photo.id}}/picture?type=normal" class="thumb" ng-class="{selected: photo.selected }"/>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ORGANIZATION - PROFILE -->
				<div ng-if="view == 'orgProfile'" class=" primaryFrame profileEditor">
					<h2>Edit Organization Profile</h2>

					<!-- HEADER -->
					<div class="profileHeader">
						
						<div class="form-group" style="margin: 0" ng-class="{'has-error': organizationController.needs.organizationName}">
							<label class="control-label" for="org_organizationName" >Organization Name</label>
							<input class="form-control" id="org_organizationName" type="text" ng-model="org.organizationName" ng-required>
						</div>
						<div class="form-group" style="margin: 0" ng-class="{'has-error': organizationController.needs.website}">
							<label class="control-label" for="org_website" >Website</label>
							<input class="form-control" id="org_website" type="text" ng-model="org.website" ng-required>
						</div>
					
					</div>

					<!-- DESCRIPTION -->
					<div class="form-group orgDescriptionFrame" ng-class="{'has-error': organizationController.needs.organizationDescription}">
						<label for="org_organizationDescription" class="control-label">What is this organization all about?</label>
						<textarea class="form-control" id="org_organizationDescription" ng-model="org.organizationDescription" maxlength="500"></textarea>
						<div style="height: 25px;">
							<span class="help-block">{{(500 - org.organizationDescription.length)}} chars remaining</span>
						</div>
					</div>


					<!-- ORGANIZATION ADDRESS -->
					<div>
						<div style="width: 45%; float: left">
							<div class="sectionHeader">Address:</div>
							<div class="form-group" style="margin: 0">
								<label class="control-label" for="org_address1">Address 1</label>
								<input class="form-control" id="org_address1" type="text" ng-model="org.address1">
							</div>

							<div class="form-group">
								<label class="control-label" for="org_address2">Address 2</label>
								<input class="form-control" id="org_address2" type="text" ng-model="org.address2">
							</div>

							<div class="form-group">
								<label class="control-label" for="org_city">City</label>
								<input class="form-control" id="org_city" type="text" ng-model="org.city">
							</div>

							<div class="form-group">
								<label class="control-label" for="org_state">State</label>
								<select class="form-control" id="org_state" ng-model="org.state">
									<option ng-repeat="(abbrev, state) in dictionary.us_state_abbrevs_names" 	
											ng-value="state" ng-selected="state == org.state">{{state}}</option>
								</select>
							</div>

							<div class="form-group">
								<label class="control-label" for="org_zip">Zip</label>
								<input class="form-control" id="org_zip" type="text" ng-model="org.zip">
							</div>
						</div>

						<!-- ORGANIZATION CONTACT -->
						<div style="width: 45%; float: right">
							<div class="sectionHeader">Primary Contact:</div>
							<div class="form-group">
								<label class="control-label" for="org_contact1_FirstName">First Name</label>
								<input class="form-control" id="org_contact1_FirstName" type="text" ng-model="org.contact1_FirstName">
							</div>
							<div class="form-group">
								<label class="control-label" for="org_contact1_LastName">Last Name</label>
								<input class="form-control" id="org_contact1_LastName" type="text" ng-model="org.contact1_LastName">
							</div>
							<div class="form-group">
								<label class="control-label" for="org_contact1_Position">Position</label>
								<input class="form-control" id="org_contact1_Position" type="text" ng-model="org.contact1_Position">
							</div>
							<div class="form-group">
								<label class="control-label" for="org_contact1_Email">Email</label>
								<input class="form-control" id="org_contact1_Email" type="text" ng-model="org.contact1_Email">
							</div>
							<div class="form-group">
								<label class="control-label" for="org_contact1_Phone">Phone</label>
								<input class="form-control" id="org_contact1_Phone" type="text" ng-model="org.contact1_Phone">
							</div>

						</div>
					</div>

					<div style="margin: 5px; text-align: center;">
						<a class="btn btn-raised btn-success" ng-click="organizationController.updateOrganization()">Save</a>
					</div>
				</div>

				<!-- CHOOSE ORGANIZATION -->
				<div ng-if="view == 'orgChooser'" class=" primaryFrame profileEditor">
					<div id="chooseOrganization">
						<div ng-if="user.orgs.length != 0">
							<div class="header">CHOOSE ORGANIZATION</div>
							<select id="organizationSelector" ng-change="organizationController.openOrganization()" ng-model="organizationController.newlySelectedOrgId">
								<option ng-repeat="org in user.orgs" value="{{org.organizationId}}">{{org.organizationName}}</option>
							</select>
						</div>
						<a class="triggerAdder" ng-click="rootController.loadView('adder','selectNew')">
							Add Organization <i class="glyphicon glyphicon-plus"></i>
						</a>
					</div>
				</div>

				<!-- ADD ORGANIZATIONS -->
				<div ng-if="view == 'adder'" class=" primaryFrame">

					<div ng-if="screen == 'selectNew'">
						<h2>Select an organization to add to Whym...</h2>
						<div class="organizationList clearfix">
							<div ng-repeat="(index, org) in user.pages" class="col-sm-4 organization" ng-click="organizationActivator.selectOrganization(org)">
								<img ng-src="//graph.facebook.com/{{org.organizationFbId}}/picture" class="organizationImg" />
								<div class="orgTitle">{{org.organizationName}}</div>
							</div>
						</div>
					</div>

					<div ng-if="screen == 'approveNew'" style="text-align: center">
						<h2>Activate {{organizationActivator.selection.organizationName}}?</h2>
						<img ng-src="//graph.facebook.com/{{organizationActivator.selection.organizationFbId}}/picture"  class="organizationImg"/>
						<a class="btn btn-raised btn-success" ng-click="organizationActivator.activateOrganization()">Continue</a>
						<br /><br /><a class="btn btn-raised btn-default" ng-click="organizationActivator.cancel()">Cancel</a>
					</div>
				</div>

		</div>

		<!-- MODAL TEMPLATES -->
		<div style="display: none;">

			<div id="personTemplate">I AM THE PERSON TEMPLATE</div>
			
		</div>		
	</body>
</html>