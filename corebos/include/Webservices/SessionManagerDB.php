<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
ini_set('include_path', ini_get('include_path'). PATH_SEPARATOR . 'include/HTTP_Session2');
require_once 'include/HTTP_Session2/Session2.php';

class SessionManagerDB {
	private $maxLife;
	private $idleLife;
	private $sessionName = '__DOESNOTEXIST__';
	private $error;
	private $authenticatedUserId;

	public function __construct($sessionName = '__DOESNOTEXIST__') {
		if (empty($sessionName) || $sessionName == '__DOESNOTEXIST__' || strlen($sessionName)<20) {
			$currentSession = $sessionName = '__DOESNOTEXIST__';
		} else {
			$currentSession = coreBOS_Settings::getSetting($sessionName, '__DOESNOTEXIST__', false);
		}
		$now = time();
		$this->idleLife = $now + GlobalVariable::getVariable('WebService_Session_Idle_Time', 1800);
		if ($currentSession=='__DOESNOTEXIST__') {
			$this->maxLife = $now + GlobalVariable::getVariable('WebService_Session_Life_Span', 86400);
			$this->authenticatedUserId = 0;
			$this->sessionName = '__DOESNOTEXIST__';
		} else {
			$currentSession = json_decode($currentSession, true);
			$this->maxLife = $currentSession['maxLife'];
			//$this->idleLife = $currentSession['idleLife']; // we update idle time
			$this->authenticatedUserId = $currentSession['authenticatedUserId'];
			$this->sessionName = $sessionName;
		}
	}

	public function isValid() {
		$valid = true;
		if ($this->isExpired() || $this->isIdle() || coreBOS_Settings::getSetting($this->sessionName, '__DOESNOTEXIST__', false)=='__DOESNOTEXIST__') {
			$valid = false;
			if ($this->sessionName!='__DOESNOTEXIST__') {
				$this->destroy();
			}
		}
		return $valid;
	}

	/**
	 * Check if session is expired
	 *
	 * @return boolean
	 */
	public function isExpired() {
		$currentSession = coreBOS_Settings::getSetting($this->sessionName, '__DOESNOTEXIST__', false);
		if ($currentSession=='__DOESNOTEXIST__') {
			return true;
		} else {
			return $this->maxLife<time();
		}
	}

	/**
	 * Check if session is idle
	 *
	 * @return boolean
	 */
	public function isIdle() {
		$currentSession = coreBOS_Settings::getSetting($this->sessionName, '__DOESNOTEXIST__', false);
		if ($currentSession=='__DOESNOTEXIST__') {
			return true;
		} else {
			return $this->idleLife<time();
		}
	}

	public function startSession($sid = null, $adoptSession = true, $sname = null) {
		if ($this->sessionName=='__DOESNOTEXIST__') {
			$this->initializeSession($sid, $sname);
			return $this->sessionName;
		}
		$currentSession = coreBOS_Settings::getSetting($this->sessionName, '__DOESNOTEXIST__', false);
		if ($currentSession=='__DOESNOTEXIST__' || !$this->isValid()) {
			$this->initializeSession($sid, $sname);
		}
		return $this->sessionName;
	}

	private function initializeSession($sid = null, $sname = null) {
		if (!$sid || strlen($sid)===0) {
			$sid = null;
		}
		//session name is used for guessing the session id by http_session so pass null.
		@HTTP_Session2::start($sname, $sid);
		if ($this->sessionName!='__DOESNOTEXIST__') {
			$this->destroy();
		}
		$this->sessionName = HTTP_Session2::id();
		$this->save();
	}

	public function getSessionId() {
		return ($this->sessionName=='__DOESNOTEXIST__' ? false : $this->sessionName);
	}

	public function set($var_name, $var_value) {
		$this->$var_name = $var_value;
		$this->save();
	}

	public function get($name) {
		return isset($this->$name) ? $this->$name : null;
	}

	public function getError() {
		return isset($this->error) ? $this->error : 'Session not initialized';
	}

	public function save() {
		if ($this->sessionName!='__DOESNOTEXIST__') {
			$currentSession = array(
				'maxLife' => $this->maxLife,
				'idleLife' => $this->idleLife,
				'authenticatedUserId' => $this->authenticatedUserId,
			);
			coreBOS_Settings::setSetting($this->sessionName, json_encode($currentSession));
		}
	}

	public function destroy() {
		coreBOS_Settings::delSetting($this->sessionName);
		$this->sessionName = '__DOESNOTEXIST__';
		$this->authenticatedUserId = 0;
	}
}
?>
