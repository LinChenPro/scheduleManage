<?php


// outil fonctions
// make_timestamp()
// time()
// functions for this module
$day_in_week = array(
		"星期一","星期二","星期三","星期四","星期五","星期六","星期天",
);

function get_crenaux_start($n){
	$n = $n%48;
	$h = intval($n/2);
	$m = $n%2 * 30;
	
	return sprintf("%02d",$h).":".sprintf("%02d",$m);
}

function get_crenaux_end($n){
	return get_crenaux_start($n+1);
}


function get_week_sessions($day=null){
	if($day==null){
		$day = time();
	}
	
	return array(
			array("day"=>3, "begin"=>5, "end"=>6),
			array("day"=>5, "begin"=>8, "end"=>9),
			array("day"=>5, "begin"=>12, "end"=>13),
	);
}

function get_final_calendar($day=null){
	if($day==null){
		$day = time();
	}
	
	$cal = get_week_calendar($day);
	$sessions = get_week_sessions($day);
	
	foreach($sessions as $session){
		for($t=$session["begin"]; $t<=$session["end"];$t++){
			$cal[$session["day"]][$t] = "occupy";
		}
	}
	return $cal;
}

function get_week_calendar($day=null){
	if($day==null){
		$day = time();
	}

	return array(
			array(false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false),
			array(false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false),
			array(false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false),
			array(false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false),
			array(false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false),
			array(false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false),
			array(false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false,false, false, true,false)
	);
}


function update_teacher_calendar(){
	if(!isset($_POST["cal_0_0"])){
		return null;
	}
	
	$cal = array();
	for($i=0; $i<7; $i++){
		for($j=0; $j<48; $j++){
			$cal[$i][$j] = $_POST["cal_$i"."_$j"];
		}
	}
	
	return $cal;
}


function get_student_sections($day = null){
	GLOBAL $USER;
	
	if($day==null){
		$day = time();
	}

	$my_sections = array();
	for($i=0; $i<10; $i++){
		$my_sections[$i] = array(
			"id"=>$i,
			"sid"=>$USER->id,
			"tid"=>3,
				"catid"=>6,
				"classid"=>2,
				"sectionid"=>$i,
				"day"=>$i%7,
				"begin"=>2+$i*4,
				"end"=>2+$i*4+2,
				"state"=>"d",
				"endtime"=>"",
		);
	}
	return $my_sections;
}

function get_student_calendar(){
	$my_sections = get_student_sections();
	$calendar = array();
	for($i=0; $i<7; $i++){
		for($j=0; $j<48; $j++){
			$calendar[$i][$j] = "vide";
		}
	}
	
	foreach($my_sections as $my_section){
		for($t=$my_section["begin"]; $t<=$my_section["end"];$t++){
			$calendar[$my_section["day"]][$t] = "occupy";
		}
	}
	return $calendar;
}


require_once('css.php');


