<?php


/* -------------------- definition of classes ------------------- */
class ProcedureLock{
	public $lockId;
	public $type;
	public $crtStep;
	public $uid;
	public $tid;
	public $sid;
	public $cid;
	public $expireTime;
	public $createTime;
	public $lastAcces;
	public $alive;
	
	function isExpired(){
		return $expireTime < new Date();
	}

	function isAlive(){
		if($this->isExpired()){
			$alive = false;
		}
		return $alive; // TODO ? $alive same as !isExpired()
	}
	
	function equals($lock){
		if($lock != null && $lock instanceof ProcedureLock){
			return $this->lockId == $lock->lockId;
		}
	}
	
	function cannotCancel(){
		return $this->isAlive() && $this->crtStep != null && !$this->crtStep->allowCancel;
	}
	
	function cance(){
		if(cannotCancel()){
			return new ActionResult(false, "can not abandon this operatoin", null);
		}else{
			// TODO : delete lock, operations in db
			return new ActionResult();
		}
	}
	
	function gotoStep($demandPage){
		// TODO : change step operation
		// TODO : need atom protect ?
		return new DemandLockResult(this);
	}
	
	function loadCurrentStep(){
		// TODO : need atom protect ?
		return new DemandLockResult(this);
	}
	
	function getReactiveChoice(){
		return new ChoicePage(); // TODO
	}
	
	function getLoadChoice(){
		return new ChoicePage(); // TODO
	}
	
	function getReplaceChoice($demandePage, $demandLock){
		return new ChoicePage(); // TODO
	}
}



// lock(procecure) : pid, uid, tid, sid, cid, type, opeid?,crtStep?, expireTime, createTime
// stepName -> submitData, demandStep
// class represent demand page info
class DemandPageInfo {
	public $step_name;
	public $submit_data;
	
	public $demand_lock_id;
	public $abandon_lock_id;
	
	public $uid;
	public $tid;
	public $sid;
	public $cid;

	function __construct($step_name, $demand_lock_id, $abandon_lock_id){
		$this->step_name = $step_name;
		$this->demand_lock_id = $demand_lock_id;
		$this->abandon_lock_id = $abandon_lock_id;
	}
	
	function setUserIds($uid, $tid, $sid, $cid){
		$this->uid = $uid;
		$this->tid = $tid;
		$this->sid = $sid;
		$this->cid = $cid;
	}	
}

class ChoicePage{
	public $url;
	public $demandPageInfo;
	public $label;
	public $message;
}

class ProcedureType {
	public $name;
	public $firstStep;
	public $steps;
	public $beginPage;
	
	function __construct($name, $beginPage, $firstStep, $steps){
		$this->name = $name;
		$this->beginPage = $beginPage;
		$this->firstStep = $firstStep;
		$this->steps = $steps;
	}
	
	function createNewProcedureLock($demandePage, $demandLock){
		// TODO create function
		return new DemandLockResult();
	}
}

class ProcedureTypeSchedule extends ProcedureType{
	function __construct($firstStep, $steps){
		parent::__construct("SCHEDULE", "schedule_begin_page", $firstStep, $steps);
	}
}

class ProcedureTypeReservation {
	function __construct($firstStep, $steps){
		parent::__construct("RESERVATION", "reservation_begin_page", $firstStep, $steps);
	}
}

class StepInfo {
	public $typeName;
	public $stepName;
	public $nextSteps;
	public $treatFile;
	public $allowCancel;

	function isFirstStep(){
		return $this == getProcedureType()->firstStep;
	}

	function getProcedureType(){
		return getProcedureTypeByName($this->typeName);
	}
	
	function inMyNextStep($stepName){
		foreach ($this->nextSteps as $step){
			if($stepName == $step->stepName){
				return true;
			}
		}
		
		return false;
	}

	function __construct($typeName, $stepName, $nextSteps, $allowCancel, $treatFile){
		$this->typeName = $typeName;
		$this->stepName = $stepName;
		$this->nextSteps = array();
		$this->allowCancel = $allowCancel;
		$this->treatFile = $treatFile;
	}
	
	// constructor without nextSteps
	function __construct($typeName, $stepName, $allowCancel, $treatFile){
		$this->typeName = $typeName;
		$this->stepName = $stepName;
		$this->nextSteps = $nextSteps;
		$this->allowCancel = $allowCancel;
		$this->treatFile = $treatFile;
	}

