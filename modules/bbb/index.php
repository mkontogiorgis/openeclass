<?php

/* ========================================================================
 * Open eClass
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2014  Greek Universities Network - GUnet
 * A full copyright notice can be read in "/info/copyright.txt".
 * For a full list of contributors, see "credits.txt".
 *
 * Open eClass is an open platform distributed in the hope that it will
 * be useful (without any warranty), under the terms of the GNU (General
 * Public License) as published by the Free Software Foundation.
 * The full license can be read in "/info/license/license_gpl.txt".
 *
 * Contact address: GUnet Asynchronous eLearning Group,
 *                  Network Operations Center, University of Athens,
 *                  Panepistimiopolis Ilissia, 15784, Athens, Greece
 *                  e-mail: info@openeclass.org
 * ========================================================================
 */


$require_current_course = TRUE;
$require_login = TRUE;
$require_help = TRUE;
$helpTopic = 'bbb';

require_once '../../include/baseTheme.php';

require_once 'include/sendMail.inc.php';

// For creating bbb urls & params
require_once 'bbb-api.php';

require_once 'include/lib/modalboxhelper.class.php';
ModalBoxHelper::loadModalBox();

/* * ** The following is added for statistics purposes ** */
require_once 'include/action.php';
$action = new action();
$action->record(MODULE_ID_BBB);
/* * *********************************** */

$nameTools = $langBBB;

global $langBBBImportRecordingsΟΚ,$langBBBMaxUsersJoinError;

// guest user not allowed
if (check_guest()) {
    $tool_content .= "<p class='caution'>$langNoGuest</p>";
    draw($tool_content, 2);
}
load_js('tools.js');
load_js('tagsinput');
load_js('jquery');
load_js('jquery-ui');
load_js('jquery-ui-timepicker-addon.min.js');
load_js('validation.js');

$head_content .= "<link rel='stylesheet' type='text/css' href='{$urlAppend}js/jquery-ui-timepicker-addon.min.css'>
<script type='text/javascript'>
$(function() {
$('input[name=start_session]').datetimepicker({
    dateFormat: 'yy-mm-dd', 
    timeFormat: 'hh:mm'
    });
});
</script>";

load_js('jquery.multiselect.min.js');
$head_content .= "<script type='text/javascript'>$(document).ready(function () {
        $('#select-groups').multiselect({
            selectedText: '$langJQSelectNum',
            noneSelectedText: '$langJQNoneSelected',
            checkAllText: '$langJQCheckAll',
            uncheckAllText: '$langJQUncheckAll'
        });
});</script>
<link href='../../js/jquery.multiselect.css' rel='stylesheet' type='text/css'>";
        
$head_content .= "
<script type='text/javascript'>
		function onAddTag(tag) {
			alert('Added a tag: ' + tag);
		}
		function onRemoveTag(tag) {
			alert('Removed a tag: ' + tag);
		}
		
		function onChangeTag(input,tag) {
			alert('Changed a tag: ' + tag);
		}
		
		$(function() {
			$('#tags_1').tagsInput({width:'auto'});
		});
</script>
";

if ($is_editor) {
    if (isset($_GET['add']) or isset($_GET['choice'])) {
        $tool_content .= "
        <div id='operations_container'>
          <ul id='opslist'>
            <li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code'>$langBack</a></li>
          </ul>
        </div>";
    } else {
        $tool_content .= "
        <div id='operations_container'>
          <ul id='opslist'>
            <li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;add=1'>$langNewBBBSession</a></li>
          </ul>
        </div>";
    }
    
}
if (isset($_GET['add'])) {
    $nameTools = $langNewBBBSession;
    $navigation[] = array('url' => "$_SERVER[SCRIPT_NAME]?course=$course_code", 'name' => $langBBB);
    new_bbb_session();
}
elseif(isset($_POST['update_bbb_session']))
{ 
    update_bbb_session($_GET['id'],$_POST['title'], $_POST['desc'], $_POST['start_session'], $_POST['type'] ,$_POST['status'],(isset($_POST['notifyUsers']) ? '1' : '0'),$_POST['minutes_before'],$_POST['external_users'],$_POST['record'],$_POST['sessionUsers']);
}
elseif(isset($_GET['choice']))
{
    switch($_GET['choice'])
    {
        case 'edit':
            edit_bbb_session($_GET['id']);
            break;
        case 'do_delete':
            delete_bbb_session($_GET['id']);
            break;
        case 'do_disable':
            disable_bbb_session($_GET['id']);
            break;
        case 'do_enable':
            enable_bbb_session($_GET['id']);
            break;
        case 'do_join':
            #check if there is any record-capable bbb server. Otherwise notify users
            if($_GET['record']=='true' && Database::get()->querySingle("SELECT count(*) count FROM bbb_servers WHERE enabled='true' AND enable_recordings='true'")->count == 0)
            {
                $tool_content .= "<p class='noteit'><b>$langNote</b>:<br />$langBBBNoServerForRecording</p>";
                break;
            }
            if(bbb_session_running($_GET['meeting_id']) == false)
            {
                $mod_pw = Database::get()->querySingle("SELECT * FROM bbb_session WHERE meeting_id=?s",$_GET['meeting_id'])->mod_pw;
                create_meeting($_GET['title'],$_GET['meeting_id'],$mod_pw,$_GET['att_pw'],$_GET['record']);
            }
            if(isset($_GET['mod_pw']))
            {
                header('Location: ' . bbb_join_moderator($_GET['meeting_id'],$_GET['mod_pw'],$_GET['att_pw'],$_SESSION['surname'],$_SESSION['givenname']));
            }else
            {
                # Get session capacity
                $c = Database::get()->querySingle("SELECT sessionUsers FROM bbb_session where meeting_id=?s",$_GET['meeting_id']);
                $sess = Database::get()->querySingle("SELECT * FROM bbb_session WHERE meeting_id=?s",$_GET['meeting_id']);
                $serv = Database::get()->querySingle("SELECT * FROM bbb_servers WHERE id=?d", $sess->running_at);

                if( ($c > 0) && ($c < get_meeting_users($serv->server_key,$serv->api_url,$_GET['meeting_id'],$sess->mod_pw)))
                {
                    $tool_content .= "<p class='noteit'><b>$langNote</b>:<br />$langBBBMaxUsersJoinError</p>";
                    break;
                }
                else
                header('Location: ' . bbb_join_user($_GET['meeting_id'],$_GET['att_pw'],$_SESSION['surname'],$_SESSION['givenname']));
            }
            break;
        case 'import_video':
            publish_video_recordings($course_code,$_GET['id']);
            break;
    }
    bbb_session_details();
    if($_GET['choice']=='import_video')
    {
        $tool_content .= "<div class='success'>$langBBBImportRecordingsΟΚ</div>";
    }
} elseif(isset($_POST['new_bbb_session'])) {  
    add_bbb_session($course_id,$_POST['title'], $_POST['desc'], $_POST['start_session'], $_POST['type'] ,$_POST['status'],(isset($_POST['notifyUsers']) ? '1' : '0'),$_POST['minutes_before'],$_POST['external_users'], $_POST['record'], $_POST['sessionUsers']);
}
else {    
    bbb_session_details();
}


