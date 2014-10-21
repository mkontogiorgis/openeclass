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

$require_current_course = true;
$require_login = true;
$require_help = true;
$helpTopic = 'For';
require_once '../../include/baseTheme.php';
require_once 'modules/group/group_functions.php';
require_once 'modules/search/indexer.class.php';
require_once 'modules/search/forumtopicindexer.class.php';
require_once 'modules/search/forumpostindexer.class.php';

$idx = new Indexer();
$ftdx = new ForumTopicIndexer($idx);
$fpdx = new ForumPostIndexer($idx);

if (!add_units_navigation(true)) {
    $navigation[] = array('url' => "index.php?course=$course_code", 'name' => $langForums);
}

require_once 'config.php';
require_once 'functions.php';

if ($is_editor) {
    load_js('tools.js');
    load_js('jquery');
    $head_content .= '<script>
                          function lock(topic_id,course_code) {
                            $.getJSON("lock_topic.php?course="+course_code+"&topic="+topic_id,function(response){
                              if (response) {
                                if ($("#lock-"+topic_id).attr("src").indexOf("lock_closed.png") != -1) {
                                  $("#lock-"+topic_id).attr("src",$("#lock-"+topic_id).attr("src").replace("lock_closed.png","lock_open.png"));
                                } else if ($("#lock-"+topic_id).attr("src").indexOf("lock_open.png") != -1) {
                                  $("#lock-"+topic_id).attr("src",$("#lock-"+topic_id).attr("src").replace("lock_open.png","lock_closed.png"));
                                }
                              }
                            });
                          }
                      </script>';
}

$paging = true;
$next = 0;
if (isset($_GET['forum'])) {
    $forum_id = intval($_GET['forum']);
} else {
    header("Location: index.php?course=$course_code");
    exit();
}
$is_member = false;
$group_id = init_forum_group_info($forum_id);

$myrow = Database::get()->querySingle("SELECT id, name FROM forum WHERE id = ?d AND course_id = ?d", $forum_id, $course_id);

$forum_name = $myrow->name;
$forum_id = $myrow->id;

$nameTools = q($forum_name);

if (isset($_GET['empty'])) { // if we come from newtopic.php
    $tool_content .= "<p class='alert1'>$langEmptyNewTopic</p>";
}

if ($can_post) {
    $tool_content .= "
	<div id='operations_container'>
	<ul id='opslist'>
	<li>
	<a href='newtopic.php?course=$course_code&amp;forum=$forum_id'>$langNewTopic</a>
	</li>
	</ul>
	</div>";
}

/*
 * Retrieve and present data from course's forum
 */

