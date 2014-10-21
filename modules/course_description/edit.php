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

$require_current_course = TRUE;
$require_help = TRUE;
$helpTopic = 'Coursedescription';
$require_login = true;
$require_editor = true;

require_once '../../include/baseTheme.php';
require_once 'include/lib/textLib.inc.php';
require_once 'include/log.php';

$nameTools = $langEditCourseProgram;
$navigation[] = array('url' => 'index.php?course=' . $course_code, 'name' => $langCourseProgram);

if (isset($_REQUEST['id'])) {
    $editId = intval($_REQUEST['id']);
    $q = Database::get()->querySingle("SELECT title, comments, type FROM course_description WHERE course_id = ?d AND id = ?d", $course_id, $editId);
    $cdtitle = $q->title;
    $comments = $q->comments;
    $defaultType = $q->type;
} else {
    $editId = false;
    $cdtitle = $comments = $defaultType = '';
}

$q = Database::get()->queryArray("SELECT id, title FROM course_description_type ORDER BY `order`");
$types = array();
$types[''] = '';
foreach ($q as $type) {
    $title = $titles = @unserialize($type->title);
    if ($titles !== false) {
        if (isset($titles[$language]) && !empty($titles[$language])) {
            $title = $titles[$language];
        } else if (isset($titles['en']) && !empty($titles['en'])) {
            $title = $titles['en'];
        } else {
            $title = array_shift($titles);
        }
    }
    $types[$type->id] = $title;
}
$tool_content .= "<div id='operations_container'>
    <ul id='opslist'>
          <li><a href='index.php?course=$course_code'>$langBack</a></li>
    </ul>
  </div>";

$tool_content .= "<form method='post' action='index.php?course=$course_code'>";
if ($editId !== false) {
    $tool_content .= "<input type='hidden' name='editId' value='$editId' />";
}
$tool_content .= "
    <fieldset>        
    <table class='tbl'>
    <tr>
        <th width='100'>$langType:</th>
        <td>" . selection($types, 'editType', $defaultType, 'id="typSel"') . "
    </tr>
    <tr>
       <th>$langTitle:</th>
       <td><input type='text' name='editTitle' value='$cdtitle' size='40' id='titleSel'/></td>
    </tr>
    <tr>
       <th valign='top'>$langContent:</th>
       <td>" . @rich_text_editor('editComments', 4, 20, $comments) . "</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td class='right'>
            <a href='index.php?course=$course_code'>" . q($langBackAndForget) . "</a>
            &nbsp;&nbsp;
            <input class='Login' type='submit' name='saveCourseDescription' value='" . q($langAdd) . "' />
        </td>
    </tr>
    </table>
  </fieldset>
  </form>";


load_js('jquery');
$head_content .= <<<hCont
<script type="text/javascript">
/* <![CDATA[ */

    $(document).on('change', '#typSel', function (e) {
        //console.log(e);
        //alert($(this).children(':selected').text());
        $('#titleSel').val( $(this).children(':selected').text() );
    });

/* ]]> */
</script>
hCont;
draw($tool_content, 2, null, $head_content);
