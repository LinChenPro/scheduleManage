<?php
require_once('includes.php');
$title = "学生日程";

?>
<a href="student_calender_edit.php">点击选课</a>

<?php 
echo '<table class="calendar">';

$calendar = get_student_calendar();
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
			$state = $calendar[$day][$t];
			$v =  ($state==="occupy") ? "occupy" : ("invalid");
			echo '<td class="crenaux_'.$v.'">';
		}
		echo "</td>";
	}
	echo "</tr>";
}
echo "</table>";



