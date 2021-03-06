<?php

/*******************************************************************************
 * Action functions are exactly the same as screen functions, however they do
 * not have a template screen associated with them. Usually they will send the
 * user back to the default screen after execution.
 */

// Allow core components and dependent plugins to register activity actions
function bp_register_activity_actions() {
	do_action( 'bp_register_activity_actions' );
}
add_action( 'bp_init', 'bp_register_activity_actions', 8 );

function bp_activity_action_permalink_router() {
	global $bp;

	// Not viewing activity
	if ( ( $bp->activity->slug != bp_current_component() ) || !bp_is_current_action( 'p' ) )
		return false;

	// No activity to display
	if ( empty( $bp->action_variables[0] ) || !is_numeric( $bp->action_variables[0] ) )
		return false;

	// Get the activity details
	$activity = bp_activity_get_specific( array( 'activity_ids' => $bp->action_variables[0] ) );

	// Redirect to root if activity does not exist
	if ( !$activity = $activity['activities'][0] )
		bp_core_redirect( bp_get_root_domain() );

	// Do not redirect at default
	$redirect = false;

	// Redirect based on the type of activity
	if ( bp_is_active( 'groups' ) && $activity->component == $bp->groups->id ) {

		// Activity is a user update
		if ( !empty( $activity->user_id ) ) {
			$redirect = bp_core_get_user_domain( $activity->user_id, $activity->user_nicename, $activity->user_login ) . $bp->activity->slug . '/' . $activity->id . '/';

		// Activity is something else
		} else {

			// Set redirect to group activity stream
			if ( $group = groups_get_group( array( 'group_id' => $activity->item_id ) ) ) {
				$redirect = bp_get_group_permalink( $group ) . $bp->activity->slug . '/' . $activity->id . '/';
			}
		}

	// Set redirect to users' activity stream
	} else {
		$redirect = bp_core_get_user_domain( $activity->user_id, $activity->user_nicename, $activity->user_login ) . $bp->activity->slug . '/' . $activity->id;
	}

	// Allow redirect to be filtered
	if ( !$redirect = apply_filters_ref_array( 'bp_activity_permalink_redirect_url', array( $redirect, &$activity ) ) )
		bp_core_redirect( bp_get_root_domain() );

	// Redirect to the actual activity permalink page
	bp_core_redirect( $redirect );
}
add_action( 'bp_actions', 'bp_activity_action_permalink_router' );

/**
 * bp_activity_action_delete_activity()
 *
 * Delete specific activity item and redirect to previous page.
 *
 * @global object $bp
 * @since 1.1
 * @uses do_action() Calls 'bp_activity_action_delete_activity' hook to allow actions to be taken after the activity is deleted.
 * @uses do_action() Calls 'bp_activity_before_action_delete_activity' hook to allow actions to be taken before the activity is deleted.
 */