	function setNextSteps($steps){
		$this->nextSteps = $steps;
	}
	
	function addNextStep($step){
		if($step==null){
			return;
		}
		
		if(!in_array($step, $this->nextSteps)){
			$this->nextSteps[] = $step;
		}
	}
}

class ErrorPageInfo {
	public $message;
	public $nextPages; // array of ChoicePage

	function __construct($message, $nextPages){
		$this->message = $message;
		$this->nextPages = $nextPages;
	}
}

class ActionResult {
	public $statut;	// boolean
	public $errorInfo; // ErrorPageInfo
	
	function __construct(){
		$this->statut = true;
		$this->errorInfo = null;
	}

	function __construct($statut, $errorInfo){
		$this->statut = $statut;
		$this->errorInfo = errorInfo;
	}

	function __construct($statut, $message, $nextPages){
		$this->statut = $statut;
		$this->errorInfo = new ErrorPageInfo($message, $nextPages);
	}
}

class DemandLockResult extends ActionResult{
	public $lock;	// return lock

	function __construct($returnLock){
		$this->statut = true;
		$this->lock = $returnLock;
		$this->errorInfo = null;
	}
	
	function __construct($message, $nextPages){
		$this->statut = false;
		$this->lock = null;
		$this->errorInfo = new ErrorPageInfo($message, $nextPages);
	}

	function setLock($returnLock){
		$this->lock = $returnLock;
	}
	// only a $error_info, or add conflit_info expired_info
}

/* -------------------- static definition of procedure type and steps ------------------- */
$STEP_RES_SELECT	= new StepInfo("RESERVATION", "RES_SELECT", true, "reservation_select");
$STEP_RES_VERIFY	= new StepInfo("RESERVATION", "RES_VERIFY", true, "reservation_verify");
$STEP_RES_PAYING	= new StepInfo("RESERVATION", "RES_PAYING", false, "reservation_paying");
$STEP_RES_CANCEL	= new StepInfo("RESERVATION", "RES_CANCEL", false, "reservation_cancel");
$STEP_RES_FINISH	= new StepInfo("RESERVATION", "RES_FINISH", false, "reservation_finish");
$STEP_RES_DEFAULT	= new StepInfo("RESERVATION", "RES_DEFAULT", false, "reservation_default");
$STEP_RES_SELECT.setNextSteps(array($STEP_RES_VERIFY, $STEP_RES_CANCEL));
$STEP_RES_VERIFY.setNextSteps(array($STEP_RES_PAYING, $STEP_RES_SELECT, $STEP_RES_CANCEL));
$STEP_RES_PAYING.setNextSteps(array($STEP_RES_FINISH, $STEP_RES_CANCEL));

$STEP_SCD_SELECT	= new StepInfo("SCHEDULE", "SCD_SELECT", true, "schedule_select");
$STEP_SCD_CANCEL	= new StepInfo("SCHEDULE", "SCD_CANCEL", false, "schedule_cancel");
$STEP_SCD_FINISH	= new StepInfo("SCHEDULE", "SCD_FINISH", false, "schedule_finish");
$STEP_SCD_DEFAULT	= new StepInfo("SCHEDULE", "SCD_DEFAULT", false, "schedule_default");
$STEP_SCD_SELECT.setNextSteps(array($STEP_SCD_FINISH, $STEP_SCD_CANCEL));

$PROCEDURE_RESERVATION = new ProcedureTypeSchedule(
	$STEP_RES_SELECT,
	array(
		"RES_SELECT" => $STEP_RES_SELECT,
		"RES_VERIFY" => $STEP_RES_VERIFY,
		"RES_PAYING" => $STEP_RES_PAYING,
		"RES_CANCEL" => $STEP_RES_CANCEL,
		"RES_FINISH" => $STEP_RES_FINISH,
		"RES_DEFAULT" => $STEP_RES_DEFAULT
	)
);

$PROCEDURE_SCHEDULE = new ProcedureTypeSchedule(
	$STEP_SCD_SELECT,
	array(
		"SCD_SELECT" => $STEP_SCD_SELECT,
		"SCD_CANCEL" => $STEP_SCD_CANCEL,
		"SCD_FINISH" => $STEP_SCD_FINISH,
		"SCD_DEFAULT" => $STEP_RES_DEFAULT
	)
);

