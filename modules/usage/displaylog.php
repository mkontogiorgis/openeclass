<?php

/* ========================================================================
 * Open eClass 3.0
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2012  Greek Universities Network - GUnet
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

/**
 * @file displaylog.php
 * @author Yannis Exidaridis <jexi@noc.uoa.gr>
 * @brief display form for displaying course actions
 */
if (isset($_GET['from_admin'])) {
    $course_id = $_GET['c'];
} else {
    $require_current_course = true;
    $require_login = true;
}

$require_course_admin = true;
require_once '../../include/baseTheme.php';
require_once 'include/log.php';

load_js('jquery');
load_js('jquery-ui');
load_js('jquery-ui-timepicker-addon.min.js');
load_js('datatables');
load_js('datatables_filtering_delay');

$head_content .= "<script type='text/javascript'>
        $(document).ready(function() {
            $('#log_results_table').DataTable ({                                
                'sPaginationType': 'full_numbers',
                'bAutoWidth': true,                
                'oLanguage': {
                   'sLengthMenu':   '$langDisplay _MENU_ $langResults2',
                   'sZeroRecords':  '".$langNoResult."',
                   'sInfo':         '$langDisplayed _START_ $langTill _END_ $langFrom2 _TOTAL_ $langTotalResults',
                   'sInfoEmpty':    '$langDisplayed 0 $langTill 0 $langFrom2 0 $langResults2',
                   'sInfoFiltered': '',
                   'sInfoPostFix':  '',
                   'sSearch':       '".$langSearch."',
                   'sUrl':          '',
                   'oPaginate': {
                       'sFirst':    '&laquo;',
                       'sPrevious': '&lsaquo;',
                       'sNext':     '&rsaquo;',
                       'sLast':     '&raquo;'
                   }
               }
            }).fnSetFilteringDelay(1000);
            $('.dataTables_filter input').attr('placeholder', '$langDetail');
        });
        </script>";

$head_content .= "<link rel='stylesheet' type='text/css' href='{$urlAppend}js/jquery-ui-timepicker-addon.min.css'>
<script type='text/javascript'>
$(function() {
$('input[name=u_date_start]').datetimepicker({
    dateFormat: 'yy-mm-dd', 
    timeFormat: 'hh:mm'
    });
});

$(function() {
$('input[name=u_date_end]').datetimepicker({
    dateFormat: 'yy-mm-dd', 
    timeFormat: 'hh:mm'
    });
});
</script>";

if (!isset($_REQUEST['course_code'])) {
    $course_code = course_id_to_code($course_id);
}

$nameTools = $langUsersLog;
$navigation[] = array('url' => 'index.php?course=' . $course_code, 'name' => $langUsage);

$logtype = isset($_REQUEST['logtype']) ? intval($_REQUEST['logtype']) : '0';
$u_user_id = isset($_REQUEST['u_user_id']) ? intval($_REQUEST['u_user_id']) : '-1';
$u_module_id = isset($_REQUEST['u_module_id']) ? intval($_REQUEST['u_module_id']) : '-1';
$u_date_start = isset($_REQUEST['u_date_start']) ? $_REQUEST['u_date_start'] : strftime('%Y-%m-%d', strtotime('now -30 day'));
$u_date_end = isset($_REQUEST['u_date_end']) ? $_REQUEST['u_date_end'] : strftime('%Y-%m-%d', strtotime('now +1 day'));

if (isset($_REQUEST['submit'])) {
    $log = new Log();
    $log->display($course_id, $u_user_id, $u_module_id, $logtype, $u_date_start, $u_date_end, $_SERVER['PHP_SELF']);
}

$letterlinks = '';
$result = Database::get()->queryArray("SELECT LEFT(a.surname, 1) AS first_letter
        FROM user AS a LEFT JOIN course_user AS b ON a.id = b.user_id
        WHERE b.course_id = ?d
        GROUP BY first_letter ORDER BY first_letter", $course_id);

foreach ($result as $row) {
    $first_letter = $row->first_letter;
    $letterlinks .= '<a href="?course=' . $course_code . '&amp;first=' . urlencode($first_letter) . '">' . q($first_letter) . '</a> ';
}

$user_opts = "<option value='-1'>$langAllUsers</option>";
if (isset($_GET['first'])) {
    $firstletter = $_GET['first'];
    $result = Database::get()->queryArray("SELECT a.id, a.surname, a.givenname, a.username, a.email, b.status
                FROM user AS a LEFT JOIN course_user AS b ON a.id = b.user_id
                WHERE b.course_id = ?d AND LEFT(a.surname,1) = ?s", $course_id, $firstletter);
} else {
    $result = Database::get()->queryArray("SELECT a.id, a.surname, a.givenname, a.username, a.email, b.status
        FROM user AS a LEFT JOIN course_user AS b ON a.id = b.user_id
        WHERE b.course_id = ?d", $course_id);              
}

foreach ($result as $row) {
    if ($u_user_id == $row->id) {
        $selected = 'selected';
    } else {
        $selected = '';
    }
    $user_opts .= '<option ' . $selected . ' value="' . $row->id . '">' .
            q($row->givenname . ' ' . $row->surname) . "</option>";
}
$tool_content .= "<form method='post' action='$_SERVER[SCRIPT_NAME]?course=$course_code'>
        <fieldset>
        <legend>$langUsersLog</legend>
        <table class='tbl'>
        <tr>
        <td>&nbsp;</td>
        <td class='bold'>$langCreateStatsGraph:</td>
        </tr>
        <th class='left'>$langLogModules :</th>
        <td><select name='u_module_id'>";
$tool_content .= "<option value='-1'>$langAllModules</option>";
foreach ($modules as $m => $mid) {
    $extra = '';
    if ($u_module_id == $m) {
        $extra = 'selected';
    }
    $tool_content .= "<option value=" . $m . " $extra>" . $mid['title'] . "</option>";
}
if ($u_module_id == MODULE_ID_USERS) {
    $extra = 'selected';
}
if ($u_module_id == MODULE_ID_TOOLADMIN) {
    $extra = 'selected';
}
$tool_content .= "<option value = " . MODULE_ID_USERS . " $extra>$langAdminUsers</option>";
$tool_content .= "<option value = " . MODULE_ID_TOOLADMIN . " $extra>$langExternalLinks</option>";
$tool_content .= "</select></td></tr>
        <tr><th class='left'>$langLogTypes :</th>
         <td>";
$log_types = array(0 => $langAllActions,
    LOG_INSERT => $langInsert,
    LOG_MODIFY => $langModify,
    LOG_DELETE => $langDelete);
$tool_content .= selection($log_types, 'logtype', $logtype);
$tool_content .= "</td></tr>
        <tr>
        <th class='left'>$langStartDate :</th>
        <td><input type='text' name ='u_date_start' value='" . q($u_date_start) . "'></td>
        </tr>
        <tr>
        <th class='left'>$langEndDate :</th>
        <td><input type='text' name ='u_date_end' value='" . q($u_date_end) . "'></td>
        </tr>
        <tr>
        <th class='left' rowspan='2' valign='top'>$langUser:</td>
        <td>$langFirstLetterUser : $letterlinks </td>
        </tr>
        <tr>
        <td><select name='u_user_id'>$user_opts</select></td>
        </tr>
        <tr>
        <td>&nbsp;</td>
        <td><input type='submit' name='submit' value='$langSubmit'>
        </td>
        </tr>
        </table>
        </fieldset>
        </form>";

if (isset($_GET['from_admin'])) {
    draw($tool_content, 3, null, $head_content);
} else {
    draw($tool_content, 2, null, $head_content);
}