/**
 * @brief create form for new session scheduling
 * @global type $tool_content
 * @global type $langAdd
 * @global type $course_code
 * @global type $langNewBBBSessionInfo
 * @global type $langNewBBBSessionDesc
 * @global type $langNewBBBSessionStart
 * @global type $langNewBBBSessionType
 * @global type $langNewBBBSessionPublic
 * @global type $langNewBBBSessionPrivate
 * @global type $langNewBBBSessionActive
 * @global type $langNewBBBSessionInActive
 * @global type $langNewBBBSessionStatus
 * @global type $langBBBSessionAvailable
 * @global type $langBBBMinutesBefore 
 * @global type $start_session
 * @global type $langBack
 * @global type $langBBBNotifyUsers
 * @global type $langBBBNotifyExternalUsers 
 */
function new_bbb_session() {
    global $tool_content, $langAdd, $course_code;
    global $langNewBBBSessionInfo, $langNewBBBSessionDesc, $langNewBBBSessionStart, $langNewBBBSessionType;
    global $langNewBBBSessionPublic, $langNewBBBSessionPrivate, $langNewBBBSessionActive, $langNewBBBSessionInActive;
    global $langNewBBBSessionStatus, $langBBBSessionAvailable, $langBBBMinutesBefore;
    global $start_session;
    global $langBack, $langTitle;
    global $langBBBNotifyUsers,$langBBBNotifyExternalUsers;    
    global $langAllUsers, $langParticipants, $langBBBRecord, $langBBBRecordTrue, $langBBBRecordFalse,$langBBBSessionMaxUsers;
    global $langBBBSessionSuggestedUsers,$langBBBSessionSuggestedUsers2;
    global $langΒΒΒAlertTitle,$langΒΒΒAlertMaxParticipants;
   
    $textarea = rich_text_editor('desc', 4, 20, '');
    $start_session = strftime('%Y-%m-%d', strtotime('now'));
    $tool_content .= "
        <form name='sessionForm' action='$_SERVER[SCRIPT_NAME]?course=$course_code' method='post' >
        <fieldset>
        <legend>$langNewBBBSessionInfo</legend>
        <table class='tbl' width='100%'>
        <tr>
          <th>$langTitle:</th>
          <td><input type='text' name='title' size='55' /></td>
        </tr>
        <tr>
          <th>$langNewBBBSessionDesc:</th>
          <th colspan='2'>$textarea</th>
        </tr>
        <tr>
          <th>$langNewBBBSessionStart:</th>
          <td><input type='text' name='start_session' value='$start_session'></td>
        </tr>
        <tr>
        <th valign='top'>$langParticipants:</th>
        <td>
    	<select name='groups[]' multiple='multiple' class='auth_input' id='select-groups'>";
            //select all users from this course except yourself
            $sql = "SELECT `group`.`id`,`group`.`name` FROM `group` RIGHT JOIN course ON group.course_id=course.id WHERE course.code=?s ORDER BY UPPER(NAME)";
            $res = Database::get()->queryArray($sql,$course_code);
            $tool_content .= "<option value=0>" . $langAllUsers . "</option>";
                    foreach ($res as $r) {
                        if(isset($r->id)) {$tool_content .= "<option value=" . $r->id . ">" . q($r->name) . "</option>";}
                    }
        $tool_content .= "</select></td>";
        $tool_content .="</th>
        </tr>
        <tr>
        <th valign='top'>$langBBBRecord:</th>
            <td><input type='radio' id='user_button' name='record' value='true' />
            <label for='user_button'>$langBBBRecordTrue</label><br />
            <input type='radio' id='group_button' name='record' checked='true' value='false' />
            <label for='group_button'>$langBBBRecordFalse</label></td>
        </th>
        </tr>         
        <tr>
        <th valign='top'>$langNewBBBSessionType:</th>
            <td><input type='radio' id='user_button' name='type' checked='true' value='0' />
            <label for='user_button'>$langNewBBBSessionPublic</label><br />
            <input type='radio' id='group_button' name='type' value='1' />
            <label for='group_button'>$langNewBBBSessionPrivate</label></td>
        </th>
        </tr>
        <tr>
        <th valign='top'>$langNewBBBSessionStatus:</th>
            <td><input type='radio' id='user_button' name='status' checked='true' value='1' />
            <label for='user_button'>$langNewBBBSessionActive</label><br />
            <input type='radio' id='group_button' name='status' value='0' />
            <label for='group_button'>$langNewBBBSessionInActive</label></td>
        </th>
        </tr>
        <tr>
            <th>$langBBBSessionAvailable:</th>
                <th colspan='2'>
                    <select name='minutes_before'>
                        <option value='15'' selected='selected'>15</option>
                        <option value='30'>30</option>
                        <option value='10'>10</option>
                    </select> $langBBBMinutesBefore
            </th>
        </tr>
        <tr>";
            $c = Database::get()->querySingle("SELECT COUNT(*) count FROM course_user WHERE course_id=(SELECT id FROM course WHERE code=?s)",$course_code)->count;
            if ($c>20) {$c = $c/2;} // If more than 20 course users, we suggest 50% of them

        $tool_content.="<th>$langBBBSessionMaxUsers:</th>
            <td><input type='text' name='sessionUsers' size='5' value='$c' > $langBBBSessionSuggestedUsers:";
        
        $tool_content .=" <strong>$c</strong> ($langBBBSessionSuggestedUsers2)</td>
        </tr>                    
        <tr>
            <th>
                $langBBBNotifyExternalUsers
            </th>
            <th colspan='2'>
                <input id='tags_1' name='external_users' type='text' class='tags' value='' />
            </th>
        </tr>
        <tr>
        <th colspan='2' valign='top'>
                <input type='checkbox' name='notifyUsers' value='1'>$langBBBNotifyUsers
            </th>
        </tr>        
        <tr>
          <th>&nbsp;</th>
          <th colspan='2' class='right'><input type='submit' name='new_bbb_session' value='$langAdd' /></th>
        </tr>
        </table>
        </fieldset>
        </form>";
        $tool_content .='<script language="javaScript" type="text/javascript">
        //<![CDATA[
            var chkValidator  = new Validator("sessionForm");
            chkValidator.addValidation("title","req","'.$langΒΒΒAlertTitle.'");
            chkValidator.addValidation("sessionUsers","req","'.$langΒΒΒAlertMaxParticipants.'");
            chkValidator.addValidation("sessionUsers","numeric","'.$langΒΒΒAlertMaxParticipants.'");
        //]]></script>';
    $tool_content .= "<p align='right'><a href='$_SERVER[SCRIPT_NAME]?course=$course_code'>$langBack</a></p>";
}

