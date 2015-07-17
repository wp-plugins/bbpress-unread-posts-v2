<?php
/*
 * Plugin Name: bbPress Unread Posts v2
 * Description: Displays an icon next to each thread and forum if there are unread posts for the current user in it.
 * Version: 1.0.3
 * Author: coronoro
 */
include 'logging.php';
include 'bbp_unread_options.php';

add_action ( "init", "bbp_unread_posts_Initialize" );
add_action ( 'admin_menu', 'bbp_unread_posts_plugin_menu' );

function bbp_unread_posts_Initialize(){
	wp_enqueue_style ( "bbpress_unread_posts_Style", plugins_url ( "style.css", __FILE__ ) );
	if (is_user_logged_in ()) {
		add_action ( "bbp_theme_before_topic_title", "bbp_unread_posts_PerformTopicSpecificActions" );
		add_action ( "bbp_theme_before_topic_title", "bbp_unread_posts_IconWrapperBegin" );
		add_action ( "bbp_theme_after_topic_meta", "bbp_unread_posts_IconWrapperEnd" );
		add_action ( "bbp_template_after_single_topic", "bbp_unread_posts_OnTopicVisit" );
		add_filter ( "bbp_get_breadcrumb", "bbp_unread_posts_MarkAllTopicsAsReadButtonFilter" );
		
		add_action ( 'bbp_theme_before_forum_title', 'bbp_unread_forum_icons' );
	}
}

function bbp_unread_posts_plugin_menu(){
	add_options_page ( "Unread Posts", "Unread Posts", 'manage_options', 'bbpUnreadPosts', 'bpp_unread_settings_page' );
}

function bbp_unread_posts_PerformTopicSpecificActions(){
	if (bbp_unread_posts_IsMarkAllTopicsAsReadRequested ()) {
		$topicID = bbp_unread_posts_GetCurrentLoopedTopicID ();
		bbp_unread_posts_UpdateLastTopicVisit ( $topicID );
	}
}

function bbp_unread_posts_IconWrapperBegin(){
	bbp_unread_posts_clog ( "topic: " . bbp_get_topic_title ( $topicID ) );
	$topicID = bbp_unread_posts_GetCurrentLoopedTopicID ();
	$isUnreadTopic = bbp_is_topic_unread ( $topicID );
	bbp_unread_posts_clog ( "lastactive: " . $topicLastActiveTime . "   lastvisit: " . $lastVisitTime );
	echo '
			<div class="bbpresss_unread_posts_icon">
				<a href="' . bbp_get_topic_last_reply_url ( $topicID ) . '">
					<img src="' . plugins_url ( "images/" . ($isUnreadTopic ? "folder_new.gif" : "folder.gif"), __FILE__ ) . '">
				</a>
			</div>
			<div style="display:table-cell;">
		';
}

function bbp_unread_posts_GetCurrentLoopedTopicID(){
	return bbpress ()->topic_query->post->ID;
}

function bbp_unread_posts_IconWrapperEnd(){
	echo '
			</div>
		';
}

function bbp_unread_posts_OnTopicVisit(){
	$topicID = bbpress ()->reply_query->query ["post_parent"];
	bbp_unread_posts_UpdateLastTopicVisit ( $topicID );
}

function bbp_unread_posts_UpdateLastTopicVisit($topicID){
	bbp_unread_posts_clog ( "last visited update: " . current_time ( 'timestamp' ) );
	update_post_meta ( $topicID, bbp_unread_posts_getLastVisitMetaKey (), current_time ( 'timestamp' ) );
}

function bbp_unread_posts_getLastVisitMetaKey(){
	return "bbpress_unread_posts_last_visit_" . bbpress ()->current_user->id;
}

function bbp_unread_posts_MarkAllTopicsAsReadButtonFilter($content){
	$topicId = bbp_get_topic_id ();
	if ($topicId == 0) {
		$forumId = bbp_get_forum_id ();
		$html = '
				<div class="bbpress_mark_all_read_wrapper">
					<form action="" method="post" class="bbpress_mark_all_read">
						<input type="hidden" name="bbp_unread_posts_markAllTopicAsRead" value="1"/>
						<input type="hidden" name="bbp_unread_posts_markID" value="' .$forumId.'"/>
				';
		if (bbp_unread_posts_IsMarkAllTopicsAsReadRequested ()) {
			$html .= '
						<span class="markedUnread" style="font-weight:bold;">Marked all topics as read</span>
					';
		} else {
			$html .= '
						<input type="submit" value="Mark all topics as read"/>
					';
		}
		$html .= '
					</form>
				</div>
				';
		$content = $content . $html;
	}
	return $content;
}

