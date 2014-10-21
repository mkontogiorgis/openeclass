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

/*
 * Open eClass 3.x standard stuff
 */
$require_current_course = true;
$require_login = true;
$require_help = false;
require_once '../../include/baseTheme.php';
require_once 'include/sendMail.inc.php';
require_once 'modules/group/group_functions.php';
require_once 'modules/search/indexer.class.php';
require_once 'modules/search/forumtopicindexer.class.php';
require_once 'modules/search/forumpostindexer.class.php';

$idx = new Indexer();
$ftdx = new ForumTopicIndexer($idx);
$fpdx = new ForumPostIndexer($idx);

require_once 'config.php';
require_once 'functions.php';



if (isset($_GET['forum'])) {
    $forum = intval($_GET['forum']);
} else {
    header("Location: index.php?course=$course_code");
    exit();
}
if (isset($_GET['topic'])) {
    $topic = intval($_GET['topic']);
} else {
    $topic = '';
}

$myrow = Database::get()->querySingle("SELECT id, name FROM forum WHERE id = ?d AND course_id = ?d", $forum, $course_id);

$forum_name = $myrow->name;
$forum_id = $myrow->id;

$is_member = false;
$group_id = init_forum_group_info($forum_id);

$nameTools = $langNewTopic;
$navigation[] = array('url' => "index.php?course=$course_code", 'name' => $langForums);
$navigation[] = array('url' => "viewforum.php?course=$course_code&amp;forum=$forum_id", 'name' => q($forum_name));

if (!does_exists($forum_id, "forum")) {
    $tool_content .= "<div class='caution'>$langErrorPost</div>";
    draw($tool_content, 2);
    exit;
}

$tool_content .= "<div id='operations_container'><ul id='opslist'>";
$tool_content .= "<li><a href='viewforum.php?course=$course_code&forum=$forum_id'>$langBack</a></li>";
$tool_content .= "</ul></div>";

if (isset($_POST['submit'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    if (empty($message) or empty($subject)) {
        header("Location: viewforum.php?course=$course_code&forum=$forum_id&empty=true");
        exit;
    }
    $message = purify($message);
    $poster_ip = $_SERVER['REMOTE_ADDR'];
    $time = date("Y-m-d H:i:s");

    $topic_id = Database::get()->query("INSERT INTO forum_topic (title, poster_id, forum_id, topic_time) VALUES (?s, ?d, ?d, ?t)"
                    , $subject, $uid, $forum_id, $time)->lastInsertID;

    $ftdx->store($topic_id);

    $post_id = Database::get()->query("INSERT INTO forum_post (topic_id, post_text, poster_id, post_time, poster_ip) VALUES (?d, ?s, ?d, ?t, ?s)"
                    , $topic_id, $message, $uid, $time, $poster_ip)->lastInsertID;
    $fpdx->store($post_id);
    
    $forum_user_stats = Database::get()->querySingle("SELECT COUNT(*) as c FROM forum_post 
                        INNER JOIN forum_topic ON forum_post.topic_id = forum_topic.id
                        INNER JOIN forum ON forum.id = forum_topic.forum_id
                        WHERE forum_post.poster_id = ?d AND forum.course_id = ?d", $uid, $course_id);
    Database::get()->query("DELETE FROM forum_user_stats WHERE user_id = ?d AND course_id = ?d", $uid, $course_id);
    Database::get()->query("INSERT INTO forum_user_stats (user_id, num_posts, course_id) VALUES (?d,?d,?d)", $uid, $forum_user_stats->c, $course_id);

    Database::get()->query("UPDATE forum_topic
                    SET last_post_id = ?d
                WHERE id = ?d
                AND forum_id = ?d", $post_id, $topic_id, $forum_id);

    Database::get()->query("UPDATE forum
                    SET num_topics = num_topics+1,
                    num_posts = num_posts+1,
                    last_post_id = ?d
		WHERE id = ?d", $post_id, $forum_id);

    $topic = $topic_id;
    $total_forum = get_total_topics($forum_id);
    $total_topic = get_total_posts($topic) - 1;
    // subtract 1 because we want the number of replies, not the number of posts.
    // --------------------------------
    // notify users
    // --------------------------------
    $subject_notify = "$logo - $langNewForumNotify";
    $category_id = forum_category($forum_id);
    $cat_name = category_name($category_id);
    $c = course_code_to_title($course_code);
    $name = uid_to_name($uid);
    $forum_message = "-------- $langBodyMessage ($langSender: $name)\n$message--------";
    $plain_forum_message = q(html2text($forum_message));
    $body_topic_notify = "$langBodyForumNotify $langInForums '" . q($forum_name) . "' 
                               $langInCat '" . q($cat_name) . "' $langTo $langCourseS '$c' <br /><br />" . q($forum_message) . "<br />
                               <br />$gunet<br /><a href='{$urlServer}courses/$course_code'>{$urlServer}courses/$course_code</a>";
    $plain_body_topic_notify = "$langBodyForumNotify $langInForums '" . q($forum_name) . "' $langInCat '" . q($cat_name) . "' $langTo $langCourseS '$c' \n\n$plain_forum_message \n\n$gunet\n<a href='{$urlServer}courses/$course_code'>{$urlServer}courses/$course_code</a>";
    $linkhere = "&nbsp;<a href='${urlServer}main/profile/emailunsubscribe.php?cid=$course_id'>$langHere</a>.";
    $unsubscribe = "<br /><br />$langNote: " . sprintf($langLinkUnsubscribe, $title);
    $plain_body_topic_notify .= $unsubscribe . $linkhere;
    $body_topic_notify .= $unsubscribe . $linkhere;

    $sql = Database::get()->queryArray("SELECT DISTINCT user_id FROM forum_notify
			WHERE (forum_id = ?d OR cat_id = ?d)
			AND notify_sent = 1 AND course_id = ?d AND user_id != ?d", $forum_id, $category_id, $course_id, $uid);
    foreach ($sql as $r) {
        if (get_user_email_notification($r->user_id, $course_id)) {
            $emailaddr = uid_to_email($r->user_id);
            send_mail_multipart('', '', '', $emailaddr, $subject_notify, $plain_body_topic_notify, $body_topic_notify, $charset);
        }
    }
    // end of notification

    $tool_content .= "<p class='success'>$langStored</p>
		<p class='back'>&laquo; <a href='viewtopic.php?course=$course_code&amp;topic=$topic_id&amp;forum=$forum_id&amp;$total_topic'>$langReturnMessages</a></p>
		<p class='back'>&laquo; <a href='viewforum.php?course=$course_code&amp;forum=$forum_id'>$langReturnTopic</a></p>";
} else {
    $tool_content .= "
        <form action='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;topic=$topic&forum=$forum_id' method='post'>
        <fieldset>
          <legend>$langTopicData</legend>
	  <table class='tbl' width='100%'>
	  <tr>
	    <th>$langSubject:</th>
	    <td><input type='text' name='subject' size='53' maxlength='100' /></td>
	  </tr>
	  <tr>
            <th valign='top'>$langBodyMessage:</th>
            <td>" . rich_text_editor('message', 14, 50, '', '') . "</td>
          </tr>
	  <tr>
            <th>&nbsp;</th>
	    <td class='right'>
	       <input class='Login' type='submit' name='submit' value='$langSubmit' />&nbsp;	       
	    </td>
          </tr>
	  </table>
	</fieldset>
	</form>";
}
draw($tool_content, 2, null, $head_content);
