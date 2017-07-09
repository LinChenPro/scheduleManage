<?php
echo "reservation : default";

global $presentationLock;

echo '<form method="post" action="">';

echo '<br>L id <input type="text" name="dmd_lock_id" value="'.$presentationLock->lockId.'">';
echo '<br>U id <input type="text" name="ipt_uid" value="'.$presentationLock->uid.'">';
echo '<br>T id <input type="text" name="tid" value="'.$presentationLock->tid.'">';
echo '<br>S id <input type="text" name="sid" value="'.$presentationLock->sid.'">';
echo '<br>C id <input type="text" name="cid" value="'.$presentationLock->cid.'">';

if($presentationLock->crtStep->nextSteps!=null){
	foreach ($presentationLock->crtStep->nextSteps as $step){
		echo '<input type="submit" value="'.getFrontText($step->stepName).'" onclick="this.form.action=\''.getPageUrlByStepName($step->stepName).'\';return true;">';
	}
}

echo '</form>';
