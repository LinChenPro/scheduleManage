<?php
/* tables
CREATE TABLE `ProcedureLock` (
  `lockid` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `lockType` varchar(20) NOT NULL,
  `crtStep` varchar(20) DEFAULT NULL,
  `uid` int(6) NOT NULL,
  `tid` int(6) NOT NULL,
  `sid` int(6) DEFAULT NULL,
  `cid` int(6) DEFAULT NULL,
  `expireTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `createTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastAcces` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `statut` varchar(10) DEFAULT 'ALIVE',
  PRIMARY KEY (`lockid`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8

statut : ALIVE | ARCHIVE | CANCELLED

grant all on schedules.* to moodle31user@localhost identified by 'pass'

*/

/* db temp functions*/
$servername = "localhost";
$username = "moodle31user";
$password = "pass";
$dbname = getScheduleDbName();

function getScheduleDbName(){
	// return "moodle31Proto";
	return "schedules";
}


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
function query($sql){
	global $conn;
	$res = $conn->query($sql);

	if(!$res===TRUE && !$res->num_rows>=0){
		echo  $conn->error;
	}
	return $res;
}

// test
// $sql = "insert into ProcedureLock(type, crtStep, uid, tid) values('tt', 'tt', 1,1)";
// echo query($sql);


/* -------------------- definition of classes ------------------- */
class ProcedureLock{
	public $lockId;
	public $lockType;
	public $crtStep;
	public $uid;
	public $tid;
	public $sid;
	public $cid;
	public $expireTime;
	public $createTime;
	public $lastAcces;
	public $statut;
	
	function isExpired(){
		$now = new DateTime(); 
		return $this->expireTime < $now->format('Y-m-d H:i:s');
	}

	function isAlive(){
		return !$this->isExpired() && ($this->statut == "ALIVE");
	}

	function canRestart(){
		return !$this->isAlive() && ($this->statut != "ARCHIVE");
	}
	
	function equals($lock){
		if($lock != null && $lock instanceof ProcedureLock){
			return $this->lockId == $lock->lockId;
		}
	}
	
	function cannotCancel(){
		return ($this->statut != "ALIVE") || ($this->isAlive() && $this->crtStep != null && !$this->crtStep->allowCancel);
	}
	
	function cancel(){
		if($this->cannotCancel()){
			return ActionResult::getInstance(false, getFrontText("CAN_NOT_CANCEL"), null); // TODO 2 : detail info, if statut cancelled ?
		}else{
			// TODO B: need atom protect and error traitement?
			$sql = "update ProcedureLock set statut='CANCELLED' where statut='ALIVE' and expireTime>CURRENT_TIMESTAMP and lockid=_LOCKID";
			$sql = str_replace("_LOCKID", $this->lockId, $sql);
			query($sql);
			$reloadCurrentLock = getLockById($this->lockid);


			if($reloadCurrentLock==null || $reloadCurrentLock->statut=='CANCELLED'){
				return new ActionResult();
			}else{
				return ActionResult::getInstance(false, getFrontText("CAN_NOT_CANCEL"), null); // TODO 2 : detail info
			}
		}
	}	
	
	function gotoStep($demandPage){
		// TODO B: need atom protect ?
		$res = $this->lockType->toNextStep($this, $demandPage);
		return $res;
	}
	
	function loadCurrentStep(){
		// TODO B: need atom protect ?
		return new DemandLockResult($this);
	}
	
	function getReactiveChoice(){
		$url = getPageUrlByStepName($this->lockType->firstStep->stepName);
		return new ChoicePage($url, $this->getRestartDemand(), "RESTART", "RESTART"); 
	}
	
	function getLoadChoice(){
		$url = getPageUrlByStepName($this->crtStep->stepName);
		return new ChoicePage($url, $this->getLoadDemand(), "LOAD", "LOAD"); // TODO 2 : demand->step = "_RELOAD" ? i think no?
	}
	
