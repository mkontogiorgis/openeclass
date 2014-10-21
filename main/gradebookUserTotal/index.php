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

$require_login = true;
$require_help = true;
$helpTopic = 'Gradebook';

require_once '../../include/baseTheme.php';

//Module name
$nameTools = $langGradebook;
$userID = $uid;
$tool_content .= "<table class='sortable' width='100%' id='t2'><tr><th>$langCourse</th><th>$langGradebookGrade</th><th>$langMore</th></tr>";

$courses = Database::get()->queryArray("SELECT course.id course_id, code, title FROM course, course_user, user 
                                            WHERE course.id = course_user.course_id
                                            AND course_user.user_id = ?d 
                                            AND user.id = ?d
                                            AND course.visible != " . COURSE_INACTIVE . "", $userID, $userID);
//print_r($courses);
foreach ($courses as $course1) {
    $course_id = $course1->course_id;    
    $gradebook = Database::get()->querySingle("SELECT id, students_semester,`range` FROM gradebook WHERE course_id = ?d", $course_id);
    if ($gradebook) {
        $gradebook_id = $gradebook->id;  
        $tool_content .= "<tr><td>".$course1->title."</td><td>".userGradeTotal($gradebook_id, $userID)." ($langMax: ".$gradebook->range.")</td>
                               <td><a href='../../modules/gradebook/index.php?course=".$course1->code."'>$langMore</a></td></tr>";
    }
}
$tool_content .= "</table>";



/**
 * @brief get total number of user attend in a course gradebook
 * @param type $gradebook_id
 * @param type $userID
 * @return string
 */
function userGradeTotal ($gradebook_id, $userID) {
    
    $visible = 1;
    
    $userGradeTotal = Database::get()->querySingle("SELECT SUM(grade * weight) AS count FROM gradebook_book, gradebook_activities
                                        WHERE gradebook_book.uid = ?d AND  gradebook_book.gradebook_activity_id = gradebook_activities.id 
                                        AND gradebook_activities.gradebook_id = ?d 
                                        AND gradebook_activities.visible = ?d", $userID, $gradebook_id, $visible)->count;

    if ($userGradeTotal) {
        return round($userGradeTotal/100, 2);
    } else {
        return "-";
    }
}

draw($tool_content, 1, null, $head_content);




  