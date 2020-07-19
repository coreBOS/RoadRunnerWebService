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
// Turn off debugging level
$Vtiger_Utils_Log = false;
set_time_limit(0);

require_once 'config.inc.php';
require_once 'include/logging.php';
require_once 'include/utils/utils.php';
//coreBOS_Session::init();
require_once 'vtlib/Vtiger/Module.php';
require_once 'vtlib/Vtiger/Package.php';
require_once 'vtlib/Vtiger/Net/Client.php';
require_once 'modules/com_vtiger_workflow/include.inc';
require_once 'modules/com_vtiger_workflow/tasks/VTEntityMethodTask.inc';
require_once 'modules/com_vtiger_workflow/VTEntityMethodManager.inc';
require_once 'include/events/include.inc';
require_once 'include/Webservices/Utils.php';
require_once 'modules/Users/Users.php';
require_once 'include/Webservices/State.php';
require_once 'include/Webservices/OperationManagerEnDecode.php';
require_once 'include/Webservices/OperationManager.php';
require_once 'include/Webservices/SessionManager.php';
require_once 'include/Webservices/SessionManagerDB.php';
require_once 'include/Webservices/WebserviceField.php';
require_once 'include/Webservices/EntityMeta.php';
require_once 'include/Webservices/VtigerWebserviceObject.php';
require_once 'include/Webservices/VtigerCRMObject.php';
require_once 'include/Webservices/VtigerCRMObjectMeta.php';
require_once 'include/Webservices/VtigerCRMActorMeta.php';
require_once 'include/Webservices/VtigerModuleOperation.php';
require_once 'include/Webservices/DataTransform.php';
require_once 'include/Webservices/WebServiceError.php';
require_once 'include/utils/UserInfoUtil.php';
require_once 'include/Webservices/ModuleTypes.php';
require_once 'include/utils/VtlibUtils.php';
require_once 'include/Webservices/WebserviceEntityOperation.php';
require_once 'include/Webservices/Retrieve.php';
require_once 'include/Webservices/Create.php';
require_once 'include/Webservices/Update.php';
require_once 'include/Webservices/Revise.php';
require_once 'include/Webservices/DescribeObject.php';
require_once 'include/Webservices/RetrieveDocAttachment.php';
require_once 'modules/Emails/mail.php';
require_once 'modules/com_vtiger_workflow/VTSimpleTemplate.inc';
require_once 'modules/com_vtiger_workflow/VTEntityCache.inc';
require_once 'modules/com_vtiger_workflow/VTWorkflowUtils.php';
require_once 'modules/com_vtiger_workflow/expression_engine/include.inc';
require_once 'modules/com_vtiger_workflow/WorkFlowScheduler.php';
require_once 'include/utils/duplicate.php';
checkFileAccessForInclusion("include/language/$default_language.lang.php");
require_once "include/language/$default_language.lang.php";
global $current_user,$adb,$app_strings;
?>