$total_topics = Database::get()->querySingle("SELECT num_topics FROM forum
                WHERE id = ?d
                AND course_id = ?d", $forum_id, $course_id)->num_topics;

if ($total_topics > $topics_per_page) {
    $pages = intval($total_topics / $topics_per_page) + 1; // get total number of pages
}

if (isset($_GET['start'])) {
    $first_topic = intval($_GET['start']);
} else {
    $first_topic = 0;
}

if ($total_topics > $topics_per_page) { // navigation
    $base_url = "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;forum=$forum_id&amp;start=";
    $tool_content .= "<table width='100%'><tr>";
    $tool_content .= "<td width='50%' align='left'><span class='row'><strong class='pagination'>
		<span class='pagination'>$langPages:&nbsp;";
    $current_page = $first_topic / $topics_per_page + 1; // current page
    for ($x = 1; $x <= $pages; $x++) { // display navigation numbers
        if ($current_page == $x) {
            $tool_content .= "$x";
        } else {
            $start = ($x - 1) * $topics_per_page;
            $tool_content .= "<a href='$base_url&amp;start=$start'>$x</a>";
        }
    }
    $tool_content .= "</span></strong></span></td>";
    $tool_content .= "<td colspan='4' align='right'>";

    $next = $first_topic + $topics_per_page;
    $prev = $first_topic - $topics_per_page;
    if ($prev < 0) {
        $prev = 0;
    }

    if ($first_topic == 0) { // beginning
        $tool_content .= "<a href='$base_url$next'>$langNextPage</a>";
    } elseif ($first_topic + $topics_per_page < $total_topics) {
        $tool_content .= "<a href='$base_url$prev'>$langPreviousPage</a>&nbsp|&nbsp;
		<a href='$base_url$next'>$langNextPage</a>";
    } elseif ($start - $topics_per_page < $total_topics) { // end
        $tool_content .= "<a href='$base_url$prev'>$langPreviousPage</a>";
    }
    $tool_content .= "</td></tr></table>";
}

// delete topic
if (($is_editor) and isset($_GET['topicdel'])) {
    if (isset($_GET['topic_id'])) {
        $topic_id = intval($_GET['topic_id']);
    }
    $number_of_posts = get_total_posts($topic_id);
    $sql = Database::get()->queryArray("SELECT id,poster_id FROM forum_post WHERE topic_id = ?d", $topic_id);
    $post_authors = array();
    foreach ($sql as $r) {
        $post_authors[] = $r->poster_id;
        Database::get()->query("DELETE FROM forum_post WHERE id = $r->id");
    }
    $post_authors = array_unique($post_authors);
    foreach ($post_authors as $author) {
        $forum_user_stats = Database::get()->querySingle("SELECT COUNT(*) as c FROM forum_post
                        INNER JOIN forum_topic ON forum_post.topic_id = forum_topic.id
                        INNER JOIN forum ON forum.id = forum_topic.forum_id
                        WHERE forum_post.poster_id = ?d AND forum.course_id = ?d", $author, $course_id);
        Database::get()->query("DELETE FROM forum_user_stats WHERE user_id = ?d AND course_id = ?d", $author, $course_id);
        if ($forum_user_stats->c != 0) {
            Database::get()->query("INSERT INTO forum_user_stats (user_id, num_posts, course_id) VALUES (?d,?d,?d)", $author, $forum_user_stats->c, $course_id);
        }
    }
    $fpdx->removeByTopic($topic_id);
    $number_of_topics = get_total_topics($forum_id);
    $num_topics = $number_of_topics - 1;
    if ($number_of_topics < 0) {
        $num_topics = 0;
    }
    Database::get()->query("DELETE FROM forum_topic WHERE id = ?d AND forum_id = ?d", $topic_id, $forum_id);
    $ftdx->remove($topic_id);
    Database::get()->query("UPDATE forum SET num_topics = ?d,
                                num_posts = num_posts-$number_of_posts
                            WHERE id = ?d
                                AND course_id = ?d", $num_topics, $forum_id, $course_id);
    Database::get()->query("DELETE FROM forum_notify WHERE topic_id = ?d AND course_id = ?d", $topic_id, $course_id);
}

// modify topic notification
if (isset($_GET['topicnotify'])) {    
    if (isset($_GET['topic_id'])) {
        $topic_id = $_GET['topic_id'];
    }
    $rows = Database::get()->querySingle("SELECT COUNT(*) AS count FROM forum_notify
		WHERE user_id = ?d AND topic_id = ?d AND course_id = ?d", $uid, $topic_id, $course_id);
    if ($rows->count > 0) {
        Database::get()->query("UPDATE forum_notify SET notify_sent = ?d
			WHERE user_id = ?d AND topic_id = ?d AND course_id = ?d", $_GET['topicnotify'], $uid, $topic_id, $course_id);
    } else {
        Database::get()->query("INSERT INTO forum_notify SET user_id = ?d,
		topic_id = $topic_id, notify_sent = 1, course_id = ?d", $uid, $course_id);
    }
}

$result = Database::get()->queryArray("SELECT t.*, p.post_time, p.poster_id AS poster_id
        FROM forum_topic t
        LEFT JOIN forum_post p ON t.last_post_id = p.id
        WHERE t.forum_id = ?d
        ORDER BY topic_time DESC LIMIT $first_topic, $topics_per_page", $forum_id);


if (count($result) > 0) { // topics found
    $tool_content .= "
	<table width='100%' class='tbl_alt'>
	<tr>
	  <th colspan='2'>&nbsp;$langSubject</th>
	  <th width='70' class='center'>$langAnswers</th>
	  <th width='150' class='center'>$langSender</th>
	  <th width='80' class='center'>$langSeen</th>
	  <th width='190' class='center'>$langLastMsg</th>
	  <th width='80' class='center'>$langActions</th>
	</tr>";
    $i = 0;
    foreach ($result as $myrow) {
        if ($i % 2 == 1) {
            $tool_content .= "<tr class='odd'>";
        } else {
            $tool_content .= "<tr class='even'>";
        }
        $replies = $myrow->num_replies;
        $topic_id = $myrow->id;
        $last_post_datetime = $myrow->post_time;
        list($last_post_date, $last_post_time) = explode(' ', $last_post_datetime);
        list($year, $month, $day) = explode("-", $last_post_date);
        list($hour, $min, $sec) = explode(":", $last_post_time);
        $last_post_time = mktime($hour, $min, $sec, $month, $day, $year);
        if (!isset($last_visit)) {
            $last_visit = 0;
        }
        if ($replies >= $hot_threshold) {
            if ($last_post_time < $last_visit)
                $image = $hot_folder_image;
            else
                $image = $hot_newposts_image;
        } else {
            if ($last_post_time < $last_visit) {
                $image = $folder_image;
            } else {
                $image = $newposts_image;
            }
        }
        $tool_content .= "<td width='1'><img src='$image' /></td>";
        $topic_title = $myrow->title;
        $topic_locked = $myrow->locked;
        $pagination = '';
        $topiclink = "viewtopic.php?course=$course_code&amp;topic=$topic_id&amp;forum=$forum_id";
        if ($replies > $posts_per_page) {
            $total_reply_pages = ceil($replies / $posts_per_page);
            $pagination .= "<strong class='pagination'><span>\n<img src='$posticon_more' />";
            add_topic_link(0, $total_reply_pages);
            if ($total_reply_pages > PAGINATION_CONTEXT + 1) {
                $pagination .= "&nbsp;...&nbsp;";
            }
            for ($p = max(1, $total_reply_pages - PAGINATION_CONTEXT); $p < $total_reply_pages; $p++) {
                add_topic_link($p, $total_reply_pages);
            }
            $pagination .= "&nbsp;</span></strong>";
        }
        $tool_content .= "<td><a href='$topiclink'><b>" . q($topic_title) . "</b></a>$pagination</td>";
        $tool_content .= "<td class='center'>$replies</td>";
        $tool_content .= "<td class='center'>" . q(uid_to_name($myrow->poster_id)) . "</td>";
        $tool_content .= "<td class='center'>$myrow->num_views</td>";
        $tool_content .= "<td class='center'>" . q(uid_to_name($myrow->poster_id)) . "<br />$last_post_datetime</td>";
        $sql = Database::get()->querySingle("SELECT notify_sent FROM forum_notify
			WHERE user_id = ?d AND topic_id = ?d AND course_id = ?d", $uid, $myrow->id, $course_id);
        if ($sql) {
            $topic_action_notify = $sql->notify_sent;
        }
        if (!isset($topic_action_notify)) {
            $topic_link_notify = FALSE;
            $topic_icon = '_off';
        } else {
            $topic_link_notify = toggle_link($topic_action_notify);
            $topic_icon = toggle_icon($topic_action_notify);
        }
        $tool_content .= "<td class='center'>";
        if ($is_editor) {
            $tool_content .= "<a href='forum_admin.php?course=$course_code&amp;forumtopicedit=yes&amp;topic_id=$myrow->id'>
			<img src='$themeimg/edit.png' title='$langModify' alt='$langModify' /></a>";
            $tool_content .= "
			<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;forum=$forum_id&amp;topic_id=$myrow->id&amp;topicdel=yes' onClick=\"return confirmation('$langConfirmDelete');\">
			<img src='$themeimg/delete.png' title='$langDelete' alt='$langDelete' />
			</a>";
            
            if ($topic_locked == 0) {
                $tool_content .= "<a href='javascript:void(0)' onclick='lock($myrow->id,\"$course_code\")'><img id='lock-$myrow->id' src='$themeimg/lock_open.png' title='$langLockTopic' alt='$langLockTopic' /></a>";
            } else {
                $tool_content .= "<a href='javascript:void(0)' onclick='lock($myrow->id,\"$course_code\")'><img id='lock-$myrow->id' src='$themeimg/lock_closed.png' title='$langUnlockTopic' alt='$langUnlockTopic' /></a>";
            }
        }
        if (isset($_GET['start']) and $_GET['start'] > 0) {
            $tool_content .= "
			<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;forum=$forum_id&amp;start=$_GET[start]&amp;topicnotify=$topic_link_notify&amp;topic_id=$myrow->id'>
			<img src='$themeimg/email$topic_icon.png' title='$langNotify' />
			</a>";
        } else {            
            $tool_content .= "<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;forum=$forum_id&amp;topicnotify=$topic_link_notify&amp;topic_id=$myrow->id'>
			<img src='$themeimg/email$topic_icon.png' title='$langNotify' />
			</a>";
        }
        $tool_content .= "</td></tr>";
        $i++;
    } // end of while
    $tool_content .= "</table>";
} else {
    $tool_content .= "<div class='alert1'>$langNoTopics</div>";
}
draw($tool_content, 2, null, $head_content);
