<?php
require_once('locklib.php');

$default_step_name = "RES_DEFAULT";
$step_name = "RES_SELECT"; // this is the value defined in this file (or in request ?), so the page that the user want demand
$uid = 1; // current user_id;



/* ------------------ handle lock and procedure ( to add this in a function($page_name))--------------- */
// 1 : get neccessary infos to decide the procedure and step demanded
$userDemande = getUserDemand($step_name, $uid, $default_step_name);

//3 : demande lock;
$demandLock_result = demandLock($userDemande);

if($demandLock_result->statut == "occupied"){
	// resource occupied by others
	gotopage_error_info($demandLock_result->error_info);
}else if($demandLock_result->statut == "conflit"){
	// the user has another lock
	gotopage_conflit_selection($demandLock_result->conflit_info);
}else if($demandLock_result->statut == "exist"){
	if($demandLock_result->lock.isValid()){
		// the user has a valid lock
		gotopage($demandLock_result->page, $demandLock_result->lock, $demandLock_result->operation);
	}else{
		// the user has a lock exired
		gotopage_expired_selection($demandLock_result->expired_info);
	}
}else{
	// the user has a new lock
	gotopage($demandLock_result->page, $demandLock_result->lock, $demandLock_result->operation);
}






