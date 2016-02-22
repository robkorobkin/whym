<?php 
	header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
	header("Pragma: no-cache"); // HTTP 1.0.
	header("Expires: 0"); // Proxies.
?><!DOCTYPE html>
<html lang="en">
	<head>

		<!-- SHARED FRAMEWORK -->
		<?php include('client/shared/head.php'); ?>


		<!-- APP -->
		<script src="server/whym_api.php?lib=js&app=public"></script>
		<link href="server/whym_api.php?lib=css&app=public" rel="stylesheet" type="text/css" />
	</head>
	
	<body  ng-app="whymApp" ng-controller="whymCtrl">
		
		
		<!-- INTERNAL APP FRAME -->
		<div class="appFrame scrollable" >
		
			
			<!-- MAIN CONTENT -->
			<div class="mainContent">

				<!-- LOADING INTERFACE -->
				<div class="loadingFrame" ng-if="view=='loading'" >
					<div class="loginMain">
						<p>Loading...</p>
						<img src="client/shared/images/loading.gif" />
					</div>
				</div>
		

				<!-- LOGIN INTERFACE -->
				<div class="loginFrame" ng-if="view=='login'" >
					<div class="loginMain">
						<p>Click below to start using {{appName}}</p>
						<div class="btnFrame">
							<a ng-click="apiClient.logIntoFacebook()">
								<img src="client/shared/images/fbcnct.png" />
							</a>
						</div>
					</div>
				</div>
				
				
				<!-- SEARCH INTERFACE -->
				<div ng-if="view == 'search'" class="primaryFrame">

					<h2>Search</h2>

					<div class="form-group" style="margin: 0">						 
						<label class="control-label" for="search_str">Search by name?</label>
						<input class="form-control" id="search_str" type="text" ng-model="search.search_str">
					</div>
					
					<div class="form-group">
						<label for="search_proximity" class="control-label">Proximity?</label>
						<select id="search_proximity" class="form-control" ng-model="search.proximity" >
							<option value="1">1 Mile</option>
							<option value="5">5 Miles</option>
							<option value="20">20 Miles</option>
							<option value="">All</option>
						</select>
					</div>

					<div style="margin: 5px; text-align: center;">
						<a class="btn btn-raised btn-success" ng-click="feedController.search()">Search</a>
					</div>
				</div>


				<!-- RESULTS -->
				<div ng-if="view == 'list'">
					<div class="listInput">
						<div class="form-group" style="margin: 0">						 
							<label class="control-label" for="search_str">Search by name?</label>
							<input class="form-control" id="search_str" type="text" ng-model="orgNavigator.search_str">
						</div>
					</div>
					<div class="organizationListFrame">
						<table class="organizationList primaryFrame panel panel-default">
							<tr class="orgResult clearfix row" ng-repeat="org in orgNavigator.orgs"
								ng-click="orgController.openOrg(org)">
								<td class="icon">
									<img ng-src="//graph.facebook.com/{{org.organizationFbId}}/picture" />
								</td>
								<td class="text">
									<div class="name">
										{{org.organizationName}}
									</div>
								</td>
							</tr>
							<!-- ... -->
						</table>


						<div ng-if="orgNavigator.orgs.length == 0" style="margin-top: 15px; padding: 10px 10px 5px;">
							<p>There are no organizations that meet your criteria.  Please revise the parameters of your search.</p>
						</div>
					</div>
				</div>


				<!-- ORG -->
				<div ng-if="view == 'org'" class="organization primaryFrame">
					<div class="header" >
						<div class="orgTitle">{{org.organizationName}}</div>
						<ul class="pagination">
							<li ng-class="{ active : true }"><a>Info</a></li>
							<li><a>Updates</a></li>
							<li><a>People</a></li>
						</ul>
					</div>

					<div ng-if="screen == 'info' ">
						<div class="imgFrame">
							<img ng-src="//graph.facebook.com/{{org.organizationFbId}}/picture" class="organizationImg" />
						</div>
						<div class="form-group togglebutton" style="margin-top: 0;">
							<div ng-class="{toggledOn : org.signup.signedup}" ng-click="orgController.toggleSignup(true)">
								Sign Up
							</div>
							<label>
								<input checked="" type="checkbox" ng-model="org.signup.signedup" ng-click="orgController.toggleSignup()"> 
							</label>
						</div>
						<div class="trigger_icons clearfix">
							<a href="http://www.facebook.com/profile.php?id={{org.organizationFbId}}">F</a>

							<a href="{{org.website}}"><i class="glyphicon glyphicon-globe"></i></a>

							<a href="tel://{{org.contact1_Phone}}">P</a>

							<a href="mailto://{{org.contact1_Email}}">E</a>
						</div>

						<div class="orgDescription">{{org.organizationDescription}}</div>

						<div class="factoid" ng-if="org.website != ''">
							<div class="header">Website:</div>
							<a class="link" href="{{org.website}}">{{org.website}}</a>
						</div>

						<div class="factoid">
							<div class="header">Address:</div>
							<div class="value" style="text-transform: uppercase;">{{org.address1}}
								<span ng-if="org.address2 != ''">- {{org.address2}}</span>
								<br />{{org.city}}, {{org.state}} {{org.zip}}
							</div>
						</div>

						<div class="factoid">
							<div class="header">Contact:</div>
							<div class="value">{{org.contact1_FirstName}} {{org.contact1_LastName}}
								<div ng-if="org.contact1_Position != ''">{{org.contact1_Position}}</div>
								<a class="link" ng-if="org.contact1_Email != ''" href="mailto:{{org.contact1_Email}}">{{org.contact1_Email}}</a>
								<a class="link"  ng-if="org.contact1_Phone != ''" href="tel:{{org.contact1_Phone}}">{{org.contact1_Phone}}</a>
							</div>
						</div>


					</div>

					
				</div>


				<!-- ACCOUNT MANAGEMENT INTERFACE -->
				<div ng-if="view == 'account'" class=" primaryFrame" style="padding: 16px">
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

					<div ng-if="screen == 3">
						WHEN ARE YOU AVAILABLE?
						<div class="btn-group-vertical">
							<a class="btn btn-raised">Button</a>
							<a class="btn btn-raised">Button</a>
							<a class="btn btn-raised">Button</a>
							<a class="btn btn-raised">Button</a>
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

				
				
			</div>

		</div>

		<!-- FOOTER: MENU -->
		<div class="footer panel panel-default" ng-if="footer.show">
			<div class="footer_nav">
				<div class="link search" ng-click="orgNavigator.searchForOrganizations()"
					ng-class="{active : currentComponent == 'search'}">
					<i class="glyphicon glyphicon-search"></i>
					<div>Search</div>
				</div>


				<div class="link browse" ng-click="orgNavigator.getMyOrganizations()"
					ng-class="{active : view == 'feed' && feedController.mode == 'feed' && (screen == 'feed' || screen == 'empty')}">
					<i class="glyphicon glyphicon-globe"></i>
					<div>Home</div>
				</div>

				<div class="link account" ng-click="loadView('account', 'menu')"
					ng-class="{active : currentComponent == 'account'}">
					<i class="glyphicon glyphicon-user"></i>
					<div>Account</div>
				</div>
			</div>
		</div>	
		
		
		<!-- MODAL TEMPLATES -->
		<div style="display: none;">

			
			
		</div>		

	</body>
</html>