function bp_activity_action_delete_activity( $activity_id = 0 ) {
	global $bp;

	// Not viewing activity or action is not delete
	if ( !bp_is_activity_component() || !bp_is_current_action( 'delete' ) )
		return false;

	if ( empty( $activity_id ) && !empty( $bp->action_variables[0] ) && is_numeric( $bp->action_variables[0] ) )
		$activity_id = (int) $bp->action_variables[0];

	// Not viewing a specific activity item
	if ( empty( $activity_id ) )
		return false;

	// Check the nonce
	check_admin_referer( 'bp_activity_delete_link' );

	// Load up the activity item
	$activity = new BP_Activity_Activity( $activity_id );

	// Check access
	if ( empty( $activity->user_id ) || !is_super_admin() && $activity->user_id != bp_loggedin_user_id() )
		return false;

	// Call the action before the delete so plugins can still fetch information about it
	do_action( 'bp_activity_before_action_delete_activity', $activity_id, $activity->user_id );

	// Delete the activity item and provide user feedback
	if ( bp_activity_delete( array( 'id' => $activity_id, 'user_id' => $activity->user_id ) ) )
		bp_core_add_message( __( 'Activity deleted successfully', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error when deleting that activity', 'buddypress' ), 'error' );

	do_action( 'bp_activity_action_delete_activity', $activity_id, $activity->user_id );

	// Check for the redirect query arg, otherwise let WP handle things
 	if ( !empty( $_GET['redirect_to'] ) )
		bp_core_redirect( esc_url( $_GET['redirect_to'] ) );
	else
		bp_core_redirect( wp_get_referer() );
}
add_action( 'bp_actions', 'bp_activity_action_delete_activity' );

function bp_activity_action_post_update() {
	global $bp;

	// Do not proceed if user is not logged in, not viewing activity, or not posting
	if ( !is_user_logged_in() || ( $bp->activity->slug != bp_current_component() ) || !bp_is_current_action( 'post' ) )
		return false;

	// Check the nonce
	check_admin_referer( 'post_update', '_wpnonce_post_update' );

	// Get activity info
	$content = apply_filters( 'bp_activity_post_update_content', $_POST['whats-new']             );
	$object  = apply_filters( 'bp_activity_post_update_object',  $_POST['whats-new-post-object'] );
	$item_id = apply_filters( 'bp_activity_post_update_item_id', $_POST['whats-new-post-in']     );

	// No activity content so provide feedback and redirect
	if ( empty( $content ) ) {
		bp_core_add_message( __( 'Please enter some content to post.', 'buddypress' ), 'error' );
		bp_core_redirect( wp_get_referer() );
	}

	// No existing item_id
	if ( empty( $item_id ) ) {
		$activity_id = bp_activity_post_update( array( 'content' => $content ) );

	// Post to groups object
	} else if ( 'groups' == $object && bp_is_active( 'groups' ) ) {
		if ( (int)$item_id ) {
			$activity_id = groups_post_update( array( 'content' => $content, 'group_id' => $item_id ) );
		}

	// Special circumstance so let filters handle it
	} else {
		$activity_id = apply_filters( 'bp_activity_custom_update', $object, $item_id, $content );
	}

	// Provide user feedback
	if ( !empty( $activity_id ) )
		bp_core_add_message( __( 'Update Posted!', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error when posting your update, please try again.', 'buddypress' ), 'error' );

	// Redirect
	bp_core_redirect( wp_get_referer() );
}
add_action( 'bp_actions', 'bp_activity_action_post_update' );

function bp_activity_action_post_comment() {
	global $bp;

	if ( !is_user_logged_in() || ( $bp->activity->slug != bp_current_component() ) || !bp_is_current_action( 'reply' ) )
		return false;

	// Check the nonce
	check_admin_referer( 'new_activity_comment', '_wpnonce_new_activity_comment' );

	$activity_id = apply_filters( 'bp_activity_post_comment_activity_id', $_POST['comment_form_id'] );
	$content = apply_filters( 'bp_activity_post_comment_content', $_POST['ac_input_' . $activity_id] );

	if ( empty( $content ) ) {
		bp_core_add_message( __( 'Please do not leave the comment area blank.', 'buddypress' ), 'error' );
		bp_core_redirect( wp_get_referer() . '#ac-form-' . $activity_id );
	}

	$comment_id = bp_activity_new_comment( array(
		'content' => $content,
		'activity_id' => $activity_id,
		'parent_id' => $parent_id
	));

	if ( !empty( $comment_id ) )
		bp_core_add_message( __( 'Reply Posted!', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error posting that reply, please try again.', 'buddypress' ), 'error' );

	bp_core_redirect( wp_get_referer() . '#ac-form-' . $activity_id );
}
add_action( 'bp_actions', 'bp_activity_action_post_comment' );

function bp_activity_action_mark_favorite() {
	global $bp;

	if ( !is_user_logged_in() || ( $bp->activity->slug != bp_current_component() ) || !bp_is_current_action( 'favorite' ) )
		return false;

	// Check the nonce
	check_admin_referer( 'mark_favorite' );

	if ( bp_activity_add_user_favorite( $bp->action_variables[0] ) )
		bp_core_add_message( __( 'Activity marked as favorite.', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error marking that activity as a favorite, please try again.', 'buddypress' ), 'error' );

	bp_core_redirect( wp_get_referer() . '#activity-' . $bp->action_variables[0] );
}
add_action( 'bp_actions', 'bp_activity_action_mark_favorite' );

function bp_activity_action_remove_favorite() {
	global $bp;

	if ( !is_user_logged_in() || ( $bp->activity->slug != bp_current_component() ) || !bp_is_current_action( 'unfavorite' ) )
		return false;

	// Check the nonce
	check_admin_referer( 'unmark_favorite' );

	if ( bp_activity_remove_user_favorite( $bp->action_variables[0] ) )
		bp_core_add_message( __( 'Activity removed as favorite.', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error removing that activity as a favorite, please try again.', 'buddypress' ), 'error' );

	bp_core_redirect( wp_get_referer() . '#activity-' . $bp->action_variables[0] );
}
add_action( 'bp_actions', 'bp_activity_action_remove_favorite' );

function bp_activity_action_sitewide_feed() {
	global $bp, $wp_query;

	if ( !bp_is_current_component( 'activity' ) || !bp_is_current_action( 'feed' ) || bp_is_user() || !empty( $bp->groups->current_group ) )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'feeds/bp-activity-sitewide-feed.php' );
	die;
}
add_action( 'bp_actions', 'bp_activity_action_sitewide_feed' );

function bp_activity_action_personal_feed() {
	global $bp, $wp_query;

	if ( !bp_is_current_component( 'activity' ) || !bp_is_user() || !bp_is_current_action( 'feed' ) )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'feeds/bp-activity-personal-feed.php' );
	die;
}
add_action( 'bp_actions', 'bp_activity_action_personal_feed' );

function bp_activity_action_friends_feed() {
	global $bp, $wp_query;

	if ( !bp_is_active( 'friends' ) || !bp_is_current_component( 'activity' ) || !bp_is_user() || !bp_is_current_action( $bp->friends->slug ) || !isset( $bp->action_variables[0] ) || $bp->action_variables[0] != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'feeds/bp-activity-friends-feed.php' );
	die;
}
add_action( 'bp_actions', 'bp_activity_action_friends_feed' );

function bp_activity_action_my_groups_feed() {
	global $bp, $wp_query;

	if ( !bp_is_active( 'groups' ) || !bp_is_current_component( 'activity' ) || !bp_is_user() || !bp_is_current_action( $bp->groups->slug ) || !isset( $bp->action_variables[0] ) || $bp->action_variables[0] != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'feeds/bp-activity-mygroups-feed.php' );
	die;
}
add_action( 'bp_actions', 'bp_activity_action_my_groups_feed' );

function bp_activity_action_mentions_feed() {
	global $bp, $wp_query;

	if ( !bp_is_current_component( 'activity' ) || !bp_is_user() || !bp_is_current_action( 'mentions' ) || !isset( $bp->action_variables[0] ) || $bp->action_variables[0] != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'feeds/bp-activity-mentions-feed.php' );
	die;
}
add_action( 'bp_actions', 'bp_activity_action_mentions_feed' );

function bp_activity_action_favorites_feed() {
	global $bp, $wp_query;

	if ( !bp_is_current_component( 'activity' ) || !bp_is_user() || !bp_is_current_action( 'favorites' ) || !isset( $bp->action_variables[0] ) || $bp->action_variables[0] != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'feeds/bp-activity-favorites-feed.php' );
	die;
}
add_action( 'bp_actions', 'bp_activity_action_favorites_feed' );

?>
