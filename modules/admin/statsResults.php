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


require_once 'modules/graphics/plotter.php';
$usage_defaults = array(
    'u_stats_type' => 'visits',
    'u_interval' => 'daily',
    'u_user_id' => -1,
    'u_date_start' => strftime('%Y-%m-%d', strtotime('now -30 day')),
    'u_date_end' => strftime('%Y-%m-%d', strtotime('now')),
);

foreach ($usage_defaults as $key => $val) {
    if (!isset($_POST[$key])) {
        $$key = $val;
    } else {
        $$key = q($_POST[$key]);
    }
}

#see if chart has content

$date_fmt = '%Y-%m-%d';
$u_date_start = mysql_real_escape_string($u_date_start);
$u_date_end = mysql_real_escape_string($u_date_end);
$date_where = " (`when` BETWEEN '$u_date_start' AND '$u_date_end') ";
$date_what = "DATE_FORMAT(MIN(`when`), '$date_fmt') AS date_start, DATE_FORMAT(MAX(`when`), '$date_fmt') AS date_end ";

switch ($u_interval) {
    case "summary":
        $date_what = '';
        $date_group = '';
        break;
    case "daily":
        $date_what .= ", DATE_FORMAT(`when`, '$date_fmt') AS date ,";
        $date_group = " GROUP BY DATE(`when`) ";
        break;
    case "weekly":
        $date_what .= ", DATE_FORMAT(`when` - INTERVAL WEEKDAY(`when`) DAY, '$date_fmt') AS week_start " .
                ", DATE_FORMAT(`when` + INTERVAL (6 - WEEKDAY(`when`)) DAY, '$date_fmt') AS week_end ,";
        $date_group = " GROUP BY WEEK(`when`)";
        break;
    case "monthly":
        $date_what .= ", MONTH(`when`) AS month ,";
        $date_group = " GROUP BY MONTH(`when`)";
        break;
    case "yearly":
        $date_what .= ", YEAR(`when`) AS year ,";
        $date_group = "  GROUP BY YEAR(`when`) ";
        break;
    default:
        $date_what = '';
        $date_group = '';
        break;
}
if ($u_user_id != -1) {
    $user_where = " (id_user = '$u_user_id') ";
} else {
    $user_where = " (1) ";
}


switch ($u_stats_type) {
    case "visits":
        $result = Database::get()->queryArray("SELECT " . $date_what . " COUNT(*) AS cnt FROM loginout WHERE $date_where AND $user_where AND action='LOGIN' $date_group ORDER BY `when` ASC");
        $chart = new Plotter(220, 200);
        $chart->setTitle($langVisits);
        switch ($u_interval) {
            case "summary":
                foreach ($result as $row) {
                    $chart->growWithPoint($langSummary, $row->cnt);
                }
                break;
            case "daily":
                foreach ($result as $row) {
                    $chart->growWithPoint($row->date, $row->cnt);
                }
                break;
            case "weekly":
                foreach ($result as $row) {
                    $chart->growWithPoint($row->week_start . ' - ' . $row->week_end, $row->cnt);
                }
                break;
            case "monthly":
                foreach ($result as $row) {
                    $chart->growWithPoint($langMonths[$row->month], $row->cnt);
                }
                break;
            case "yearly":
                foreach ($result as $row) {
                    $chart->growWithPoint($row->year, $row->cnt);
                }
                break;
        }
        break;
}

$tool_content .= $chart->plot($langNoStatistics);