	function getReplaceChoice($demandPage, $demandLock){
		$choiceLabel = "REPLACE";
		$choiceMessage = "REPLACE";
		if($demandLock != null){
			$url = getPageUrlByStepName($demandLock->lockType->firstStep->stepName);
			$demand = $demandLock->getRestartDemand();
			$demand->abandon_lock_id = $this->lockId;
			return new ChoicePage($url, $demand, $choiceLabel, $choiceMessage);
		}else if($demandPage != null){
			$demandType = getStepByName($demandPage->step_name)->getProcedureType();
			$url = getPageUrlByStepName($demandType->firstStep->stepName);
			$newDemand = new DemandPageInfo($demandType->firstStep->stepName, null, $this->lockId);
			$newDemand->setUserIds($demandPage->uid, $demandPage->tid, $demandPage->sid, $demandPage->cid);
			return new ChoicePage($url, $newDemand, $choiceLabel, $choiceMessage); 			
		}else{
			return null;
		}
	}

	function getRestartDemand(){
		$demand = new DemandPageInfo($this->lockType->firstStep->stepName, null, null);
		$demand->setUserIds($this->uid, $this->tid, $this->sid, $this->cid);
		return $demand;
	}

	function getLoadDemand(){
		$demand = new DemandPageInfo($this->crtStep->stemName, $this->lockId, null);
		$demand->setUserIds($this->uid, $this->tid, $this->sid, $this->cid);
		return $demand;
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
	
	function printAsInputs(){
 		echo '<input type="hidden" name="ipt_step_name" value="'.$this->step_name.'">';
 		echo '<input type="hidden" name="ipt_uid" value="'.$this->uid.'">';
		echo '<input type="hidden" name="sbm_data" value="'.$this->submit_data.'">';
		echo '<input type="hidden" name="dmd_lock_id" value="'.$this->demand_lock_id.'">';
		echo '<input type="hidden" name="abd_lock_id" value="'.$this->abandon_lock_id.'">';
		echo '<input type="hidden" name="tid" value="'.$this->tid.'">';
		echo '<input type="hidden" name="sid" value="'.$this->sid.'">';
		echo '<input type="hidden" name="cid" value="'.$this->cid.'">';
	}
}

class ChoicePage{
	public $url;
	public $demandPageInfo;
	public $label;
	public $message;

	function __construct($url, $demandPageInfo, $label, $message){
		$this->url = $url;
		$this->demandPageInfo = $demandPageInfo;
		$this->label = $label;
		$this->message = $message;
	}
	
	function showAsButton(){
		echo '<form class="btn_'.$this->label.'" method="post" action="'.$this->url.'">';
		if($this->demandPageInfo != null){
			$this->demandPageInfo->printAsInputs();
		}
		echo '<input type="submit" value="'.getFrontText($this->label).'">';
		echo '</form>';
	}
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

	function getCancelChoice($label="CANCEL", $message="CANCEL"){
		return new ChoicePage(getIncludeUrlByStepName($this->beginPage), null, $label, $message);
	}
	
	function createNewProcedureLock($demandPage, $demandLock){
		$uid = $demandPage->uid;
		$tid = $demandPage->tid;
		$sid = $demandPage->sid;
		$cid = $demandPage->cid;
		if($uid==null || $tid==null ){ // TODO B: condition selon type
			if($demandLock != null && !$demandLock->isAlive()){
			$uid = $demandLock->uid;
			$tid = $demandLock->tid;
			$sid = $demandLock->sid;
			$cid = $demandLock->cid;
			}
		}

		if($uid==null || $tid==null){  // TODO B: condition selon type
			return new UrlResult(getIncludeUrlByStepName($this->beginPage));
		}

		/******* atom begin ********/
		$res = null;
		// verify occupation
		$haisConflit = hasConflitLock($uid, $tid, $sid);

		if($haisConflit){
			$res = DemandLockResult::getInstance(getFrontText("OCCUPIED"), $this->getCancelChoice("OK", "OK"));  // TODO 2: occupied detail info
		}else{
			$lockType = $this->name;
			$crtStep = $this->firstStep->stepName;

			$sid = $sid==null?"NULL":$sid;
			$cid = $cid==null?"NULL":$cid;

			$sql = "insert into ProcedureLock(lockType, crtStep, uid, tid, sid, cid, expireTime) values('$lockType', '$crtStep', $uid, $tid, $sid, $cid, date_add(CURRENT_TIMESTAMP, interval 20 minute))";
			$insertRes = query($sql);
			if($insertRes === TRUE){
				$res = new DemandLockResult(getUserLiveLock($uid));
			}else{
// 				echo $sql;
				$res = DemandLockResult::getInstance(getFrontText("SQL_ERROR"), $this->getCancelChoice("OK", "OK"));
			}
		}
		/******* atom end ********/

		return $res;

	}

