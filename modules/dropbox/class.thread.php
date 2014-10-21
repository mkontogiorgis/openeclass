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

Class Thread {
    
    var $id;
    var $subject;
    var $recipients;
    var $course_id;
    var $error = false;
    //var $is_read;
    //user context
    var $uid;
    
    /**
     * Constructor
     * @param id the thread id
     * @param uid the user id
     */
    public function __construct($id, $uid) {
        
        $this->id = $id;
        $this->uid = $uid;
        
        $sql = "SELECT `dropbox_index`.`recipient_id`, `dropbox_msg`.`subject`, `dropbox_msg`.`course_id`  
                FROM `dropbox_msg`,`dropbox_index`
                WHERE `dropbox_msg`.`id` = `dropbox_index`.`msg_id`
                AND `dropbox_index`.`thread_id` = ?d 
                GROUP BY `dropbox_index`.`recipient_id`";
        
        $res = Database::get()->queryArray($sql, $id);
        if (!empty($res)) {
            foreach ($res as $r) {
                $this->recipients[] = $r->recipient_id;
            }
            
            $this->subject = $r->subject;
            $this->course_id = $r->course_id;
        } else {
            $this->error = true;
        }
    }
    
    /**
     * Get the messages of a thread that are visible in
     * the user context
     * @return msg objects
     */
    public function getMsgs() {
        $msgs = array();
        
        $sql = "SELECT DISTINCT `msg_id` 
                FROM `dropbox_index` 
                WHERE `thread_id` = ?d 
                AND `deleted` = ?d
                AND `recipient_id` = ?d";
        $res = Database::get()->queryArray($sql, $this->id, 0, $this->uid);
        foreach ($res as $r) {
            $msgs[] = new Msg($r->msg_id, $this->uid);
        }
        return $msgs;
    }
    
    /**
     * Delete thread
     */
    public function delete() {
        $msgs = $this->getMsgs();
        //delete all messages of this thread
        foreach ($msgs as $msg) {
            $msg->delete();
        }
    }
}