<?php
require_once('includes.php');

$title = "老师日程编辑";

$update_state = update_teacher_calendar();

$weekCalendar = null;
if($update_state != null && $update_state!=false){
	$weekCalendar = $update_state;
}else{
	$weekCalendar = get_final_calendar();
}


?>
<script type="text/javascript">
function toggle(obj, id){
	var input = document.getElementById(id);
	if(input.value == 0 || input.value == 1){
		input.value = 1 - input.value;
		obj.setAttribute("class","crenaux_"+(input.value==1?"valid":"invalid"));
	}	
}
</script>

<form action="teacher_calender_edit.php" method="post">
	<input type="submit" value="保存"></input>
<?php 
echo '<table class="calendar">';
for($t = -1; $t<48; $t++){
	echo "<tr>";
	for($day = -1; $day<7; $day++){
		if($day<0 && $t<0){
			echo "<td>";
		}else if($day<0){
			$start = get_crenaux_start($t);
			$end   = get_crenaux_end($t);
			echo '<td class="time_header">'.$start.' - '.$end;
		}else if($t<0){
			echo '<td class="week_header">'.$day_in_week[$day];
		}else{
			$state = $weekCalendar[$day][$t];
			if($state === false)$state=0;
			$v =  ($state==="occupy") ? "occupy" : ($state ? "valid" : "invalid");
			echo '<td class="crenaux_'.$v.'" onclick="toggle(this, '."'cal_$day"."_$t'".')">';
			echo "<input type=\"hidden\" name=\"cal_$day"."_$t\" id=\"cal_$day"."_$t\" value=\"$state\">";
		}
		echo "</td>";
	}
	echo "</tr>";
}
echo "</table></form>";


