<?php
/*
Based on contributions from: Chris Taylor - http://www.stillbreathing.co.uk/
Modified for BuddyPress by: Andy Peatling - http://apeatling.wordpress.com/
*/

/**
 * Analyzes the URI structure and breaks it down into parts for use in code.
 * The idea is that BuddyPress can use complete custom friendly URI's without the
 * user having to add new re-write rules.
 *
 * Future custom components would then be able to use their own custom URI structure.
 *
 * @package BuddyPress Core
 * @since BuddyPress (r100)
 *
 * The URI's are broken down as follows:
 *   - http:// domain.com / members / andy / [current_component] / [current_action] / [action_variables] / [action_variables] / ...
 *   - OUTSIDE ROOT: http:// domain.com / sites / buddypress / members / andy / [current_component] / [current_action] / [action_variables] / [action_variables] / ...
 *
 *	Example:
 *    - http://domain.com/members/andy/profile/edit/group/5/
 *    - $bp->current_component: string 'xprofile'
 *    - $bp->current_action: string 'edit'
 *    - $bp->action_variables: array ['group', 5]
 *
 */
function bp_core_set_uri_globals() {
	global $bp, $bp_unfiltered_uri, $bp_unfiltered_uri_offset;
	global $current_blog, $wpdb;

	// Create global component, action, and item variables
	$bp->current_component = $bp->current_action = $bp->current_item ='';
	$bp->action_variables = $bp->displayed_user->id = '';

	// Only catch URI's on the root blog if we are not running
	// on multiple blogs
	if ( !defined( 'BP_ENABLE_MULTIBLOG' ) && is_multisite() ) {
		if ( BP_ROOT_BLOG != (int) $wpdb->blogid )
			return false;
	}

	// Fetch all the WP page names for each component
	if ( empty( $bp->pages ) )
		$bp->pages = bp_core_get_page_names();

	// Ajax or not?
	if ( strpos( $_SERVER['REQUEST_URI'], 'wp-load.php' ) )
		$path = bp_core_referrer();
	else
		$path = esc_url( $_SERVER['REQUEST_URI'] );

	// Filter the path
	$path = apply_filters( 'bp_uri', $path );

	// Take GET variables off the URL to avoid problems,
	// they are still registered in the global $_GET variable
	if ( $noget = substr( $path, 0, strpos( $path, '?' ) ) )
		$path = $noget;

	// Fetch the current URI and explode each part separated by '/' into an array
	$bp_uri = explode( '/', $path );

	// Loop and remove empties
	foreach ( (array)$bp_uri as $key => $uri_chunk )
		if ( empty( $bp_uri[$key] ) ) unset( $bp_uri[$key] );

	// Running off blog other than root
	if ( defined( 'BP_ENABLE_MULTIBLOG' ) || 1 != BP_ROOT_BLOG ) {

		// Any subdirectory names must be removed from $bp_uri.
		// This includes two cases: (1) when WP is installed in a subdirectory,
		// and (2) when BP is running on secondary blog of a subdirectory
		// multisite installation. Phew!
		if ( $chunks = explode( '/', $current_blog->path ) ) {
			foreach( $chunks as $key => $chunk ) {
				$bkey = array_search( $chunk, $bp_uri );

				if ( $bkey !== false )
					unset( $bp_uri[$bkey] );

				$bp_uri = array_values( $bp_uri );
			}
		}
	}

	// Set the indexes, these are incresed by one if we are not on a VHOST install
	$component_index = 0;
	$action_index    = $component_index + 1;

	// Get site path items
	$paths = explode( '/', bp_core_get_site_path() );

	// Take empties off the end of path
	if ( empty( $paths[count( $paths ) - 1] ) )
		array_pop( $paths );

	// Take empties off the start of path
	if ( empty( $paths[0] ) )
		array_shift( $paths );

	// Unset URI indices if they intersect with the paths
	foreach ( (array) $bp_uri as $key => $uri_chunk ) {
		if ( in_array( $uri_chunk, $paths ) ) {
			unset( $bp_uri[$key] );
		}
	}

	// Reset the keys by merging with an empty array
	$bp_uri = array_merge( array(), $bp_uri );

	// If a component is set to the front page, force its name into $bp_uri
	// so that $current_component is populated
	if ( 'page' == get_option( 'show_on_front' ) && get_option( 'page_on_front' ) && empty( $bp_uri ) ) {
		$post = get_post( get_option( 'page_on_front' ) );
		if ( !empty( $post ) )
			$bp_uri[0] = $post->post_name;
	}

	// Keep the unfiltered URI safe
	$bp_unfiltered_uri = $bp_uri;

	// Get slugs of pages into array
	foreach ( (array) $bp->pages as $page_key => $bp_page )
		$key_slugs[$page_key] = trailingslashit( '/' . $bp_page->slug );

	// Bail if keyslugs are empty, as BP is not setup correct
	if ( empty( $key_slugs ) )
		return;

	// Loop through page slugs and look for exact match to path
	foreach ( $key_slugs as $key => $slug ) {
		if ( $slug == $path ) {
			$match      = $bp->pages->{$key};
			$match->key = $key;
			$matches[]  = 1;
			break;
		}
	}

	// No exact match, so look for partials
	if ( empty( $match ) ) {

		// Loop through each page in the $bp->pages global
		foreach ( (array) $bp->pages as $page_key => $bp_page ) {

			// Look for a match (check members first)
			if ( in_array( $bp_page->name, (array) $bp_uri ) ) {

				// Match found, now match the slug to make sure.
				$uri_chunks = explode( '/', $bp_page->slug );

				// Loop through uri_chunks
				foreach ( (array) $uri_chunks as $key => $uri_chunk ) {

					// Make sure chunk is in the correct position
					if ( !empty( $bp_uri[$key] ) && ( $bp_uri[$key] == $uri_chunk ) ) {
						$matches[] = 1;

					// No match
					} else {
						$matches[] = 0;
					}
				}

				// Have a match
				if ( !in_array( 0, (array) $matches ) ) {
					$match      = $bp_page;
					$match->key = $page_key;
					break;
				};

				// Unset matches
				unset( $matches );
			}

			// Unset uri chunks
			unset( $uri_chunks );
		}
	}
	
	// URLs with BP_ENABLE_ROOT_PROFILES enabled won't be caught above 
	if ( empty( $matches ) && defined( 'BP_ENABLE_ROOT_PROFILES' ) && BP_ENABLE_ROOT_PROFILES ) {
		
		// Make sure there's a user corresponding to $bp_uri[0]
		if ( !empty( $bp_uri[0] ) && $root_profile = get_userdatabylogin( $bp_uri[0] ) ) {
			
			// Force BP to recognize that this is a members page
			$matches[]  = 1;
			$match      = $bp->pages->members;
			$match->key = 'members';
			
			// Without the 'members' URL chunk, WordPress won't know which page to load
			// This filter intercepts the WP query and tells it to load the members page
			add_filter( 'request', create_function( '$query_args', '$query_args["pagename"] = "' . $match->name . '"; return $query_args;' ) );
		
		}
	
	}

	// Search doesn't have an associated page, so we check for it separately
	if ( !empty( $bp_uri[0] ) && ( BP_SEARCH_SLUG == $bp_uri[0] ) ) {
		$matches[]   = 1;
		$match       = new stdClass;
		$match->key  = 'search';
		$match->slug = BP_SEARCH_SLUG;
	}

	// This is not a BuddyPress page, so just return.
	if ( !isset( $matches ) )
		return false;

	// Find the offset. With $root_profile set, we fudge the offset down so later parsing works
	$slug       = !empty ( $match ) ? explode( '/', $match->slug ) : '';
	$uri_offset = empty( $root_profile ) ? 0 : -1;

	// Rejig the offset
	if ( !empty( $slug ) && ( 1 < count( $slug ) ) ) {
		array_pop( $slug );
		$uri_offset = count( $slug );
	}

	// Global the unfiltered offset to use in bp_core_load_template().
	// To avoid PHP warnings in bp_core_load_template(), it must always be >= 0 
	$bp_unfiltered_uri_offset = $uri_offset >= 0 ? $uri_offset : 0;

	// We have an exact match
	if ( isset( $match->key ) ) {

		// Set current component to matched key
		$bp->current_component = $match->key;

		// If members component, do more work to find the actual component
		if ( 'members' == $match->key ) {

			// Viewing a specific user
			if ( !empty( $bp_uri[$uri_offset + 1] ) ) {
				
				// Switch the displayed_user based on compatbility mode
				if ( defined( 'BP_ENABLE_USERNAME_COMPATIBILITY_MODE' ) )
					$bp->displayed_user->id = (int) bp_core_get_userid( urldecode( $bp_uri[$uri_offset + 1] ) );
				else
					$bp->displayed_user->id = (int) bp_core_get_userid_from_nicename( urldecode( $bp_uri[$uri_offset + 1] ) );

				// Bump the offset
				if ( isset( $bp_uri[$uri_offset + 2] ) ) {
					$bp_uri                = array_merge( array(), array_slice( $bp_uri, $uri_offset + 2 ) );
					$bp->current_component = $bp_uri[0];

				// No component, so default will be picked later
				} else {
					$bp_uri                = array_merge( array(), array_slice( $bp_uri, $uri_offset + 2 ) );
					$bp->current_component = '';
				}
				
				// Reset the offset
				$uri_offset = 0;
			}
		}
	}

	// Set the current action
	$bp->current_action = isset( $bp_uri[$uri_offset + 1] ) ? $bp_uri[$uri_offset + 1] : '';

	// Slice the rest of the $bp_uri array and reset offset
	$bp_uri      = array_slice( $bp_uri, $uri_offset + 2 );
	$uri_offset  = 0;

	// Set the entire URI as the action variables, we will unset the current_component and action in a second
	$bp->action_variables = $bp_uri;

	// Remove the username from action variables if this is not a VHOST install
	if ( defined( 'VHOST' ) && 'no' == VHOST && empty( $is_root_component ) )
		array_shift( $bp_uri );

	// Reset the keys by merging with an empty array
	$bp->action_variables = array_merge( array(), $bp->action_variables );
}

