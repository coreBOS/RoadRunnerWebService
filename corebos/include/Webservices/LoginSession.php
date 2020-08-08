<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
require_once 'include/Webservices/SessionManagerDB.php';
require_once 'include/Webservices/AuthToken.php';
require_once 'include/Webservices/Login.php';

function cbwsLoginSession($username, $loggedinat, $hashaccess, $sessionid) {
	$user = new Users();
	$userId = $user->retrieve_user_id($username);

	if (empty($userId)) {
		throw new WebServiceException(WebServiceErrorCode::$AUTHREQUIRED, 'Given user cannot be found');
	}
	$serverID = preg_replace('/[^a-zA-Z0-9_]/', '', $loggedinat);
	$pkey = coreBOS_Settings::getSetting('cbwsLoginSync'.$serverID, null);
	if ($pkey == null) {
		throw new WebServiceException(WebServiceErrorCode::$INVALIDTOKEN, 'Specified system is invalid');
	}

	$token = vtws_getActiveToken($userId);
	if ($token == null) {
		throw new WebServiceException(WebServiceErrorCode::$INVALIDTOKEN, 'Specified token is invalid or expired');
	}
	$accessCrypt = hash('sha512', $token.$pkey);
	if (!hash_equals($accessCrypt, $hashaccess)) {
		throw new WebServiceException(WebServiceErrorCode::$INVALIDUSERPWD, 'Invalid username or password');
	}
	$user = $user->retrieveCurrentUserInfoFromFile($userId);
	if ($user->status != 'Inactive') {
		// create session
		$sessionManager = new SessionManagerDB($sessionid);
		$sessionManager->set('sessionName', $sessionid);
		$sessionManager->set('authenticatedUserId', $userId);
		return array(true);
	}
	throw new WebServiceException(WebServiceErrorCode::$AUTHREQUIRED, 'Given user is inactive');
}
?>
