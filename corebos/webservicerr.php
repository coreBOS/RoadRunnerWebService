<?php
/*************************************************************************************************
 * Copyright 2020 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS customizations.
 * You can copy, adapt and distribute the work under the "Attribution-NonCommercial-ShareAlike"
 * Vizsage Public License (the "License"). You may not use this file except in compliance with the
 * License. Roughly speaking, non-commercial users may share and modify this code, but must give credit
 * and share improvements. However, for proper details please read the full License, available at
 * http://vizsage.com/license/Vizsage-License-BY-NC-SA.html and the handy reference for understanding
 * the full license at http://vizsage.com/license/Vizsage-Deed-BY-NC-SA.html. Unless required by
 * applicable law or agreed to in writing, any software distributed under the License is distributed
 * on an  "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and limitations under the
 * License terms of Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (the License).
 *************************************************************************************************
 *  Module       : coreBOS RoadRunner Webservice
 *************************************************************************************************/
ini_set('display_errors', 'stderr');
use Spiral\RoadRunner;
use Nyholm\Psr7;
use Spiral\Goridge;

include 'vendor/autoload.php';
include 'webserviceload.php';

$worker = RoadRunner\Worker::create();
$psr7 = new Psr7\Factory\Psr17Factory();
$worker = new RoadRunner\Http\PSR7Worker($worker, $psr7, $psr7, $psr7);
$metrics = new RoadRunner\Metrics\Metrics(Goridge\RPC\RPC::create(RoadRunner\Environment::fromGlobals()->getRPCAddress()));

/** Workaround to enable capaturing relation query */
global $GetRelatedList_ReturnOnlyQuery;
$GetRelatedList_ReturnOnlyQuery = true;

global $seclog, $log, $_POST, $_REQUEST, $_SERVER;
$API_VERSION = '0.22';
$seclog = LoggerManager::getLogger('SECURITY');
$log = LoggerManager::getLogger('webservice');

function getRequestParamsArrayForOperation($operation) {
	global $operationInput;
	return $operationInput[$operation];
}

function setResponseHeaders() {
	global $cors_enabled_domains, $resp;
	if (isset($_SERVER['HTTP_ORIGIN']) && !empty($cors_enabled_domains)) {
		$parse = parse_url($_SERVER['HTTP_ORIGIN']);
		if ($cors_enabled_domains=='*' || strpos($cors_enabled_domains, $parse['host'])!==false) {
			$resp->withAddedHeader('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN']);
			$resp->withAddedHeader('Access-Control-Allow-Credentials', 'true');
			$resp->withAddedHeader('Access-Control-Max-Age', '86400');    // cache for 1 day
		}
	}
	if (!(isset($_REQUEST['format']) && (strtolower($_REQUEST['format'])=='stream' || strtolower($_REQUEST['format'])=='streamraw'))) {
		$resp->withAddedHeader('Content-type', 'application/json');
	}
}

function writeErrorOutput($operationManager, $error) {
	global $resp;
	setResponseHeaders();
	$state = new State();
	$state->success = false;
	$state->error = $error;
	unset($state->result);
	$output = $operationManager->encode($state);
	//Send email with error.
	$mailto = GlobalVariable::getVariable('Debug_Send_WebService_Error', 'joe@tsolucio.com');
	if ($mailto != '') {
		$wserror = GlobalVariable::getVariable('Debug_WebService_Errors', '*');
		$wsproperty = false;
		if ($wserror != '*') {
			$wsprops = explode(',', $wserror);
			foreach ($wsprops as $wsprop) {
				if (property_exists('WebServiceErrorCode', $wsprop)) {
					$wsproperty = true;
					break;
				}
			}
		}
		if ($wserror == '*' || $wsproperty) {
			global $site_URL;
			require_once 'modules/Emails/mail.php';
			require_once 'modules/Emails/Emails.php';
			$HELPDESK_SUPPORT_EMAIL_ID = GlobalVariable::getVariable('HelpDesk_Support_EMail', 'support@your_support_domain.tld', 'HelpDesk');
			$HELPDESK_SUPPORT_NAME = GlobalVariable::getVariable('HelpDesk_Support_Name', 'your-support name', 'HelpDesk');
			$mailsubject = '[ERROR]: '.$error->code.' - web service call throwed exception.';
			$mailcontent = '[ERROR]: '.$error->code.' '.$error->message."\n<br>".$site_URL;
			unset($_REQUEST['sessionName']);
			$mailcontent.= var_export($_REQUEST, true);
			send_mail('Emails', $mailto, $HELPDESK_SUPPORT_NAME, $HELPDESK_SUPPORT_EMAIL_ID, $mailsubject, $mailcontent);
		}
	}
	$resp->getBody()->write($output);
}