$PRCEDURE_TYPES = array(
	"RESERVATION" => $PROCEDURE_RESERVATION,
	"SCHEDULE" => $PROCEDURE_SCHEDULE
);


/* -------------------- operating functions ------------------- */

// get procedure type by name
function getProcedureTypeByName($typeName){
	return $_GLOBALS['PRCEDURE_TYPES'][$typeName];
}

// get procedure type by name
function getStepByName($stepName){
	foreach ($_GLOBALS['PRCEDURE_TYPES'] as $typeName => $type){
		$step = $step[$stepName];
		if($step != null){
			return $step;
		}
	}
	
	return null;
}

function getUserDemand($step_name, $uid, $default_step_name){
	$demand_lock_id = $_RESUEST["dmd_lock_id"];
	$abandon_lock_id = $_RESUEST["abd_lock_id"];
	$demandPage = new DemandPageInfo(($step_name!=null?$step_name : $default_step_name), $demand_lock_id, $abandon_lock_id);

	$demandPage->uid = $uid;
	$demandPage->tid = $_RESUEST["tid"];
	$demandPage->sid = $_RESUEST["sid"];
	$demandPage->cid = $_RESUEST["cid"];
	$demandPage->submit_data = $_RESUEST["sbm_data"];

	return $demandPage;
}

function gotopage_error_info($errorPageInfo){
	// *set params
	// *include errorpage;
}

function getLockById($lock_id, $onlyAlive = false){
	// TODO	
}

function getUserLiveLock($userid){
	// TODO: db function
}

function getCancelChoice(){
	return new ChoicePage(); // TODO
}

function demandLock($demandPage){
 	$demandStep = getStepByName($demandPage->step_name);
	$demandLock = getLockById($demandPage->demand_lock_id);
	$abandonLock = getLockById($demandPage->abandon_lock_id, true); 
	$userAliveLock =  getUserLiveLock($demandPage->uid);
	
	$res = null;
	if($demandLock==null && $abandonLock==null && $userAliveLock == null){
		// simple new lock
		$res = $demandStep->getProcedureType()->createNewProcedureLock($demandePage, $demandLock);
	}else if($demandLock != null && $demandLock->isAlive()){
		// demand alive lock. in this case, $abandonLock must null, and $userAliveLock must = $demandLock
		if($demandLock->crtStep->inMyNextStep($demandStep)){
			// same lock goto next step
			$res = $demandLock->gotoStep($demandPage);
		}else{
			// load current lock and step
			$res = $demandLock->loadCurrentStep();
		}
	}else if($demandLock != null && !$demandLock->isAlive() && $abandonLock == null && $userAliveLock == null){
		// demand lock expired, offer choice between cancel and reactive
		$pageCancel = getCancelChoice();
		$pageRenew = $demandLock->getReactiveChoice();
		$res = new ActionResult(false, $message, array($pageCancel, $pageRenew));
	}else if(($demandLock==null || !$demandLock->isAlive()) && $abandonLock == null && $userAliveLock != null){
		// no alive demande lock, has alive lock
		$pageCancel = getCancelChoice();
		$pageLoadAlive = $userAliveLock->getLoadChoice();
		if(!$userAliveLock->cannotCancel() && !$userAliveLock->equals($demandLock)){
			// offer choice between cancel, replace and load alive lock
			$pageReplace =  $userAliveLock->getReplaceChoice($demandePage, $demandLock);
			$res = new ActionResult(false, $message, array($pageCancel, $pageReplace, $pageLoadAlive));
		}else{
			// offer choice between cancel and load alive lock
			$res = new ActionResult(false, $message, array($pageCancel, $pageLoadAlive));
		}
	}else if(($demandLock==null || !$demandLock->isAlive()) && $abandonLock != null && $userAliveLock != null){
		if(!$userAliveLock->cannotCancel() && !$userAliveLock->equals($demandLock)){
			$cancel_res = $abandonLock->cancel();
			if($cancel_res->statut == false){
				// cancel fail, show error
				$res = $cancel_res;
			}else{
				// cancel succes, create new lock;
				$res = $demandStep->getProcedureType()->createNewProcedureLock($demandePage, $demandLock);
			}
		}else{
			$pageCancel = getCancelChoice();
			$pageLoadAlive = $userAliveLock->getLoadChoice();
			$res = new ActionResult(false, $message, array($pageCancel, $pageLoadAlive));
		}
	}
	
	return $res;	
}
