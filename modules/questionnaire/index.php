<?php

/* ========================================================================
 * Open eClass 3.0
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
 * ======================================================================== */

/* ===========================================================================
  index.php
  @last update: 17-4-2006 by Costas Tsibanis
  @authors list: Dionysios G. Synodinos <synodinos@gmail.com>
  ==============================================================================
  @Description: Main script for the questionnaire tool
  ==============================================================================
 */

$require_login = TRUE;
$require_current_course = TRUE;
$require_help = TRUE;
$helpTopic = 'Questionnaire';
require_once '../../include/baseTheme.php';

/* * ** The following is added for statistics purposes ** */
require_once 'include/action.php';
$action = new action();
$action->record(MODULE_ID_QUESTIONNAIRE);
/* * *********************************** */

$nameTools = $langQuestionnaire;

load_js('tools.js');
if ($is_editor) {
    if (isset($_GET['pid'])) {
        $pid = $_GET['pid'];
        $p = Database::get()->querySingle("SELECT pid FROM poll WHERE course_id = ?d AND pid = ?d ORDER BY pid", $course_id, $pid);
        if(!$p){
            redirect_to_home_page("modules/questionnaire/index.php?course=$course_code");
        }
        // activate / dectivate polls
        if (isset($_GET['visibility'])) {
            switch ($_GET['visibility']) {
                case 'activate':
                    Database::get()->query("UPDATE poll SET active = 1 WHERE course_id = ?d AND pid = ?d", $course_id, $pid);
                    Session::Messages($langPollActivated, 'success');
                    break;
                case 'deactivate':
                    Database::get()->query("UPDATE poll SET active = 0 WHERE course_id = ?d AND pid = ?d", $course_id, $pid);
                    Session::Messages($langPollDeactivated, 'success');
                    break;
            }
            redirect_to_home_page('modules/questionnaire/index.php?course='.$course_code);
        }

        // delete polls
        if (isset($_GET['delete']) and $_GET['delete'] == 'yes') {
            Database::get()->query("DELETE FROM poll_question_answer WHERE pqid IN
                        (SELECT pqid FROM poll_question WHERE pid = ?d)", $pid);
            Database::get()->query("DELETE FROM poll WHERE course_id = ?d AND pid = ?d", $course_id, $pid);
            Database::get()->query("DELETE FROM poll_question WHERE pid = ?d", $pid);
            Database::get()->query("DELETE FROM poll_answer_record WHERE pid = ?d", $pid);
            Session::Messages($langPollDeleted, 'success');
            redirect_to_home_page('modules/questionnaire/index.php?course='.$course_code);       
        // delete poll results
        } elseif (isset($_GET['delete_results']) && $_GET['delete_results'] == 'yes') {
            Database::get()->query("DELETE FROM poll_answer_record WHERE pid = ?d", $pid);
            Session::Messages($langPollResultsDeleted, 'success');
            redirect_to_home_page('modules/questionnaire/index.php?course='.$course_code);
        //clone poll
        } elseif (isset($_GET['clone']) and $_GET['clone'] == 'yes') {
            $poll = Database::get()->querySingle("SELECT * FROM poll WHERE pid = ?d", $pid);
            $questions = Database::get()->queryArray("SELECT * FROM poll_question WHERE pid = ?d", $pid);

            $poll->name .= " ($langCopy2)";
            $poll_data = array(
                $poll->creator_id, 
                $poll->course_id, 
                $poll->name, 
                $poll->creation_date, 
                $poll->start_date, 
                $poll->end_date, 
                $poll->description, 
                $poll->end_message, 
                $poll->anonymized
            );
            $new_pid = Database::get()->query("INSERT INTO poll
                                SET creator_id = ?d,
                                    course_id = ?d,
                                    name = ?s,
                                    creation_date = ?t,
                                    start_date = ?t,
                                    end_date = ?t,
                                    description = ?s,
                                    end_message = ?s,
                                    anonymized = ?d,    
                                    active = 1", $poll_data)->lastInsertID;

            foreach ($questions as $question) {
                $new_pqid = Database::get()->query("INSERT INTO poll_question
                                           SET pid = ?d,
                                               question_text = ?s,
                                               qtype = ?d", $new_pid, $question->question_text, $question->qtype);
                $answers = Database::get()->queryArray("SELECT * FROM poll_question_answer WHERE pqid = ?d", $question->pqid);
                foreach ($answers as $answer) {
                    Database::get()->query("INSERT INTO poll_question_answer
                                            SET pqid = ?d,
                                                answer_text = ?s",$new_pqid, $answer->answer_text);
                }
            }
            redirect_to_home_page('modules/questionnaire/index.php?course='.$course_code);
        }        
    }
    $tool_content .= "
        <div id=\"operations_container\">
	  <ul id=\"opslist\">
	    <li><a href='admin.php?course=$course_code&amp;newPoll=yes'>$langCreatePoll</a></li>
	  </ul>
	</div>";
}

printPolls();
add_units_navigation(TRUE);
draw($tool_content, 2, null, $head_content);


/* * *************************************************************************************************
 * printPolls()
 * ************************************************************************************************** */

function printPolls() {
    global $tool_content, $course_id, $course_code, $langCreatePoll,
    $langPollsActive, $langTitle, $langPollCreator, $langPollCreation,
    $langPollStart, $langPollEnd, $langPollNone, $is_editor, $langAnswers,
    $themeimg, $langEdit, $langDelete, $langActions,
    $langDeactivate, $langPollsInactive, $langPollHasEnded, $langActivate,
    $langParticipate, $langVisible, $user_id, $langHasParticipated, $langSee,
    $langHasNotParticipated, $uid, $langConfirmDelete, $langPurgeExercises,
    $langPurgeExercises, $langConfirmPurgeExercises, $langCreateDuplicate ;

    $poll_check = 0;
    $result = Database::get()->queryArray("SELECT * FROM poll WHERE course_id = ?d", $course_id);
    $num_rows = count($result);
    if ($num_rows > 0)
        ++$poll_check;
    if (!$poll_check) {
        $tool_content .= "\n    <p class='alert1'>" . $langPollNone . "</p><br>";
    } else {
        // Print active polls
        $tool_content .= "
		      <table align='left' width='100%' class='tbl_alt'>
		      <tr>
			<th colspan='2'><div align='left'>&nbsp;$langTitle</div></th>
			<th class='center'>$langPollStart</th>
			<th class='center'>$langPollEnd</th>";

        if ($is_editor) {
            $tool_content .= "<th class='center' width='16'>$langAnswers</th>"
                           . "<th class='center'>$langActions</th>";
        } else {
            $tool_content .= "<th class='center'>$langParticipate</th>";
        }
        $tool_content .= "</tr>";
        $index_aa = 1;
        $k = 0;
        foreach ($result as $thepoll) {
            $visibility = $thepoll->active;

            if (($visibility) or ($is_editor)) {
                if ($visibility) {
                    if ($k % 2 == 0) {
                        $visibility_css = " class=\"even\"";
                    } else {
                        $visibility_css = " class=\"odd\"";
                    }
                    $visibility_gif = "visible";
                    $visibility_func = "deactivate";
                    $arrow_png = "arrow";
                    $k++;
                } else {
                    $visibility_css = " class=\"invisible\"";
                    $visibility_gif = "invisible";
                    $visibility_func = "activate";
                    $arrow_png = "arrow";
                    $k++;
                }
                if ($k % 2 == 0) {
                    $tool_content .= "<tr $visibility_css>";
                } else {
                    $tool_content .= "<tr $visibility_css>";
                }
                $temp_CurrentDate = date("Y-m-d H:i");
                $temp_StartDate = $thepoll->start_date;
                $temp_EndDate = $thepoll->end_date;
                $temp_StartDate = mktime(substr($temp_StartDate, 11, 2), substr($temp_StartDate, 14, 2), 0, substr($temp_StartDate, 5, 2), substr($temp_StartDate, 8, 2), substr($temp_StartDate, 0, 4));
                $temp_EndDate = mktime(substr($temp_EndDate, 11, 2), substr($temp_EndDate, 14, 2), 0, substr($temp_EndDate, 5, 2), substr($temp_EndDate, 8, 2), substr($temp_EndDate, 0, 4));
                $temp_CurrentDate = mktime(substr($temp_CurrentDate, 11, 2), substr($temp_CurrentDate, 14, 2), 0, substr($temp_CurrentDate, 5, 2), substr($temp_CurrentDate, 8, 2), substr($temp_CurrentDate, 0, 4));
                $creator_id = $thepoll->creator_id;
                $theCreator = uid_to_name($creator_id);
                $pid = $thepoll->pid;
                $countAnswers = Database::get()->querySingle("SELECT COUNT(DISTINCT(user_id)) as counter FROM poll_answer_record WHERE pid = ?d", $pid)->counter;
                // check if user has participated
                $has_participated = Database::get()->querySingle("SELECT COUNT(*) as counter FROM poll_answer_record
                                        WHERE user_id = ?d AND pid = ?d", $uid, $pid)->counter;
                // check if poll has ended
                if (($temp_CurrentDate >= $temp_StartDate) && ($temp_CurrentDate < $temp_EndDate)) {
                    $poll_ended = 0;
                } else {
                    $poll_ended = 1;
                }
                if ($is_editor) {
                    $tool_content .= "
                        <td width='16'><img src='$themeimg/$arrow_png.png' title='bullet' /></td>
                        <td><a href='pollresults.php?course=$course_code&amp;pid=$pid'>".q($thepoll->name)."</a>";
                } else {
                    $tool_content .= "
                        <td><img style='border:0px; padding-top:3px;' src='$themeimg/arrow.png' title='bullet' /></td>
                        <td>";
                    if (($has_participated == 0) and $poll_ended == 0) {
                        $tool_content .= "<a href='pollparticipate.php?course=$course_code&amp;UseCase=1&pid=$pid'>".q($thepoll->name)."</a>";
                    } else {
                        $tool_content .= q($thepoll->name);
                    }
                }
                $tool_content .= "                       
                        <td class='center'>" . nice_format(date("Y-m-d H:i", strtotime($thepoll->start_date)), true) . "</td>
                        <td class='center'>" . nice_format(date("Y-m-d H:i", strtotime($thepoll->end_date)), true) . "</td>";
                if ($is_editor) {
                    $tool_content .= "
                        <td class='center'>$countAnswers</td>
                        <td class='center'>" .
                            icon('search', $langSee, "pollparticipate.php?course=$course_code&amp;UseCase=1&pid=$pid") .
                            "&nbsp;" . 
                            icon('edit', $langEdit, "admin.php?course=$course_code&amp;pid=$pid") .
                            "&nbsp;" . 
                            icon('clear', $langPurgeExercises,
                                "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;delete_results=yes&amp;pid=$pid",
                                "onClick=\"return confirmation('" . js_escape($langConfirmPurgeExercises) . "');\"") .
                            "&nbsp;" .
                            icon('delete', $langDelete,
                                "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;delete=yes&amp;pid=$pid",
                                "onClick=\"return confirmation('" . js_escape($langConfirmDelete) . "');\"") .
                            "&nbsp;" .
                            icon($visibility_gif, $langVisible,
                                "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;visibility=$visibility_func&amp;pid={$pid}") .
                            "&nbsp;" .
                            icon('duplicate', $langCreateDuplicate,
                                "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;clone=yes&amp;pid={$pid}") .
                            "</td></tr>";
                } else {
                    $tool_content .= "
                        <td class='center'>";
                    if (($has_participated == 0) and ($poll_ended == 0)) {
                        $tool_content .= "$langHasNotParticipated";
                    } else {
                        if ($poll_ended == 1) {
                            $tool_content .= $langPollHasEnded;
                        } else {
                            $tool_content .= $langHasParticipated;
                        }
                    }
                    $tool_content .= "</td></tr>";
                }
            }
            $index_aa ++;
        }
        $tool_content .= "</table>";
    }
}