function writeOutput($operationManager, $data) {
	global $resp;
	setResponseHeaders();
	$state = new State();
	if (isset($data['wsmoreinfo'])) {
		$state->moreinfo = $data['wsmoreinfo'];
		unset($data['wsmoreinfo']);
		if (!isset($data['wssuccess'])) {
			$data = $data['wsresult'];
		}
	}
	if (isset($data['wsresult']) && isset($data['wssuccess'])) {
		$state->success = $data['wssuccess'];
		$state->result = $data['wsresult'];
	} else {
		$state->success = true;
		$state->result = $data;
	}
	unset($state->error);
	$output = $operationManager->encode($state);
	$resp->getBody()->write($output);
}

$adminid = Users::getActiveAdminId();
while ($req = $worker->waitRequest()) {
	try {
		global $current_user,$adb,$app_strings;
		$cb_db = PearDatabase::getInstance();
		if (empty($cb_db) || $cb_db->database->_connectionID->errno > 0) {
			$adb->connect();
		}

		$resp = new Laminas\Diactoros\Response();
		$_GET = is_null($req->getQueryParams()) ? array() : $req->getQueryParams();
		$_POST = is_null($req->getParsedBody()) ? array() : $req->getParsedBody();
		$_REQUEST = array_merge($_GET, $_POST);
		if (empty($_REQUEST)) {
			$operationManager = new OperationManager($adb, 'getchallenge', 'json', null);
			writeErrorOutput($operationManager, new WebServiceException(WebServiceErrorCode::$UNKNOWNOPERATION, 'Unknown operation requested'));
			$worker->respond($resp);
			continue;
		}
		$_SERVER = $req->getServerParams();
		if (!GlobalVariable::getVariable('Webservice_Enabled', 1, 'Users', $adminid) || coreBOS_Settings::getSetting('cbSMActive', 0)) {
			$resp->getBody()->write('Webservice - Service is not active');
			$metrics->add('app_metric_counter', 1);
			$worker->respond($resp);
			continue;
		}

		// Full CORS support: preflight options call support
		// Access-Control headers are received during OPTIONS requests
		if (isset($_SERVER['REQUEST_METHOD'])) {
			$cors_enabled_domains = GlobalVariable::getVariable('Webservice_CORS_Enabled_Domains', '', 'Users', $adminid);
			if (isset($_SERVER['HTTP_ORIGIN']) && !empty($cors_enabled_domains)) {
				$parse = parse_url($_SERVER['HTTP_ORIGIN']);
				if ($cors_enabled_domains=='*' || strpos($cors_enabled_domains, $parse['host'])!==false) {
					$resp->withAddedHeader('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN']);
					$resp->withAddedHeader('Access-Control-Allow-Credentials', 'true');
				}
			}
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
				$resp->withAddedHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
			}
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
				$resp->withAddedHeader('Access-Control-Allow-Headers', $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
			}
			if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
				$metrics->add('app_metric_counter', 1);
				$worker->respond($resp);
				continue;
			}
		}

		// some frameworks (namely angularjs and polymer) send information in application/json format, we try to adapt to those system with the next two if
		if (empty($_REQUEST)) {
			$data = json_decode(file_get_contents('php://input'));
			if (is_object($data) && !empty($data->operation)) {
				$_POST = get_object_vars($data);  // only post is affected by this
				$_REQUEST = $_POST;
			}
		}
		$operation = vtws_getParameter($_REQUEST, 'operation');
		$operation = strtolower($operation);
		$format = vtws_getParameter($_REQUEST, 'format', 'json');
		$sessionId = vtws_getParameter($_REQUEST, 'sessionName');

		$sessionManager = new SessionManagerDB($sessionId);
		try {
			$operationManager = new OperationManager($adb, $operation, $format, $sessionManager);
		} catch (WebServiceException $e) {
			$operationManager = new OperationManager($adb, 'getchallenge', 'json', null);
			writeErrorOutput($operationManager, $e);
			$worker->respond($resp);
			continue;
		}
		if (strcasecmp($operation, 'extendsession')===0) {
			$operationManager = new OperationManager($adb, 'getchallenge', 'json', null);
			writeErrorOutput($operationManager, new WebServiceException(WebServiceErrorCode::$OPERATIONNOTSUPPORTED, 'extendsession operation not supported'));
			$metrics->add('app_metric_counter', 1);
			$worker->respond($resp);
			continue;
		}
		// Empty cache
		VTCacheUtils::$_cbcacheinfo_cache = array();
		VTCacheUtils::$_tabidinfo_cache = array();
		VTCacheUtils::$_module_columnfields_cache = null;
		VTCacheUtils::$_alltabrows_cache = false;
		VTCacheUtils::$_blocklabel_cache = array();
		VTCacheUtils::$_fieldinfo_cache = array();
		VTCacheUtils::$_module_columnfields_cache = array();
		VTCacheUtils::$_usercurrencyid_cache = array();
		VTCacheUtils::$_currencyinfo_cache = array();
		VTCacheUtils::$_userprofileid_cache = array();
		VTCacheUtils::$_profile2fieldpermissionlist_cache = array();
		VTCacheUtils::$_subroles_roleid_cache = array();
		VTCacheUtils::$_report_listofmodules_cache = false;
		VTCacheUtils::$_reportmodule_infoperuser_cache = array();
		VTCacheUtils::$_map_listofmodules_cache = false;
		VTCacheUtils::$_reportmodule_subordinateuserid_cache = array();
		VTCacheUtils::$_reportmodule_scheduledinfoperuser_cache = array();
		VTCacheUtils::$_role_related_users_cache = array();

		try {
			if (!$sessionId || strcasecmp($sessionId, 'null')===0) {
				$sessionId = null;
			}

			$input = $operationManager->getOperationInput();
			$sessionName = null;
			if (!$operationManager->isPreLoginOperation() && $sessionManager->isValid()) {
				$sid = $sessionManager->getSessionId();
			} else {
				$sid = false;
			}

			if (!$sid && !$operationManager->isPreLoginOperation()) {
				if (!empty($sessionId) && strcasecmp($operation, 'logout')===0) {
					writeErrorOutput($operationManager, new WebServiceException(WebServiceErrorCode::$SESSIONIDINVALID, 'Session Identifier provided is invalid'));
				} else {
					writeErrorOutput($operationManager, new WebServiceException(WebServiceErrorCode::$AUTHREQUIRED, 'Authentication required'));
				}
				$metrics->add('app_metric_counter', 1);
				$worker->respond($resp);
				continue;
			}

			$userid = $sessionManager->get('authenticatedUserId');
			if (!empty($userid)) {
				$seed_user = new Users();
				$current_user = $seed_user->retrieveCurrentUserInfoFromFile($userid);
				if (!empty($current_user->language)) {
					$app_strings = return_application_language($current_user->language);
				}
				// Empty cache
				$webserviceObject = VtigerWebserviceObject::fromName($adb, 'HelpDesk');
				$handlerPath = $webserviceObject->getHandlerPath();
				require_once $handlerPath;
				$handler = new VtigerModuleOperation($webserviceObject, $current_user, $adb, $log);
				$handler->emptyCache();
				vtws_query('', '', true);
			} else {
				$current_user = null;
			}
			if (empty($current_user) && !$operationManager->isPreLoginOperation()) {
				writeErrorOutput($operationManager, new WebServiceException(WebServiceErrorCode::$INVALIDUSER, 'Invalid user'));
				$metrics->add('app_metric_counter', 1);
				$worker->respond($resp);
				continue;
			}

			$operationInput = $operationManager->sanitizeOperation($input);
			$includes = $operationManager->getOperationIncludes();

			foreach ($includes as $ind => $path) {
				checkFileAccessForInclusion($path);
				require_once $path;
			}
			cbEventHandler::do_action('corebos.audit.action', array((isset($current_user) ? $current_user->id:0), 'Webservice', $operation, 0, date('Y-m-d H:i:s')));
			if (strcasecmp($operation, 'logout')===0) {
				if ($sessionManager->isValid()) {
					$sessionManager->destroy();
					writeOutput($operationManager, array('message' => 'successfull'));
				} else {
					writeErrorOutput($operationManager, new WebServiceException(WebServiceErrorCode::$SESSIONIDINVALID, 'Session Identifier provided is invalid'));
				}
			} elseif (strcasecmp($operation, 'login')===0) {
				$sessionManager->startSession($sid);
				writeOutput($operationManager, $operationManager->runOperation($operationInput, $current_user));
			} else {
				writeOutput($operationManager, $operationManager->runOperation($operationInput, $current_user));
			}
		} catch (WebServiceException $e) {
			writeErrorOutput($operationManager, $e);
		} catch (Exception $e) {
			writeErrorOutput($operationManager, new WebServiceException(WebServiceErrorCode::$INTERNALERROR, 'Unknown Error while processing request'));
		}
		//$resp->getBody()->write(json_encode(vtws_describe($_REQUEST['mod'], $current_user)));
		//$resp->getBody()->write(print_r($req->getQueryParams(), true));
		$metrics->add('app_metric_counter', 1);
		$worker->respond($resp);
	} catch (\Throwable $e) {
		$worker->getWorker()->error((string)$e);
	}
}
