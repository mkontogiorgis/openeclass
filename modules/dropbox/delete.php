<?php

/* ========================================================================
 * Open eClass 3.0
* E-learning and Course Management System
* ========================================================================
* Copyright 2003-2013  Greek Universities Network - GUnet
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

$require_login = TRUE;
$guest_allowed = FALSE;

include '../../include/baseTheme.php';

require_once("class.msg.php");

if (isset($_POST['tid'])) {
    require_once("class.thread.php");
    
    $tid = intval($_POST['tid']);
    $thread = new Thread($tid, $uid);
    if (!$thread->error) {
        $thread->delete();
    }
    
} elseif (isset($_POST['mid'])) {
    $mid = intval($_POST['mid']);
    $msg = new Msg($mid, $uid);
    if (!$msg->error) {
        $msg->delete();
    }
}
