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

/* ===========================================================================
  details.php
  @last update: 05-12-2006 by Thanos Kyritsis
  @authors list: Thanos Kyritsis <atkyritsis@upnet.gr>

  based on Claroline version 1.7 licensed under GPL
  copyright (c) 2001, 2006 Universite catholique de Louvain (UCL)

  original file: tracking/learnPath_details.php Revision: 1.19

  Claroline authors: Piraux Sebastien <pir@cerdecam.be>
  ==============================================================================
  @Description: This script displays the stats of all users of a course
  for his progression into the chosen learning path

  @Comments:

  @todo:
  ==============================================================================
 */

$require_current_course = TRUE;
$require_editor = TRUE;

require_once '../../include/baseTheme.php';
require_once 'include/lib/learnPathLib.inc.php';

$navigation[] = array("url" => "index.php?course=$course_code", "name" => $langLearningPaths);
$nameTools = $langStatsOfLearnPath;

// path id can not be empty, return to the list of learning paths
if (empty($_REQUEST['path_id'])) {
    header("Location: ./index.php?course=$course_code");
    exit();
}

$path_id = intval($_REQUEST['path_id']);

// get infos about the learningPath
$learnPathName = Database::get()->querySingle("SELECT `name` FROM `lp_learnPath` WHERE `learnPath_id` = ?d AND `course_id` = ?d", $path_id, $course_id);

if ($learnPathName) {
    // display title
    $titleTab['subTitle'] = htmlspecialchars($learnPathName->name);

    // display a list of user and their respective progress
    $sql = "SELECT U.`surname`, U.`givenname`, U.`id`
		FROM `user` AS U,
		     `course_user` AS CU
		WHERE U.`id` = CU.`user_id`
		AND CU.`course_id` = $course_id
		ORDER BY U.`surname` ASC, U.`givenname` ASC";

    @$tool_content .= get_limited_page_links($sql, 30, $langPreviousPage, $langNextPage);

    $usersList = get_limited_list($sql, 30);

    // display tab header
    $tool_content .= '' . "\n\n"
            . '      <p>' . $langLearnPath . ': <b>';
    $tool_content .= disp_tool_title($titleTab);
    $tool_content .= '</b></p>' . "\n"
            . '    <table width="99%" class="tbl_alt">' . "\n"
            . '    <tr>' . "\n"
            . '      <th>&nbsp;</th>' . "\n"
            . '      <th><div align="left">' . $langStudent . '</div></th>' . "\n"
            . '      <th colspan="2" width="25%">' . $langProgress . '</th>' . "\n"
            . '    </tr>' . "\n";

    // display tab content
    $k = 0;
    foreach ($usersList as $user) {
        $lpProgress = get_learnPath_progress($path_id, $user->id);
        if ($k % 2 == 0) {
            $tool_content .= "\n    <tr class=\"even\">";
        } else {
            $tool_content .= "\n    <tr class=\"odd\">";
        }
        $tool_content .= '' . "\n"
                . '      <td width="1"><img src="' . $themeimg . '/arrow.png" alt="bullet" title="bullet" border="0"></td>' . "\n"
                . '      <td><a href="detailsUserPath.php?course=' . $course_code . '&amp;uInfo=' . $user->id . '&amp;path_id=' . $path_id . '">' . q($user->surname) . ' ' . q($user->givenname) . '</a></td>' . "\n"
                . '      <td align="right">'
                . disp_progress_bar($lpProgress, 1)
                . '</td>' . "\n"
                . '      <td align="left"><small>' . $lpProgress . '%</small></td>' . "\n"
                . '    </tr>' . "\n";
        $k++;
    }
    // foot of table
    $tool_content .= '    ' . "\n\n" . '    </table>' . "\n\n";
}

draw($tool_content, 2, null, $head_content);