	function toNextStep($lock, $demand){
		return null;
	}
}

class ProcedureTypeSchedule extends ProcedureType{
	function __construct($firstStep, $steps){
		parent::__construct("SCHEDULE", "schedule_begin_page", $firstStep, $steps);
	}

	function toNextStep($lock, $demand){
		// TODO C : change step selon params, need transaction
		$lock->crtStep = getStepByName($demand->step_name);
		if($lock->crtStep->isArchiveStep()){
			$lock->statut = "ARCHIVE";
		}
		$lock = updateLockToDB($lock);
		return new DemandLockResult($lock); 
	}
}

class ProcedureTypeReservation extends ProcedureType{
	function __construct($firstStep, $steps){
		parent::__construct("RESERVATION", "reservation_begin_page", $firstStep, $steps);
	}

	function toNextStep($lock, $demand){
		// TODO C : change step selon params, need transaction
		$lock->crtStep = getStepByName($demand->step_name);
		if($lock->crtStep->isArchiveStep()){
			$lock->statut = "ARCHIVE";
		}
		$lock = updateLockToDB($lock);
		// var_dump($lock);
		return new DemandLockResult($lock); 
	}
}

class StepInfo {
	public $typeName;
	public $stepName;
	public $nextSteps;
	public $treatFile;
	public $allowCancel;
	public $isArchiveStep = false;

	function isFirstStep(){
		return $this == getProcedureType()->firstStep;
	}

	function getProcedureType(){
		return getProcedureTypeByName($this->typeName);
	}
	
	function inMyNextStep($step){
		$stepName = $step->stepName;
		foreach ($this->nextSteps as $stepI){
			if($stepName == $stepI->stepName){
				return true;
			}
		}
		
		return false;
	}

	// constructor without nextSteps
	function __construct($typeName, $stepName, $allowCancel, $treatFile){
		$this->typeName = $typeName;
		$this->stepName = $stepName;
		$this->nextSteps = array();
		$this->allowCancel = $allowCancel;
		$this->treatFile = $treatFile;
	}

	static function getInstance($typeName, $stepName, $nextSteps, $allowCancel, $treatFile){
		$obj = new StepInfo($typeName, $stepName, $allowCancel, $treatFile);
		$obj->nextSteps = $nextSteps;
		return $obj;
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

	function isArchiveStep(){
		return $this->isArchiveStep;
	}

	function setArchiveStep($isArchiveStep){
		$this->isArchiveStep = $isArchiveStep;
		return $this;
	}

}

class ErrorPageInfo {
	public $message;
	public $nextPages; // array of ChoicePage

	function __construct($message, $nextPages){
		$this->message = $message;
		if(is_array($nextPages)){
			$this->nextPages = $nextPages;
		}else if($nextPages==null){
			$this->nextPages = array();
		}else{
			$this->nextPages = array($nextPages);
		}
	}
}

class ActionResult {
	public $statut;	// boolean
	public $errorInfo; // ErrorPageInfo
	
	function __construct($statut = true, $errorInfo = null){
		$this->statut = $statut;
		$this->errorInfo = $errorInfo;
	}

	static function getInstance($statut, $message, $nextPages){
		return new ActionResult($statut, new ErrorPageInfo($message, $nextPages));
	}

	function show(){
		if($this->statut){
			$this->displayObj();
		}else{
			echo $this->errorInfo->message;
			if($this->errorInfo->nextPages != null){
				foreach ($this->errorInfo->nextPages as $choice){
					$choice->showAsButton();
				}
			}
		}
	}

	function displayObj(){
	}
}

class UrlResult extends ActionResult{
	public $url; // text

	function __construct($url){
		$this->statut = true;
		$this->url = $url;
		$this->errorInfo = null;
	}

	function displayObj(){
		if($url != null){
			echo "<script type=\"text/javascript\">window.location.replace(\"".$url."\");</script>"; // TODO A : test
		}
	}
}
class DemandLockResult extends ActionResult{
	public $lock;	// return lock

	function __construct($returnLock){
		$this->statut = true;
		$this->lock = $returnLock;
		$this->errorInfo = null;
	}
	
	static function getInstance($message, $nextPages){
		$obj = new DemandLockResult(null);
		$obj->statut = false;
		$obj->errorInfo = new ErrorPageInfo($message, $nextPages);
		return $obj;
	}