/**
 * bp_core_load_template()
 *
 * Load a specific template file with fallback support.
 *
 * Example:
 *   bp_core_load_template( 'members/index' );
 * Loads:
 *   wp-content/themes/[activated_theme]/members/index.php
 *
 * @package BuddyPress Core
 * @param $username str Username to check.
 * @return false|int The user ID of the matched user, or false.
 */
function bp_core_load_template( $templates ) {
	global $post, $bp, $wpdb, $wp_query, $bp_unfiltered_uri, $bp_unfiltered_uri_offset;

	// Determine if the root object WP page exists for this request (TODO: is there an API function for this?
	if ( !empty( $bp_unfiltered_uri_offset ) && !$page_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s", $bp_unfiltered_uri[$bp_unfiltered_uri_offset] ) ) )
		return false;

	// Set the root object as the current wp_query-ied item
	$object_id = 0;
	foreach ( (array)$bp->pages as $page ) {
		if ( isset( $bp_unfiltered_uri[$bp_unfiltered_uri_offset] ) && $page->name == $bp_unfiltered_uri[$bp_unfiltered_uri_offset] )
			$object_id = $page->id;
	}

	// Make the queried/post object an actual valid page
	if ( !empty( $object_id ) ) {
		$wp_query->queried_object    = &get_post( $object_id );
		$wp_query->queried_object_id = $object_id;
		$post                        = $wp_query->queried_object;
	}

	// Fetch each template and add the php suffix
	foreach ( (array)$templates as $template )
		$filtered_templates[] = $template . '.php';

	// Filter the template locations so that plugins can alter where they are located
	if ( $located_template = apply_filters( 'bp_located_template', locate_template( (array) $filtered_templates, false ), $filtered_templates ) ) {
		// Template was located, lets set this as a valid page and not a 404.
		status_header( 200 );
		$wp_query->is_page = true;
		$wp_query->is_404 = false;

		load_template( apply_filters( 'bp_load_template', $located_template ) );
	}

	// Kill any other output after this.
	die;
}

/**
 * bp_core_catch_profile_uri()
 *
 * If the extended profiles component is not installed we still need
 * to catch the /profile URI's and display whatever we have installed.
 *
 */
function bp_core_catch_profile_uri() {
	global $bp;

	if ( !bp_is_active( 'xprofile' ) )
		bp_core_load_template( apply_filters( 'bp_core_template_display_profile', 'members/single/home' ) );
}

?>