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

/**
 * 	@file searchuser.php
 * 	@authors list: Karatzidis Stratos <kstratos@uom.gr>
  Pitsiougas Vagelis <vagpits@uom.gr>
 *      @brief: user search form based upon criteria/filters
 */
$require_usermanage_user = TRUE;
require_once '../../include/baseTheme.php';
require_once 'modules/auth/auth.inc.php';
require_once 'include/lib/hierarchy.class.php';
require_once 'include/lib/user.class.php';
require_once 'hierarchy_validations.php';

$user = new User();

load_js('jquery');
load_js('jquery-ui');
load_js('jstree');
load_js('jquery-ui-timepicker-addon.min.js');

$head_content .= "<link rel='stylesheet' type='text/css' href='{$urlAppend}js/jquery-ui-timepicker-addon.min.css'>
<script type='text/javascript'>
$(function() {
$('input[name=user_registered_at]').datetimepicker({
    dateFormat: 'yy-mm-dd', 
    timeFormat: 'hh:mm'
    });
});
</script>";

$navigation[] = array('url' => 'index.php', 'name' => $langAdmin);
$nameTools = $langSearchUser;

// get the incoming values
$inactive_checked = (isset($_GET['search']) and $_GET['search'] == 'inactive') ?
        ' checked' : '';
$lname = isset($_GET['lname']) ? $_GET['lname'] : '';
$fname = isset($_GET['fname']) ? $_GET['fname'] : '';
$uname = isset($_GET['uname']) ? canonicalize_whitespace($_GET['uname']) : '';
$am = isset($_GET['am']) ? $_GET['am'] : '';
$verified_mail = isset($_GET['verified_mail']) ? intval($_GET['verified_mail']) : 3;
$user_type = isset($_GET['user_type']) ? intval($_GET['user_type']) : '';
$auth_type = isset($_GET['auth_type']) ? intval($_GET['auth_type']) : '';
$email = isset($_GET['email']) ? mb_strtolower(trim($_GET['email'])) : '';
$reg_flag = isset($_GET['reg_flag']) ? intval($_GET['reg_flag']) : '';
$user_registered_at = isset($_GET['user_registered_at']) ? $_GET['user_registered_at'] : '';

if (isset($_GET['department'])) {
    $depts_defaults = array('params' => 'name="department"', 'tree' => array('0' => $langAllFacultes), 'useKey' => 'id', 'multiple' => false, 'defaults' => array_map('intval', $_GET['department']));
} else {
    $depts_defaults = array('params' => 'name="department"', 'tree' => array('0' => $langAllFacultes), 'useKey' => 'id', 'multiple' => false);
}

if (isDepartmentAdmin()) {
    $allowables = array('allowables' => $user->getDepartmentIds($uid));
    $depts_defaults = array_merge($depts_defaults, $allowables);
}

// Display Actions Toolbar
$tool_content .= "
  <div id='operations_container'>
    <ul id='opslist'>
      <li><a href='listusers.php?search=yes'>$langAllUsers</a></li>
      <li><a href='listusers.php?search=inactive'>$langInactiveUsers</a></li>
    </ul>
  </div>";

// display the search form
$tool_content .= "
<form action='listusers.php' method='get' name='user_search'>
<fieldset>
  <legend>$langUserData</legend>
  <table class='tbl' width='100%'>
  <tr>
    <th class='left'>$langName:</th>
    <td><input type='text' name='fname' size='40' value='" . q($fname) . "'></td>
  </tr>
  <tr>
    <th class='left' width='180'>$langSurname:</th>
    <td><input type='text' name='lname' size='40' value='" . q($lname) . "'></td>
  </tr>
  <tr>
    <th class='left'>$langAm:</th>
    <td><input type='text' name='am' size='30' value='" . q($am) . "'></td>
  </tr>
  <tr>
    <th class='left'>$langUserType:</th>
    <td>";

$usertype_data = array(
    0 => $langAllUsers,
    USER_TEACHER => $langTeacher,
    USER_STUDENT => $langStudent,
    USER_GUEST => $langGuest);

$tool_content .= selection($usertype_data, 'user_type', 0) . "
    </td>
  </tr>
  <tr>
    <th class='left'>$langAuthMethod:</th>
    <td>";

// enalaktika mporoume na valoume mono tous energous tropous
//$auth_methods = get_auth_active_methods();
$authtype_data = $auth_ids;
$authtype_data[0] = $langAllAuthTypes;
$tool_content .= selection($authtype_data, 'auth_type', 0) . "
    </td>
    <tr>
    <th class='left'>$langRegistrationDate:</th>
    <td>";

$tool_content .= selection(array('1' => $langAfter, '2' => $langBefore), 'reg_flag', $reg_flag);

$tool_content .= "<input type='text' name='user_registered_at' value='" . $user_registered_at . "'>";

$tool_content .= "</td></tr>
  <tr>
    <th class='left'>$langEmailVerified:</th>
    <td>";

$verified_mail_data = array(
    EMAIL_VERIFICATION_REQUIRED => $m['pending'],
    EMAIL_VERIFIED => $m['yes'],
    EMAIL_UNVERIFIED => $m['no'],
    3 => $langAllUsers);
$tool_content .= selection($verified_mail_data, 'verified_mail', $verified_mail) . "
    </td>
  </tr>
  <tr>
    <th class='left'>$langEmail:</th>
    <td><input type='text' name='email' size='40' value='" . q($email) . "'></td>
  </tr>
  <tr>
    <th class='left'>$langUsername:</th>
    <td><input type='text' name='uname' size='40' value='" . q($uname) . "'></td>
  </tr>
  <tr>
    <th class='left'>$langInactiveUsers:</th>
    <td><input type='checkbox' name='search' value='inactive'$inactive_checked></td>
  </tr>
  <tr>
    <th>$langFaculty:</th>
    <td>";
$tree = new Hierarchy();
list($js, $html) = $tree->buildNodePicker($depts_defaults);
$head_content .= $js;
$tool_content .= $html . "
    </td>
  </tr>
  <tr>
    <th class='left'>$langSearchFor:</th>
    <td>
      <select name='search_type'>
        <option value='exact'>$langSearchExact</option>
        <option value='begin'>$langSearchStartsWith</option>
        <option value='substring' selected>$langSearchSubstring</option>
      </select>
    </td>
  </tr>
  <tr>
    <th>&nbsp;</th>
    <td colspan='2' class='right'>
      <input type='submit' value='$langSearch'>
    </td>
  </tr>
  </table>
</fieldset>
</form>";
// end form

$tool_content .= "<p align='right'><a href='index.php'>$langBack</a></p>";

// display administrator menu
draw($tool_content, 3, null, $head_content);