	function setLock($returnLock){
		$this->lock = $returnLock;
	}

	function displayObj(){
		if($this->lock != null){
			$GLOBALS['presentationLock'] = $this->lock;
			// var_dump($this->lock);
			include($this->lock->crtStep->treatFile.".php");
		}
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

$STEP_RES_FINISH->setArchiveStep(true);
$STEP_RES_SELECT->setNextSteps(array($STEP_RES_VERIFY, $STEP_RES_CANCEL));
$STEP_RES_VERIFY->setNextSteps(array($STEP_RES_PAYING, $STEP_RES_SELECT, $STEP_RES_CANCEL));
$STEP_RES_PAYING->setNextSteps(array($STEP_RES_FINISH, $STEP_RES_CANCEL));


$STEP_SCD_SELECT	= new StepInfo("SCHEDULE", "SCD_SELECT", true, "schedule_select");
$STEP_SCD_CANCEL	= new StepInfo("SCHEDULE", "SCD_CANCEL", false, "schedule_cancel");
$STEP_SCD_FINISH	= new StepInfo("SCHEDULE", "SCD_FINISH", false, "schedule_finish");
$STEP_SCD_DEFAULT	= new StepInfo("SCHEDULE", "SCD_DEFAULT", false, "schedule_default");

$STEP_SCD_FINISH->setArchiveStep(true);
$STEP_SCD_SELECT->setNextSteps(array($STEP_SCD_FINISH, $STEP_SCD_CANCEL));

$PROCEDURE_RESERVATION = new ProcedureTypeReservation(
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
	return $GLOBALS['PRCEDURE_TYPES'][$typeName];
}

// get procedure type by name
function getStepByName($stepName){
	foreach ($GLOBALS['PRCEDURE_TYPES'] as $typeName => $type){
		$step = $type->steps[$stepName];
		if($step != null){
			return $step;
		}
	}
	
	return null;
}

function hasConflitLock($uid, $tid, $sid){
	$sql = "select count(*) as CNT from ProcedureLock where statut='ALIVE' and expireTime>CURRENT_TIMESTAMP and (uid=_UID or sid in(_USER_ID_LIST) or tid in(_USER_ID_LIST))";
	$userList = ($tid==null? -1 : $tid).",".($sid==null? -1 : $sid);
	$sql = str_replace(array("_UID", "_USER_ID_LIST"), array($uid, $userList), $sql);
	$res = query($sql);
	if ($res->num_rows > 0) {
	    while($row = $res->fetch_assoc()) {
	    	return $row["CNT"]>0;
	    }
	}
	return true;
}

function getUserDemand($step_name, $uid, $default_step_name=null){
	$demand_lock_id = $_REQUEST["dmd_lock_id"];
	$abandon_lock_id = $_REQUEST["abd_lock_id"];
	$step_name = $step_name!=null ? $step_name : $default_step_name;
	$step_name = $step_name!=null ? $step_name : $_REQUEST["ipt_step_name"];

	$demandPage = new DemandPageInfo($step_name, $demand_lock_id, $abandon_lock_id);
	$demandPage->uid = $uid!=null ? $uid : $_REQUEST["ipt_uid"]; // TODO 3 safe?
	$demandPage->tid = $_REQUEST["tid"];
	$demandPage->sid = $_REQUEST["sid"];
	$demandPage->cid = $_REQUEST["cid"];
	$demandPage->submit_data = $_REQUEST["sbm_data"];
	
	return $demandPage;
}

function gotopage_error_info($errorPageInfo){
	// *set params
	// *include errorpage;
}

function getLockById($lock_id, $onlyAlive = false){
	if($lock_id==null){
		return null;
	}
	$sql = "select * from ProcedureLock where lockid=$lock_id" . ($onlyAlive ? " and statut='ALIVE' and expireTime>CURRENT_TIMESTAMP" : "");
	return getLockFromQuery($sql);
}

function getUserLiveLock($userid){
	$sql = "select * from ProcedureLock where uid=$userid and statut='ALIVE' and expireTime>CURRENT_TIMESTAMP ORDER BY lastAcces desc";
	return getLockFromQuery($sql);
}

function getLockFromQuery($sql){
	$res = query($sql);
	if ($res->num_rows > 0) {
	    // output data of each row
	    while($row = $res->fetch_assoc()) {
	        $lock = new ProcedureLock();
			$lock->lockId = $row["lockid"];
			$lock->lockType = getProcedureTypeByName($row["lockType"]);
			$lock->crtStep = getStepByName($row["crtStep"]);
			$lock->uid = $row["uid"];
			$lock->tid = $row["tid"];
			$lock->sid = $row["sid"];
			$lock->cid = $row["cid"];
			$lock->expireTime = $row["expireTime"];
			$lock->createTime = $row["createTime"];
			$lock->lastAcces = $row["lastAcces"];
			$lock->statut = $row["statut"];
			return $lock;
	    }
	} else {
	    return null;
	}
}

function updateLockToDB($lock){
	$sql = "update ProcedureLock set crtStep='".$lock->crtStep->stepName."', expireTime='".$lock->expireTime."', statut='".$lock->statut."' where lockid=".$lock->lockId;
	// echo " $sql ";
	$res = query($sql);
	return getLockById($lock->lockId);
}


function demandLock($demandPage){
 	$demandStep = getStepByName($demandPage->step_name);
	$demandLock = getLockById($demandPage->demand_lock_id);
	$abandonLock = getLockById($demandPage->abandon_lock_id, true); 
	$userAliveLock =  getUserLiveLock($demandPage->uid);
	
	$res = null;
	// var_dump($demandLock);
	if($demandLock==null && $abandonLock==null && $userAliveLock == null){
		// simple new lock
		$res = $demandStep->getProcedureType()->createNewProcedureLock($demandPage, $demandLock);
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
		$pageCancel = $demandStep->getProcedureType()->getCancelChoice();
		$pageRenew = $demandLock->getReactiveChoice();
		$res = ActionResult::getInstance(false, "MSG_LOCK_EXPIRED", array($pageCancel, $pageRenew));
	}else if(($demandLock==null || !$demandLock->isAlive()) && $abandonLock == null && $userAliveLock != null){
		// no alive demande lock, has alive lock
		$pageCancel = $demandStep->getProcedureType()->getCancelChoice();
		$pageLoadAlive = $userAliveLock->getLoadChoice();
		if(!$userAliveLock->cannotCancel() && !$userAliveLock->equals($demandLock)){
			// offer choice between cancel, replace and load alive lock
			$pageReplace =  $userAliveLock->getReplaceChoice($demandPage, $demandLock);
			$res = ActionResult::getInstance(false, "MSG_OTHER_REPLACABLE_ALIVE", array($pageCancel, $pageReplace, $pageLoadAlive));
		}else{
			// offer choice between cancel and load alive lock
			$res = ActionResult::getInstance(false, "MSG_OTHER_NOREPLACABLE_ALIVE", array($pageCancel, $pageLoadAlive));
		}
	}else if(($demandLock==null || !$demandLock->isAlive()) && $abandonLock != null && $userAliveLock != null){
		if(!$userAliveLock->cannotCancel() && !$userAliveLock->equals($demandLock)){
			$cancel_res = $abandonLock->cancel();
			if($cancel_res->statut == false){
				// cancel fail, show error
				$res = $cancel_res;
			}else{
				// cancel succes, create new lock;
				$res = $demandStep->getProcedureType()->createNewProcedureLock($demandPage, $demandLock);
			}
		}else{
			$pageCancel = $demandStep->getProcedureType()->getCancelChoice();
			$pageLoadAlive = $userAliveLock->getLoadChoice();
			$res = ActionResult::getInstance(false, "MSG_CANNOT_REPLACE", array($pageCancel, $pageLoadAlive));
		}
	}
	
	return $res;	
}

/************* txt messages, regle textuel*************/
$TextMap = array(
	"MSG_RESTART" => "RESTART",
	"OCCUPIED" => "OCCUPIED"
);

function getFrontText($key){
	global $TextMap;
	$res = $TextMap[$key];
	if($res != null){
		return $res;
	}

	$res = $TextMap["MSG_$key"];
	if($res != null){
		return $res;
	}

	return $key;
}


function getIncludeUrlByStepName($stepName, $autocase = true){
	if($autocase){
		return strtolower($stepName).".php";
	}else{
		return $stepName.".php";
	}
}

function getPageUrlByStepName($stepName, $autocase = false){
	if($autocase){
		return strtolower($stepName).".php";
	}else{
		return $stepName.".php";
	}
}