/**
 * @brief insert scheduled session data into database
 * @global type $tool_content
 * @global type $langBBBAddSuccessful
 * @global type $langBBBScheduledSession
 * @global type $langBBBScheduleSessionInfo
 * @global type $langBBBScheduleSessionInfo2
 * @param type $course_id
 * @param type $title
 * @param type $desc
 * @param type $start_session
 * @param type $type
 * @param type $status
 * @param type $notifyUsers
 * @param type $minutes_before
 * @param type $external_users
 */
function add_bbb_session($course_id,$title,$desc,$start_session,$type,$status,$notifyUsers,$minutes_before,$external_users,$record,$sessionUsers)
{
    global $tool_content, $langBBBAddSuccessful;
    global $langBBBScheduledSession;
    global $langBBBScheduleSessionInfo , $langBBBScheduleSessionInfo2, $course_code, $langBack;

    // Groups of participants per session
    $r_group = "";
    if (isset($_POST['groups'])) {
        foreach ($_POST['groups'] as $group) { 
            $r_group .= $group .',';
        }
    }            
    $r_group = rtrim($r_group,',');
    
    // Enable recording or not
    switch($record)
    {
        case 0:
            $record="false";
            break;
        case 1:
            $record="true";
            break;
    }
    
    Database::get()->querySingle("INSERT INTO bbb_session (course_id,title,description,start_date,public,active,running_at,meeting_id,mod_pw,att_pw,unlock_interval,external_users,participants,record,sessionUsers)"
        . " VALUES (?d,?s,?s,?t,?s,?s,'1',?s,?s,?s,?d,?s,?s,?s,?d)", $course_id, $title, $desc, $start_session, $type, $status, generateRandomString(), generateRandomString(), generateRandomString(), $minutes_before, $external_users,$r_group,$record,$sessionUsers);
    
    $tool_content .= "<div class='success'>$langBBBAddSuccessful</div>";
    $tool_content .= "<p><a href='$_SERVER[SCRIPT_NAME]?course=$course_code'>$langBack</a></p>";

    // if we have to notify users for new session
    if($notifyUsers=="1")
    {
        $recipients = array();

        $result = Database::get()->queryArray("SELECT user_id, email FROM course_user, user
                WHERE course_user.course_id = $course_id AND course_user.user_id = user.id");

        foreach($result as $row) {
            $emailTo = $row->email;
            $user_id = $row->user_id;
            // we check if email notification are enabled for each user
            if (get_user_email_notification($user_id)) {
                //and add user to recipients
                array_push($recipients, $emailTo);
            }
        }
        if(count($recipients)>0)
        {
            $emailsubject = $langBBBScheduledSession;
            $emailbody = $langBBBScheduleSessionInfo . " \"" . q($title) . "\" " . $langBBBScheduleSessionInfo2 . " " . q($start_session);
            $emailcontent = $langBBBScheduleSessionInfo . " \"" . q($title) . "\" " . $langBBBScheduleSessionInfo2 . " " . q($start_session);
            
            //Notify course users for new bbb session
            send_mail_multipart('', '', '', $recipients, $emailsubject, $emailbody, $emailcontent, 'UTF-8');
        }
    }
    
    $orderMax = Database::get()->querySingle("SELECT MAX(`order`) AS maxorder FROM announcement
                                                   WHERE course_id = ?d", $course_id)->maxorder;
 
    $order = $orderMax + 1;
            
    Database::get()->querySingle("INSERT INTO announcement (content,title,`date`,course_id,`order`,visible) VALUES ('".$langBBBScheduleSessionInfo . " \"" . $title . "\" " . $langBBBScheduleSessionInfo2 . " " . $start_session."',
                                             '$langBBBScheduledSession',NOW(),
                                             '$course_id','$order','1')");
}

/**
 * @brief update scheduled session data into database
 * @global type $tool_content
 * @global type $langBBBAddSuccessful
 * @global type $course_id
 * @global type $langBBBScheduleSessionInfo
 * @global type $langBBBScheduledSession
 * @global type $langBBBScheduleSessionInfo2
 * @param type $session_id
 * @param type $title
 * @param type $desc
 * @param type $start_session
 * @param type $type
 * @param type $status
 * @param type $notifyUsers
 * @param type $minutes_before
 * @param type $external_users
 */
function update_bbb_session($session_id,$title,$desc,$start_session,$type,$status,$notifyUsers,$minutes_before,$external_users,$record,$sessionUsers)
{
    global $tool_content, $langBBBAddSuccessful, $course_id;
    global $langBBBScheduleSessionInfo , $langBBBScheduledSession, $langBBBScheduleSessionInfo2 ;

    // Groups of participants per session
    $r_group = "";
    foreach ($_POST['groups'] as $group)
    { $r_group .= $group .','; }
    
    $r_group = rtrim($r_group,',');

    // Enable recording or not
    switch($record)
    {
        case 0:
            $record="false";
            break;
        case 1:
            $record="true";
            break;
    }
    Database::get()->querySingle("UPDATE bbb_session SET title=?s,description=?s,"
            . "start_date=?t,public=?s,active=?s,unlock_interval=?d,external_users=?s,participants=?s,record=?s,sessionUsers=?d WHERE id=?d",$title, $desc, $start_session, $type, $status, $minutes_before, $external_users, $r_group, $record, $sessionUsers, $session_id);
    
    $tool_content .= "<p class='success'>$langBBBAddSuccessful</p>";

    // if we have to notify users for new session
    if($notifyUsers=="1")
    {
        $recipients = array();

        $result = Database::get()->queryArray("SELECT user_id, email FROM course_user, user
                WHERE course_user.course_id = $course_id AND course_user.user_id = user.id");

        foreach($result as $row) {
            $emailTo = $row->email;
            $user_id = $row->user_id;
            // we check if email notification are enabled for each user
            if (get_user_email_notification($user_id)) {
                //and add user to recipients
                array_push($recipients, $emailTo);
            }
        }
        if(count($recipients)>0)
        {
            $emailsubject = $langBBBScheduledSession;
            $emailbody = $langBBBScheduleSessionInfo . " \"" . q($title) . "\" " . $langBBBScheduleSessionInfo2 . " " . q($start_session);
            $emailcontent = $langBBBScheduleSessionInfo . " \"" .q($title) . "\" " . $langBBBScheduleSessionInfo2 . " " . q($start_session);
            
            //Notify course users for new bbb session
            send_mail_multipart('', '', '', $recipients, $emailsubject, $emailbody, $emailcontent, 'UTF-8');
        }
    }
    
    $orderMax = Database::get()->querySingle("SELECT MAX(`order`) AS maxorder FROM announcement
                                                   WHERE course_id = ?d", $course_id)->maxorder;
    $order = $orderMax + 1;
            
    Database::get()->querySingle("INSERT INTO announcement (content,title,`date`,course_id,`order`,visible) VALUES ('".$langBBBScheduleSessionInfo . " \"" . $title . "\" " . $langBBBScheduleSessionInfo2 . " " . $start_session."',
                                             '$langBBBScheduledSession',NOW(),
                                             '$course_id','$order','1')");

}

/**
 * @brief form to edit session data
 * @global type $tool_content
 * @global type $m
 * @global type $langAdd
 * @global type $course_code
 * @global type $langNewBBBSessionInfo
 * @global type $langNewBBBSessionDesc
 * @global type $langNewBBBSessionStart
 * @global type $langNewBBBSessionType
 * @global type $langNewBBBSessionPublic
 * @global type $langNewBBBSessionPrivate
 * @global type $langNewBBBSessionStatus
 * @global type $langNewBBBSessionActive
 * @global type $langNewBBBSessionInActive
 * @global type $langBBBSessionAvailable
 * @global type $langBBBMinutesBefore 
 * @global type $start_session
 * @global type $langBack
 * @global type $langBBBNotifyUsers
 * @global type $langBBBNotifyExternalUsers
 * @param type $session_id
 */
function edit_bbb_session($session_id) {
    global $tool_content, $langAdd, $course_code;
    global $langNewBBBSessionInfo, $langNewBBBSessionDesc, $langNewBBBSessionStart;
    global $langNewBBBSessionType, $langNewBBBSessionPublic, $langNewBBBSessionPrivate;
    global $langNewBBBSessionStatus, $langNewBBBSessionActive, $langNewBBBSessionInActive,$langBBBSessionAvailable,$langBBBMinutesBefore;    
    global $start_session;
    global $langBack, $langTitle;
    global $langBBBNotifyUsers,$langBBBNotifyExternalUsers;
    global $langAllUsers,$langParticipants,$langBBBRecord,$langBBBRecordTrue,$langBBBRecordFalse,$langBBBSessionMaxUsers;
    global $langBBBSessionSuggestedUsers,$langBBBSessionSuggestedUsers2;
    global $langΒΒΒAlertTitle, $langΒΒΒAlertMaxParticipants;

    
    $row = Database::get()->querySingle("SELECT * FROM bbb_session WHERE id = ?d ", $session_id);
    
    $type = ($row->public == 1 ? 1 : 0);
    $status = ($row->active == 1 ? 1 : 0);
    $record = ($row->record == "true" ? 1 : 0);
    #print_r($row);
    $r_group = explode(",",$row->participants);
    
    $textarea = rich_text_editor('desc', 4, 20, $row->description);

    $tool_content .= "
                    <form name='sessionForm' action='$_SERVER[SCRIPT_NAME]?id=$session_id' method='post'>
                    <fieldset>
                    <legend>$langNewBBBSessionInfo</legend>
                    <table class='tbl' width='100%'>
                    <tr>
                      <th>$langTitle:</th>`
                      <td><input type='text' name='title' size='55' value='".q($row->title)."'></td>
                    </tr>
                    <tr>
                      <th>$langNewBBBSessionDesc:</th>
                      <td>$textarea</td>
                    </tr>
                    <tr>
                      <th>$langNewBBBSessionStart:</th>
                      <td><input type='text' name='start_session' value = ".q($row->start_date)."></td>
                    </tr>
                    <tr>
                    <th valign='top'>$langParticipants:</th>
                    <td>
                    <select name='groups[]' multiple='multiple' class='auth_input' id='select-groups'>";
                    //select all users from this course except yourself
                    $sql = "SELECT `group`.`id`,`group`.`name` FROM `group` RIGHT JOIN course ON group.course_id=course.id WHERE course.code=?s ORDER BY UPPER(NAME)";
                    $res = Database::get()->queryArray($sql,$course_code);
                    
                    $tool_content .= "<option value=0 ";
                    if(in_array(0,$r_group))
                    {
                        $tool_content.="selected ";
                    }
                    $tool_content .=">" . $langAllUsers . "</option>";
                    foreach ($res as $r) {
                        if($r->id){
                                $tool_content .= "<option "; 
                                if(in_array($r->id,$r_group))
                                {
                                    $tool_content.="selected ";
                                }
                                $tool_content.="value=" . $r->id . ">" . q($r->name) . "</option>";
                            }
                    }
                    $tool_content .= "</select></td>";
                    $tool_content .="</th>
                    </tr>	
                    <tr>
                    <th valign='top'>$langBBBRecord:</th>
                    <td><input type='radio' id='user_button' name='record' value='1' "; 
                    if ($record==1) {
                        $tool_content .= "checked";
                    }
                    $tool_content .= " /><label for='user_button'>$langBBBRecordTrue</label><br />
                    <input type='radio' id='group_button' name='record' value='0' ";
                    if ($record==0) {
                        $tool_content .= "checked";
                    }
                    $tool_content .=" /><label for='group_button'>$langBBBRecordFalse</label></td>
                    </td>
                    </tr>
                    <tr>
                    <th valign='top'>$langNewBBBSessionType:</th>
                    <td><input type='radio' id='user_button' name='type' value='1' "; 
                    if ($type==1) {
                        $tool_content .= "checked";
                    }
                    $tool_content .= " /><label for='user_button'>$langNewBBBSessionPublic</label><br />
                    <input type='radio' id='group_button' name='type' value='0' ";
                    if ($type==0) {
                        $tool_content .= "checked";
                    }
                    $tool_content .=" /><label for='group_button'>$langNewBBBSessionPrivate</label></td>
                    </td>
                    </tr>
                    <tr>
                    <th valign='top'>$langNewBBBSessionStatus:</th>
                        <td><input type='radio' id='user_button' name='status' value='1' ";
                    if ($status==1) {
                        $tool_content .= "checked";
                    }                        
                    $tool_content .=" /><label for='user_button'>$langNewBBBSessionActive</label><br />
                    <input type='radio' id='group_button' name='status' value='0' ";
                    if ($status==0) {
                        $tool_content .= "checked ";
                    }
                     $tool_content .= " /><label for='group_button'>$langNewBBBSessionInActive</label></td>
                    </td>
                    </tr>
                    <tr>
                    <th>$langBBBSessionAvailable:</th>
                    <td>
                      <select name='minutes_before'>
                          <option value='15''"; if($row->unlock_interval=='15') { $tool_content .="selected='selected'"; }
                          $tool_content .=">15</option>
                          <option value='30'"; if($row->unlock_interval=='30') { $tool_content .="selected='selected'"; }
                          $tool_content .=">30</option>
                          <option value='10'"; if($row->unlock_interval=='10') { $tool_content .="selected='selected'"; }
                          $tool_content .=">10</option>
                      </select> $langBBBMinutesBefore
                      </td>
                    </tr>                    
                    <tr>
                      <th>$langBBBSessionMaxUsers:</th>
                      <td><input type='text' name='sessionUsers' size='5' value=".$row->sessionUsers."> $langBBBSessionSuggestedUsers:";
        $c = Database::get()->querySingle("SELECT COUNT(*) count FROM course_user WHERE course_id=(SELECT id FROM course WHERE code=?s)",$course_code)->count;
        if ($c>20) {$c = $c/2;} // If more than 20 course users, we suggest 50% of them
        $tool_content .=" <strong>$c</strong> ($langBBBSessionSuggestedUsers2)</td></td>
                    </tr>                    
                    <tr>
                        <th>
                            $langBBBNotifyExternalUsers
                        </th>
                        <td>
                            <input id='tags_1' name='external_users' type='text' class='tags' value='".trim($row->external_users)."' />
                        </td>
                    </tr>
                    <tr>
                    <th colspan='2' valign='top'>
                        <input type='checkbox' name='notifyUsers' value='1'>$langBBBNotifyUsers
                    </th>
                    </tr>
                    <tr>
                      <th>&nbsp;</th>
                      <td class='right'><input type='submit' name='update_bbb_session' value='$langAdd' /></td>
                    </tr>
                    </table>
                    </fieldset>
                    </form>";
                $tool_content .='<script language="javaScript" type="text/javascript">
                    //<![CDATA[
                    var chkValidator  = new Validator("sessionForm");
                    chkValidator.addValidation("title","req","'.$langΒΒΒAlertTitle.'");
                    chkValidator.addValidation("sessionUsers","req","'.$langΒΒΒAlertMaxParticipants.'");
                    chkValidator.addValidation("sessionUsers","numeric","'.$langΒΒΒAlertMaxParticipants.'");
                    //]]></script>';
                    $tool_content .= "<p align='right'><a href='$_SERVER[SCRIPT_NAME]?course=$course_code'>$langBack</a></p>";
        }

/**
 * @brief Print a box with the details of a bbb session
 * @global type $course_id
 * @global type $tool_content 
 * @global type $is_editor
 * @global type $langActions
 * @global type $langNewBBBSessionStart
 * @global type $langNewBBBSessionType
 * @global type $langConfirmDelete
 * @global type $langNewBBBSessionPublic
 * @global type $langNewBBBSessionPrivate
 * @global type $langBBBSessionJoin
 * @global type $langNewBBBSessionDesc
 * @global type $course_code
 * @global type $themeimg
 * @global type $langNote
 * @global type $langBBBNoteEnableJoin
 * @global type $langTitle
 * @global type $langActivate
 * @global type $langDeactivate
 * @global type $langModify
 * @global type $langDelete
 */        
function bbb_session_details() {
    global $course_id, $tool_content, $is_editor, $langActions, $langNewBBBSessionStart, $langNewBBBSessionType;
    global $langConfirmDelete, $langNewBBBSessionPublic, $langNewBBBSessionPrivate, $langBBBSessionJoin, $langNewBBBSessionDesc;
    global $course_code;
    global $themeimg;
    global $langNote, $langBBBNoteEnableJoin, $langTitle,$langActivate, $langDeactivate, $langModify, $langDelete, $langNoBBBSesssions;
    global $langBBBNotServerAvailableStudent, $langBBBNotServerAvailableTeacher;
    global $langBBBImportRecordings;
        
    $myGroups = Database::get()->queryArray("SELECT group_id FROM group_members WHERE user_id=?d", $_SESSION['uid']);

    $result = Database::get()->queryArray("SELECT * FROM bbb_session WHERE course_id = ?s ORDER BY id DESC", $course_id);

    if (($result)) {
        if (!$is_editor) {
            $tool_content .= "<p class='noteit'><b>$langNote</b>:<br />$langBBBNoteEnableJoin</p>";
        }    
        $tool_content .= "<table class='tbl_alt' width='100%'>
                          <tr>
                              <th width = '10%' class='center'>$langTitle</th>
                              <th class = 'center'>$langNewBBBSessionDesc</th>
                              <th class = 'center'>$langNewBBBSessionStart</th>
                              <th class = 'center'>$langNewBBBSessionType</th>
                              <th width = '15%' class='center'>$langActions</th>
                          </tr>";
        $k = 0;

        foreach ($result as $row) {    
                // Get participants groups
                $r_group = explode(",",$row->participants);
                
                $id = $row->id;
                $title = $row->title;
                $start_date = $row->start_date;
                $row->public == '1' ? $type = $langNewBBBSessionPublic: $type = $langNewBBBSessionPrivate;
                $meeting_id = $row->meeting_id;
                $att_pw = $row->att_pw;
                $mod_pw = $row->mod_pw;
                $record = $row->record;
                (isset($row->description)? $desc = $row->description : $desc="");
                $tool_content .= "<tr>";

                if ($is_editor) {
                    // If there no available bbb servers, disable join link. Otherwise, enable    
                    if(get_total_bbb_servers()=='0')
                    {
                        $tool_content .= "
                        <td>".q($title)."</td>";
                    } else {
                        $tool_content .= "
                        <td><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;choice=do_join&amp;meeting_id=$meeting_id&amp;title=".urlencode($title)."&amp;att_pw=".urlencode($att_pw)."&amp;mod_pw=".urlencode($mod_pw)."&amp;record=$record' target='_blank'>".q($title)."</a></td>";
                    }
                    $tool_content.="<td>".$desc."</td>
                    <td class='center'>".q($start_date)."</td>
                    <td class='center'>$type</td>
                    <td class='center'>
                    ".icon('edit', $langModify, "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id&amp;choice=edit")."                        
                     <a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$row->id&amp;choice=do_delete' onClick='return confirmation(\"" . $langConfirmDelete . "\");'>
                    <img src='$themeimg/delete.png' alt='$langDelete' title='$langDelete' /></a>
                    <a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$row->id&amp;choice=import_video' >
                    <img src='$themeimg/video.png' alt='$langBBBImportRecordings' title='$langBBBImportRecordings' /></a>";
                    if ($row->active=='1') {
                        $deactivate_temp = q($langDeactivate);
                        $activate_temp = q($langActivate);
                        $tool_content .= "<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;choice=do_disable&amp;id=$row->id'><img src='$themeimg/visible.png' title='$deactivate_temp' /></a>";
                    } else {
                        $activate_temp = q($langActivate);
                        $tool_content .= "<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;choice=do_enable&amp;id=$row->id'><img src='$themeimg/invisible.png' title='$activate_temp' /></a>";
                    }
                } else {
                    //Allow access to session only if user is in participant group or session is scheduled for everyone
                    $access='false';
                    foreach($myGroups as $mg)
                    {
                        if(in_array($mg,$r_group)) { 
                            $access='true';                            
                        }
                    }
                    if(in_array("0",$r_group) || $access == 'true')
                    {
                        $tool_content .= "<td align='center'>";
                        // Join url will be active only X minutes before scheduled time and if session is visible for users
                        if ($row->active=='1' && date_diff_in_minutes($start_date,date('Y-m-d H:i:s'))<= $row->unlock_interval && get_total_bbb_servers()<>'0' )
                        {   
                            $tool_content .= "<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;choice=do_join&amp;title=".urlencode($title)."&amp;meeting_id=$meeting_id&amp;att_pw=".urlencode($att_pw)."&amp;record=$record' target='_blank'>".q($title)."</a>";
                        } else {
                            $tool_content .= q($title);
                        }
                        $tool_content .="<td>".$desc."</td>
                            <td align='center'>".q($start_date)."</td>
                            <td align='center'>$type</td>
                            <td class='center'>";
                        // Join url will be active only X minutes before scheduled time and if session is visible for users
                        if ($row->active=='1' && date_diff_in_minutes($start_date,date('Y-m-d H:i:s'))<= $row->unlock_interval && get_total_bbb_servers()<>'0' ) {
                            $tool_content .= "<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;choice=do_join&amp;title=".urlencode($title)."&amp;meeting_id=$meeting_id&amp;att_pw=".urlencode($att_pw)."&amp;record=$record' target='_blank'>$langBBBSessionJoin</a></td>";
                        } else {
                            $tool_content .= "-</td>";
                        }
                    }
                }
                $tool_content .= "</tr>";
            }        
        $tool_content .= "</table>";
        if(get_total_bbb_servers()=='0')
        {
            if($is_editor) {$tool_content .= "<p class='alert1'><b>$langNote</b>:<br />$langBBBNotServerAvailableTeacher</p>";}
            else {$tool_content .= "<p class='alert1'><b>$langNote</b>:<br />$langBBBNotServerAvailableStudent</p>";}
        }
        
    } else {
        $tool_content .= "<div class='alert1'>$langNoBBBSesssions</div>";
    }
}

/**
 * @brief disable bbb session
 * @global type $langBBBUpdateSuccessful
 * @global type $tool_content
 * @param type $id
 * @return type
 */
function disable_bbb_session($id)
{
    global $langBBBUpdateSuccessful, $tool_content;
    
    Database::get()->querySingle("UPDATE bbb_session set active='0' WHERE id=?d",$id);
    $tool_content .= "<div class='success'>$langBBBUpdateSuccessful</div>";
    
    return;    
}

/**
 * @brief enable bbb session
 * @global type $langBBBUpdateSuccessful
 * @global type $tool_content
 * @param type $id
 * @return type
 */
function enable_bbb_session($id)
{
    global $langBBBUpdateSuccessful, $tool_content;
    
    Database::get()->querySingle("UPDATE bbb_session SET active='1' WHERE id=?d",$id);
    $tool_content .= "<div class='success'>$langBBBUpdateSuccessful</div>";
    
    return;
}



/**
 * @brief delete bbb sessions
*/
function delete_bbb_session($id)
{
    global $langBBBDeleteSuccessful, $tool_content;
    
    Database::get()->querySingle("DELETE FROM bbb_session WHERE id=?d",$id);
    $tool_content .= "<div class='success'>$langBBBDeleteSuccessful</div>";
    
    return;
}


function create_meeting($title,$meeting_id,$mod_pw,$att_pw,$record)
{
    global $course_id;

    $run_to = -1;
    $min_users  = 10000000;
    
    //Get all course participants
    $users_to_join = Database::get()->querySingle("SELECT COUNT(*) AS count FROM course_user, user
                                WHERE course_user.course_id = ?d AND course_user.user_id = user.id", $course_id)->count;
    //Algorithm to select BBB server GOES HERE ...
    $query = Database::get()->queryArray("SELECT * FROM bbb_servers WHERE enabled='true' AND enable_recordings=?s ORDER BY weight ASC",$record);

    if ($query) {        
        foreach ($query as $row) {
            $max_rooms = $row->max_rooms;
            $max_users = $row->max_users;
            // GET connected Participants
            $connected_users = get_connected_users($row->server_key,$row->api_url);
            $active_rooms = get_active_rooms($row->server_key,$row->api_url);
            
            if($connected_users<$min_users)
            {
                $run_to=$row->id;
                $min_users = $connected_users;
            }
            
            //cases
            // max_users = 0 && max_rooms = 0 - UNLIMITED
            // active_rooms < max_rooms && active_users < max_users
            // active_rooms < max_rooms && max_users = 0 (UNLIMITED)
            // active_users < max_users && max_rooms = 0 (UNLIMITED)
            if(($max_rooms == 0 && $max_users == 0) || (($max_users > ($users_to_join + $connected_users)) && $active_rooms < $max_rooms) || ($active_rooms < $max_rooms && $max_users == 0) || (($max_users > ($users_to_join + $connected_users)) && $max_rooms == 0)) // YOU FOUND THE SERVER
            {
                $run_to = $row->id;
                Database::get()->querySingle("UPDATE bbb_session SET running_at=?s WHERE meeting_id=?s",$row->id, $meeting_id);
                break;
            }
        }
    }

    if($run_to == -1)
    {
        //WE SHOULD TAKE ACTION IF NO SERVER AVAILABLE DUE TO CAPACITY PROBLEMS
        // If no server available we select server with min connected users
        
        $temp_conn = 10000000;
        
        $query = Database::get()->queryArray("SELECT * FROM bbb_servers WHERE enabled='true' ORDER BY weight ASC",$record);

        if ($query) {        
            foreach ($query as $row) {

                // GET connected Participants
                $connected_users = get_connected_users($row->server_key,$row->api_url);

                if($connected_users<$temp_conn)
                {
                    $run_to=$row->id;
                    $temp_conn = $connected_users;
                }
            }
        }
        Database::get()->querySingle("UPDATE bbb_session SET running_at=?d WHERE meeting_id=?d",$run_to,$meeting_id);
    }
    
    //we find the bbb server that will serv the session
    $res = Database::get()->querySingle("SELECT *
                        FROM bbb_servers
                        WHERE id=?d", $run_to);

    $salt = $res->server_key;
    $bbb_url = $res->api_url;

    $bbb = new BigBlueButton($salt,$bbb_url);

    $creationParams = array(
        'meetingId' => $meeting_id, // REQUIRED
        'meetingName' => $title, // REQUIRED
        'attendeePw' => $att_pw, // Match this value in getJoinMeetingURL() to join as attendee.
        'moderatorPw' => $mod_pw, // Match this value in getJoinMeetingURL() to join as moderator.
        'welcomeMsg' => '', // ''= use default. Change to customize.
        'dialNumber' => '', // The main number to call into. Optional.
        'voiceBridge' => '', // PIN to join voice. Optional.
        'webVoice' => '', // Alphanumeric to join voice. Optional.
        'logoutUrl' => '', // Default in bigbluebutton.properties. Optional.
        'maxParticipants' => '-1', // Optional. -1 = unlimitted. Not supported in BBB. [number]
        'record' => $record, // New. 'true' will tell BBB to record the meeting.
        'duration' => '0', // Default = 0 which means no set duration in minutes. [number]
        //'meta_category' => '', // Use to pass additional info to BBB server. See API docs.
    );

    // Create the meeting and get back a response:
    $itsAllGood = true;
    try {$result = $bbb->createMeetingWithXmlResponseArray($creationParams);}
    catch (Exception $e) {
        echo 'Caught exception: ', $e->getMessage(), "\n";
        $itsAllGood = false;
    }

    if ($itsAllGood == true) {
        // If it's all good, then we've interfaced with our BBB php api OK:
        if ($result == null) {
            // If we get a null response, then we're not getting any XML back from BBB.
            // echo "Failed to get any response. Maybe we can't contact the BBB server.";
        }
        else {
            // We got an XML response, so let's see what it says:
            //print_r($result);
            if ($result['returncode'] == 'SUCCESS') {
                // Then do stuff ...
                //echo "<p>Meeting succesfullly created.</p>";
            }
            else {
                //echo "<p>Meeting creation failed.</p>";
            }
        }
    }
}

//create join as moderator link
function bbb_join_moderator($meeting_id,$mod_pw,$att_pw,$surname,$name){
    
    $res = Database::get()->querySingle("SELECT running_at FROM bbb_session WHERE meeting_id = ?s",$meeting_id);
    if ($res) {
        $running_server = $res->running_at;
    }

    $res = Database::get()->querySingle("SELECT *
                        FROM bbb_servers
                        WHERE id=?s", $running_server);

    $salt = $res->server_key;
    $bbb_url = $res->api_url;

    // Instatiate the BBB class:
    $bbb = new BigBlueButton($salt,$bbb_url);

    $joinParams = array(
        'meetingId' => $meeting_id, // REQUIRED - We have to know which meeting to join.
        'username' => $surname . " " . $name,	// REQUIRED - The user display name that will show in the BBB meeting.
        'password' => $mod_pw,	// REQUIRED - Must match either attendee or moderator pass for meeting.
        'createTime' => '',	// OPTIONAL - string
        'userId' => '',	// OPTIONAL - string
        'webVoiceConf' => ''	// OPTIONAL - string
    );

    // Get the URL to join meeting:
    $itsAllGood = true;
    try {$result = $bbb->getJoinMeetingURL($joinParams);}
        catch (Exception $e) {
            // echo 'Caught exception: ', $e->getMessage(), "\n";
            $itsAllGood = false;
    }

    if ($itsAllGood == true) {
        //Output results to see what we're getting:
        //print_r($result);
    }

    return $result;
}

// create join as simple user link
function bbb_join_user($meeting_id,$att_pw,$surname,$name){
    $res = Database::get()->querySingle("SELECT running_at FROM bbb_session WHERE meeting_id = ?s",$meeting_id);
    if ($res) {
        $running_server = $res->running_at;
    }

    $res = Database::get()->querySingle("SELECT *
                        FROM bbb_servers
                        WHERE id=?d", $running_server);

    $salt = $res->server_key;
    $bbb_url = $res->api_url;
    
    // Instatiate the BBB class:
    $bbb = new BigBlueButton($salt,$bbb_url);

    $joinParams = array(
        'meetingId' => $meeting_id, // REQUIRED - We have to know which meeting to join.
        'username' => $surname . " " . $name,	// REQUIRED - The user display name that will show in the BBB meeting.
        'password' => $att_pw,	// REQUIRED - Must match either attendee or moderator pass for meeting.
        'createTime' => '',	// OPTIONAL - string
        'userId' => '',	// OPTIONAL - string
        'webVoiceConf' => ''	// OPTIONAL - string
    );

    // Get the URL to join meeting:
    $itsAllGood = true;
    try {$result = $bbb->getJoinMeetingURL($joinParams);}
        catch (Exception $e) {
            //echo 'Caught exception: ', $e->getMessage(), "\n";
            $itsAllGood = false;
    }

    if ($itsAllGood == true) {
        //Output results to see what we're getting:
        //print_r($result);
    }

    return $result;
}

// Generate random strings. Used to create meeting_id, attendance password and moderator password
function generateRandomString($length = 10) {
    return substr(str_shuffle(implode(array_merge(range(0,9), range('A', 'Z'), range('a', 'z')))), 0, $length);
}

function bbb_session_running($meeting_id)
{
    //echo "SELECT running_at FROM bbb_session WHERE meeting_id = '$meeting_id'";    
    $res = Database::get()->querySingle("SELECT running_at FROM bbb_session WHERE meeting_id = ?s",$meeting_id);

    if (! isset($res->running_at)) {
        return false;
    }
    $running_server = $res->running_at;    

    if(Database::get()->querySingle("SELECT count(*) as count
                                    FROM bbb_servers
                                    WHERE id=?d AND enabled='true'", $running_server)->count == 0)
    {
        //it means that the server is disabled so session must be recreated
        return false;
    }
    
    $res = Database::get()->querySingle("SELECT *
                                    FROM bbb_servers
                                    WHERE id=?d", $running_server);    
    $salt = $res->server_key;
    $bbb_url = $res->api_url;
    
    if(!isset($salt) || !isset($bbb_url)) { return false; }
    
    // Instatiate the BBB class:
    $bbb = new BigBlueButton($salt,$bbb_url);

    // Get the URL to join meeting:
    $itsAllGood = true;
    try {$result = $bbb->isMeetingRunningWithXmlResponseArray($meeting_id);}
    catch (Exception $e) {
        //echo 'Caught exception: ', $e->getMessage(), "\n";
        $itsAllGood = false;
        return $itsAllGood;
    }
    if((string) $result['running'] == 'false')
    {
        return false;
        
    }else return true;
}

//Function to calculate date diff in minutes in order to enable join link
function date_diff_in_minutes($start_date,$current_date)
{
    return round((strtotime($start_date) - strtotime($current_date)) /60);
}

//Get total connected users per server
function get_connected_users($salt,$bbb_url)
{
    // Instatiate the BBB class:
    $bbb = new BigBlueButton($salt,$bbb_url);

    $meetings = $bbb->getMeetingsWithXmlResponseArray();

    $sum = 0;
    foreach($meetings as $meeting){
            $mid = $meeting['meetingId'];
            $pass = $meeting['moderatorPw'];
            if($mid != null){
                    $info = $bbb->getMeetingInfoWithXmlResponseArray(array('meetingId' => $mid, 'password' => $pass));
                    $sum += $info['participantCount'];
            }
    }
    return $sum;

}

function get_active_rooms($salt,$bbb_url)
{
    $sum = 0;
    // Instatiate the BBB class:
    $bbb = new BigBlueButton($salt,$bbb_url);

    $meetings = $bbb->getMeetingsWithXmlResponseArray();

    foreach($meetings as $meeting){
        $mid = $meeting['meetingId'];
        $pass = $meeting['moderatorPw'];
        if($mid != null){
            $sum += 1;
        }
    }
    
    return $sum;
}

function get_total_bbb_servers()
{
    $total = 0;
    
    $total = Database::get()->querySingle("SELECT count(*) AS count FROM bbb_servers WHERE enabled='true'")->count;
    
    return $total;
}

function publish_video_recordings($course_id,$id)
{
    $sessions = Database::get()->queryArray("SELECT bbb_session.id,bbb_session.course_id as course_id,"
            . "bbb_session.title,bbb_session.description,bbb_session.start_date,"
            . "bbb_session.meeting_id,course.prof_names FROM bbb_session LEFT JOIN course ON bbb_session.course_id=course.id WHERE course.code=?s AND bbb_session.id=?d", $course_id,$id);

    $servers = Database::get()->queryArray("SELECT * FROM bbb_servers WHERE enabled='true' ORDER BY id DESC");

    if (($sessions) && ($servers)) {
        foreach ($servers as $server){
            $salt = $server->server_key;
            $bbb_url = $server->api_url;
            
            $bbb = new BigBlueButton($salt,$bbb_url);
            foreach ($sessions as $session) {    
                $recordingParams = array(
                    'meetingId' => $session->meeting_id,
                );
                $recs = file_get_contents($bbb->getRecordingsUrl($recordingParams));
                #print_r($recs);
                $xml = simplexml_load_string($recs);
                # If not set it means that there is no video recording.
                # Skip and search for next one
                if(isset($xml->recordings->recording->playback->format->url))
                {
                    $url = (string) $xml->recordings->recording->playback->format->url;

                    #Check if recording allready in videolinks and if not insert
                    $c = Database::get()->querySingle("SELECT count(*) AS cnt FROM videolink WHERE url = ?s",$url);
                    if($c->cnt == 0)
                    {
                        Database::get()->querySingle("INSERT INTO videolink (course_id,url,title,description,creator,publisher,date,visible,public)"
                        . " VALUES (?s,?s,?s,IFNULL(?s,'-'),?s,?s,?t,?d,?d)",$session->course_id,$url,$session->title,strip_tags($session->description),$session->prof_names,$session->prof_names,$session->start_date,1,1);
                    }
                }
            }
        }
    }
    return true;
}

function get_meeting_users($salt,$bbb_url,$meeting_id,$pw)
{
    // Instatiate the BBB class:
    $bbb = new BigBlueButton($salt,$bbb_url);

    $infoParams = array(
        'meetingId' => $meeting_id, // REQUIRED - We have to know which meeting.
        'password' => $pw,	// REQUIRED - Must match moderator pass for meeting.
    );

    // Now get meeting info and display it:
    $itsAllGood = true;
    try {$result = $bbb->getMeetingInfoWithXmlResponseArray($infoParams);}
    catch (Exception $e) {
        echo 'Caught exception: ', $e->getMessage(), "\n";
        $itsAllGood = false;
    }

    if ($itsAllGood == true) {
        // If it's all good, then we've interfaced with our BBB php api OK:
            if ($result == null) {
                // If we get a null response, then we're not getting any XML back from BBB.
                echo "Failed to get any response. Maybe we can't contact the BBB server.";
            }	
            else {
                // We got an XML response, so let's see what it says:
                //var_dump($result);
                if (!isset($result['messageKey'])) {
                    // Then do stuff ...
                    echo "<p>Meeting info was found on the server.</p>";
                }
                else {
                    echo "<p>Failed to get meeting info.</p>";
                }
            }
    }

    return (int)$result['participantCount'];
}

add_units_navigation(TRUE);
draw($tool_content, 2, null, $head_content);
