<?php
require_once('locklib.php');

$page_name = "RES_FINISH"; // this is the value defined in this file, so the page that the user want demand
$uid = 1; // current user_id;


/* ------------------ handle lock and procedure ( to add this in a function($page_name))--------------- */
// 1 : get neccessary infos to decide the procedure and step demanded
$userDemande = getUserDemand($page_name, $uid);

//2 : demande lock;
$demandLock_result = demandLock($userDemande);

/* ------------------------------------------------------------ */

$demandLock_result->show();