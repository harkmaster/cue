<?php

/**
 * BuddyPress - Users Forums
 *
 * @package BuddyPress
 * @subpackage bp-default
 */

?>

<div class="item-list-tabs no-ajax" id="subnav" role="navigation">
	<ul>
		<?php bp_get_options_nav() ?>

		<li id="forums-order-select" class="last filter">

			<?php _e( 'Order By:', 'buddypress' ); ?>

			<select>
				<option value="active"><?php _e( 'Last Active', 'buddypress' ); ?></option>
				<option value="popular"><?php _e( 'Most Posts', 'buddypress' ); ?></option>
				<option value="unreplied"><?php _e( 'Unreplied', 'buddypress' ); ?></option>

				<?php do_action( 'bp_forums_directory_order_options' ); ?>

			</select>
		</li>
	</ul>
</div><!-- .item-list-tabs -->

<?php

if ( bp_is_current_action( 'favorites' ) ) :
	locate_template( array( 'members/single/forums/topics.php' ), true );

else :
	do_action( 'bp_before_member_forums_content' ); ?>

	<div class="groups mygroups">

		<?php locate_template( array( 'forums/forums-loop.php' ), true ); ?>

	</div>

	<?php do_action( 'bp_after_member_forums_content' ); ?>

<?php endif; ?>
