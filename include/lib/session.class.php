<?php

/* ========================================================================
 * Open eClass 3.0
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2013  Greek Universities Network - GUnet
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
 * Eclass Session object
 *
 * Holds information about the current logged-in user
 */
class Session {

    public $user_id;
    public $username;
    public $givenname;
    public $surname;
    public $fullname;
    public $status;
    public $course_id;
    public $course_code;
    public $course_title;
    public $courses;
    public $language;
    public $active_ui_languages;
    public $native_language_names;

    public function __construct() {
        global $native_language_names_init;
        
        $this->active_ui_languages = explode(' ', get_config('active_ui_languages'));
        // Set active user interface languages
        $this->native_language_names = array();
        foreach ($this->active_ui_languages as $langcode) {
            if (isset($native_language_names_init[$langcode])) {
                $this->native_language_names[$langcode] = $native_language_names_init[$langcode];
            }
        }
        if (isset($_REQUEST['localize'])) {
            $this->language = $_SESSION['langswitch'] = $this->validate_language_code($_REQUEST['localize']);
        } elseif (isset($_SESSION['langswitch'])) {
            $this->language = $_SESSION['langswitch'];
        } else {
            $this->language = get_config('default_language');
        }

        if (isset($_SESSION['uid'])) {
            $this->user_id = $_SESSION['uid'];
        } else {
            $this->user_id = 0;
        }
        if (isset($_SESSION['status'])) {
            $this->status = intval($_SESSION['status']);
        } else {
            $this->status = 0;
        }
    }

    public function logout() {
        unset($this->user_id);
        unset($this->username);
        unset($this->givenname);
        unset($this->surname);
        unset($this->fullname);
        unset($this->status);
        unset($this->courses);
        unset($this->language);
    }
    
    // Sets flash data
    public static function flash($key, $data) {
        $_SESSION[$key]['data'] = $data;
        if (!isset($_SESSION['flash_new'])) {
            $_SESSION['flash_new'] = array();
        }
        array_push($_SESSION['flash_new'], $key);       
    }
    public static function has($key) {
        if (isset($_SESSION[$key]['data'])) {
            return TRUE;
        } else {
            return FALSE;
        }   
    }    
    //Sets automatically generated on next request messages
    public static function Messages($messages, $class='alert1'){
        if ( !is_array($messages)) $messages = array($class => array($messages));
        foreach ($messages as $message) {
            if (is_array($message)) {
                $i=0;
                foreach ($message as $row) {
                    $_SESSION['messages'][$class][$i] = $row;
                    $i++;
                }
            } else {
                $_SESSION['messages'][$class] = $message;
            }
        }
        if(!isset($_SESSION['flash_new'])) $_SESSION['flash_new'] = array();
        array_push($_SESSION['flash_new'], 'messages');
        return new self;
    }
    // Flashes posted variables
    public static function flashPost() {
        foreach ($_POST as $key => $value){
          self::flash($key, $value);   
        }
        return new self;
    }
    // Flashes posted variable errors
    public function Errors($errors){
        foreach ($errors as $key => $error) {
            $_SESSION[$key]['errors'] = $error;
        }
        if(!isset($_SESSION['flash_new'])) $_SESSION['flash_new'] = array();
        $keys = array_unique($keys);
        foreach($keys as $key) {
            array_push($_SESSION['flash_new'], $key);
        }
        array_push($_SESSION['flash_new'], 'messages');         
        return new self;
    }      
    public static function get($key) {
        if(isset($_SESSION[$key]['data'])) {
            return $_SESSION[$key]['data'];
        } else {
            return FALSE;
        }
    }   
    public static function getError($key, $class='caution') {
        if (isset($_SESSION[$key]['errors'][0])){
            return "<div class='$class'>".$_SESSION[$key]['errors'][0]."</div>";
        } else {
            return FALSE;
        }
    }
    public static function getMessages() {
       if (!isset($_SESSION['messages'])) {
            return null;
        }
        $item_messages = $_SESSION['messages'];
        $msg_boxes = '';

        foreach($item_messages as $class => $value){
            $msg_boxes .= "<div class='$class'><ul><li>".(is_array($value) ? implode('</li><li>', $value) : $value)."</li></ul></div>";
        }
        unset($_SESSION['messages']);
        return $msg_boxes;
    }
    
    // Make sure a language code is valid - if not, default language is Greek
    public function validate_language_code($langcode, $default = 'el') {     
        if (array_search($langcode, $this->active_ui_languages) === false) {
            return $default;
        } else {
            return $langcode;
        }
    }
}