function bbp_unread_posts_IsForumPage(){
	return isset ( bbpress ()->topic_query->posts );
}

function bbp_unread_posts_IsMarkAllTopicsAsReadRequested(){
	return isset ( $_POST ["bbp_unread_posts_markAllTopicAsRead"] );
}

function bbp_unread_posts_getRootForumId(){
	return isset ( $_POST ["bbp_unread_posts_markID"] );
}

function bbp_is_topic_unread($topicID){
	$topicLastActiveTime = bbp_convert_date ( get_post_meta ( $topicID, '_bbp_last_active_time', true ) );
	$lastVisitTime = get_post_meta ( $topicID, bbp_unread_posts_getLastVisitMetaKey (), true );
	bbp_unread_posts_clog ( "Last Active Time " . $topicLastActiveTime . "   >   last visit time" . $lastVisitTime );
	return $topicLastActiveTime > $lastVisitTime;
}

function bbp_unread_forum_icons(){
	if ('forum' == get_post_type ()) {
		if(bbp_unread_posts_IsMarkAllTopicsAsReadRequested()){
			$forumId = bbp_unread_posts_getRootForumId();
			bbp_markAllUnread($forumId);
			$unread = false;
		}else{
			$forumId = bbp_get_forum_id ();
			$unread = bbp_isForumUnread ( $forumId );
		}
		echo '
				<div class="bbpresss_unread_posts_icon">
					<a href="' . bbp_get_forum_last_reply_url ( $forumId ) . '">
						<img src="' . plugins_url ( "images/" . ($unread ? "folder_new.gif" : "folder.gif"), __FILE__ ) . '">
					</a>
				</div>
				<div style="display:table-cell;">
				';
	}
}

function bbp_isForumUnread($forumId){
	bbp_unread_posts_clog ( "forum: " . bbp_get_forum_title ( $forumId ) );
	if ($forumId != null && ! empty ( $forumId )) {
		$childs = bbp_get_all_child_ids ( $forumId, bbp_get_topic_post_type () );
		$max = count ( $childs );
		$topicID;
		for($i = 0; $i <= $max; $i ++) {
			$topicID = $childs [$i];
			bbp_unread_posts_clog ( " -- found subtopic: $topicID" );
			if (! empty ( $topicID ) && bbp_is_topic_unread ( $topicID )) {
				bbp_unread_posts_clog ( " -- unread topic!" );
				return true;
			}
		}
		$childs = bbp_get_all_child_ids ( $forumId, bbp_get_forum_post_type () );
		$max = count ( $childs );
		$subforumID;
		for($i = 0; $i <= $max; $i ++) {
			$subforumID = $childs [$i];
			bbp_unread_posts_clog ( " -- found subforum:" . bbp_get_forum_title ( $subforumID ) );
			if (! empty ( $subforumID ) && bbp_isForumUnread ( $subforumID )) {
				bbp_unread_posts_clog ( " -- unread forum!" );
				return true;
			}
		}
	}
	return false;
}

function bbp_markAllUnread($forumId){
	bbp_unread_posts_clog ( "marking all topic for: " . bbp_get_forum_title ( $forumId ) . " as unread" );
	if ($forumId != null && ! empty ( $forumId )) {
		$childs = bbp_get_all_child_ids ( $forumId, bbp_get_topic_post_type () );
		$max = count ( $childs );
		$topicID;
		for($i = 0; $i <= $max; $i ++) {
			$topicID = $childs [$i];
			if (! empty ( $topicID ) && bbp_is_topic_unread ( $topicID )) {
				bbp_unread_posts_UpdateLastTopicVisit($topicID);
			}
		}
		$childs = bbp_get_all_child_ids ( $forumId, bbp_get_forum_post_type () );
		$max = count ( $childs );
		$subforumID;
		for($i = 0; $i <= $max; $i ++) {
			$subforumID = $childs [$i];
			if (! empty ( $subforumID )) {
				bbp_isForumUnread ( $subforumID );
			}
		}
	}
}



?>