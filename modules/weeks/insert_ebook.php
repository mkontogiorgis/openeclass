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
 * 
 * @global type $id
 * @global type $course_id
 * @global type $course_code
 * @global type $tool_content
 * @global type $urlServer
 * @global type $mysqlMainDb
 * @global type $langAddModulesButton
 * @global type $langChoice
 * @global type $langNoEbook
 * @global type $langEBook
 * @global type $course_code
 * @global type $themeimg
 */
function list_ebooks() {
    global $id, $course_id, $course_code, $tool_content, $urlServer,
    $langAddModulesButton, $langChoice, $langNoEBook,
    $langEBook, $course_code, $themeimg;
    
    $result = Database::get()->queryArray("SELECT * FROM ebook WHERE course_id = ?d ORDER BY `order`", $course_id);
    if (count($result) == 0) {
        $tool_content .= "<p class='alert1'>$langNoEBook</p>";
    } else {
        $tool_content .= "<form action='insert.php?course=$course_code' method='post'>
				<input type='hidden' name='id' value='$id' />" .
                "<table class='tbl_alt' width='99%'>" .
                "<tr>" .
                "<th align='left'>&nbsp;$langEBook</th>" .
                "<th width='80' class='center'>$langChoice</th>" .
                "</tr>";
        $unit_parameter = 'unit=' . $id;
        foreach ($result as $catrow) {        
            $tool_content .= "<tr>";
            $tool_content .= "<td class='bold'><img src='$themeimg/folder_open.png' />&nbsp;&nbsp;" .
                    q($catrow->title) . "</td>";
            $tool_content .= "<td align='center'>
                            <input type='checkbox' name='ebook[]' value='$catrow->id' />
                            <input type='hidden' name='ebook_title[$catrow->id]'
                               value='" . q($catrow->title) . "'></td>";
            $tool_content .= "</tr>";
            $q = Database::get()->queryArray("SELECT ebook_section.id AS sid,
                                    ebook_section.public_id AS psid,
                                    ebook_section.title AS section_title,
                                    ebook_subsection.id AS ssid,
                                    ebook_subsection.public_id AS pssid,
                                    ebook_subsection.title AS subsection_title,
                                    document.path,
                                    document.filename
                                    FROM ebook, ebook_section, ebook_subsection, document
                                    WHERE ebook.id = ?d AND
                                        ebook.course_id = ?d AND
                                        ebook_section.ebook_id = ebook.id AND
                                        ebook_section.id = ebook_subsection.section_id AND
                                        document.id = ebook_subsection.file_id AND
                                        document.course_id = ?d AND
                                        document.subsystem = " . EBOOK . "
                                        ORDER BY CONVERT(psid, UNSIGNED), psid,
                                                 CONVERT(pssid, UNSIGNED), pssid", $catrow->id, $course_id, $course_id);

            $ebook_url_base = "{$urlServer}modules/ebook/show.php/$course_code/$catrow->id/";
            $old_sid = false;
            $class = 'odd';
            foreach ($q as $row) {
                $class = ($class == 'odd') ? 'even' : 'odd';
                $sid = $row->sid;
                $ssid = $row->ssid;
                $display_id = $sid . ',' . $ssid;
                $surl = $ebook_url_base . $display_id . '/' . $unit_parameter;
                if ($old_sid != $sid) {
                    $tool_content .= "<tr class='even'>
                                    <td class='section'><img src='$themeimg/links_on.png' />&nbsp;&nbsp;
                                        " . q($row->section_title) . "</td>
                                    <td align='center'><input type='checkbox' name='section[]' value='$sid' />
                                        <input type='hidden' name='section_title[$sid]'
                                               value='" . q($row->section_title) . "'></td></tr>";
                }

                $tool_content .= "<tr class='$class'>
                                <td class='subsection'><img src='$themeimg/links_on.png' />&nbsp;&nbsp;
                                <a href='" . q($surl) . "' target='_blank'>" . q($row->subsection_title) . "</a></td>
                                <td align='center'><input type='checkbox' name='subsection[]' value='$ssid' />
                                   <input type='hidden' name='subsection_title[$ssid]'
                                          value='" . q($row->subsection_title) . "'></td>
                            </tr>";
                $old_sid = $sid;
            }
        }
        $tool_content .= "<tr>" .
                "<th colspan='2'><div align='right'>" .
                "<input type='submit' name='submit_ebook' value='$langAddModulesButton' /></div></th>" .
                "</tr></table></form>";
    }
}
