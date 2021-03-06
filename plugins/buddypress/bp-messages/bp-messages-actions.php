<?php

/*******************************************************************************
 * Action functions are exactly the same as screen functions, however they do not
 * have a template screen associated with them. Usually they will send the user
 * back to the default screen after execution.
 */

function messages_action_view_message() {
	global $bp, $thread_id;

	if ( $bp->current_component != $bp->messages->slug || $bp->current_action != 'view' )
		return false;

	$thread_id = 0;
	if ( !empty( $bp->action_variables[0] ) )
		$thread_id = $bp->action_variables[0];

	if ( !$thread_id || !messages_is_valid_thread( $thread_id ) || ( !messages_check_thread_access($thread_id) && !is_super_admin() ) )
		bp_core_redirect( $bp->displayed_user->domain . $bp->current_component );

	// Check if a new reply has been submitted
	if ( isset( $_POST['send'] ) ) {

		// Check the nonce
		check_admin_referer( 'messages_send_message', 'send_message_nonce' );

		// Send the reply
		if ( messages_new_message( array( 'thread_id' => $thread_id, 'subject' => $_POST['subject'], 'content' => $_POST['content'] ) ) )
			bp_core_add_message( __( 'Your reply was sent successfully', 'buddypress' ) );
		else
			bp_core_add_message( __( 'There was a problem sending your reply, please try again', 'buddypress' ), 'error' );

		bp_core_redirect( $bp->displayed_user->domain . $bp->current_component . '/view/' . $thread_id . '/' );
	}

	// Mark message read
	messages_mark_thread_read( $thread_id );
	
	// Decrease the unread count in the nav before it's rendered
	if ( $count = messages_get_unread_count() ) 
		$name = sprintf( __( 'Messages <strong>(%s)</strong>', 'buddypress' ), $count ); 
	else 
		$name = __( 'Messages <strong></strong>', 'buddypress' ); 
	
	$bp->bp_nav[$bp->messages->slug]['name'] = $name;

	do_action( 'messages_action_view_message' );

	bp_core_new_subnav_item( array(
		'name'            => sprintf( __( 'From: %s', 'buddypress' ), BP_Messages_Thread::get_last_sender( $thread_id ) ),
		'slug'            => 'view',
		'parent_url'      => $bp->loggedin_user->domain . $bp->messages->slug . '/',
		'parent_slug'     => $bp->messages->slug,
		'screen_function' => true,
		'position'        => 40,
		'user_has_access' => bp_is_my_profile(),
		'link'            => $bp->loggedin_user->domain . $bp->messages->slug . '/view/' . (int) $thread_id
	) );

	bp_core_load_template( apply_filters( 'messages_template_view_message', 'members/single/home' ) );
}
add_action( 'bp_actions', 'messages_action_view_message' );

function messages_action_delete_message() {
	global $bp, $thread_id;

	if ( $bp->current_component != $bp->messages->slug || 'notices' == $bp->current_action || empty( $bp->action_variables[0] ) || 'delete' != $bp->action_variables[0] )
		return false;

	$thread_id = $bp->action_variables[1];

	if ( !$thread_id || !is_numeric($thread_id) || !messages_check_thread_access($thread_id) ) {
		bp_core_redirect( $bp->displayed_user->domain . $bp->current_component . '/' . $bp->current_action );
	} else {
		if ( !check_admin_referer( 'messages_delete_thread' ) )
			return false;

		// Delete message
		if ( !messages_delete_thread($thread_id) ) {
			bp_core_add_message( __('There was an error deleting that message.', 'buddypress'), 'error' );
		} else {
			bp_core_add_message( __('Message deleted.', 'buddypress') );
		}
		bp_core_redirect( $bp->loggedin_user->domain . $bp->current_component . '/' . $bp->current_action );
	}
}
add_action( 'bp_actions', 'messages_action_delete_message' );

function messages_action_bulk_delete() {
	global $bp, $thread_ids;

	if ( $bp->current_component != $bp->messages->slug || empty( $bp->action_variables[0] ) || 'bulk-delete' != $bp->action_variables[0] )
		return false;

	$thread_ids = $_POST['thread_ids'];

	if ( !$thread_ids || !messages_check_thread_access($thread_ids) ) {
		bp_core_redirect( $bp->displayed_user->domain . $bp->current_component . '/' . $bp->current_action );
	} else {
		if ( !check_admin_referer( 'messages_delete_thread' ) )
			return false;

		if ( !messages_delete_thread( $thread_ids ) )
			bp_core_add_message( __('There was an error deleting messages.', 'buddypress'), 'error' );
		else
			bp_core_add_message( __('Messages deleted.', 'buddypress') );

		bp_core_redirect( $bp->loggedin_user->domain . $bp->current_component . '/' . $bp->current_action );
	}
}
add_action( 'bp_actions', 'messages_action_bulk_delete' );
?>