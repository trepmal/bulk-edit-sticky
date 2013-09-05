<?php
/*
 * Plugin Name: Bulk Edit Sticky
 * Plugin URI: trepmal.com
 * Description: Adds an ajaxy bulk edit option for sticky-fying posts.
 * Version:
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * TextDomain: bulk-edit-sticky
 * DomainPath:
 * Network:
 */

$bulk_edit_sticky = new Bulk_Edit_Sticky();

class Bulk_Edit_Sticky {

	function __construct() {
		if ( !is_admin() ) return;
		add_action( 'wp_ajax_bulk_sticky', array( &$this, 'bulk_sticky_cb' ) );
		add_action( 'admin_print_footer_scripts', array( &$this, 'admin_print_footer_scripts' ) );
	}

	function bulk_sticky_cb() {

		// should likely check for caps here.

		$post_ids = $_POST['ids'];
		$stickies = get_option( 'sticky_posts' );

		if ( 'sticky-replace' == $_POST['method'] ) {
			update_option( 'sticky_posts', $post_ids );
		} else if ( 'sticky-append' == $_POST['method'] ) {
			update_option( 'sticky_posts', array_merge( $stickies, $post_ids ) );
		} else if ( 'sticky-remove' == $_POST['method'] ) {
			update_option( 'sticky_posts', array_diff( $stickies, $post_ids ) );
		}

		$_changed_posts = array_merge( $post_ids, $stickies );
		$changed_posts = array();
		foreach( $_changed_posts as $k => $cp ) {
			ob_start();
			_post_states( get_post( $cp ) );
			$post_states = ob_get_clean();
			$changed_posts[ $cp ] = $post_states;
		}

		wp_send_json( compact( 'changed_posts' ) );
	}

	function admin_print_footer_scripts() {

		if ( get_current_screen()->id != 'edit-post' ) return;

		?><script>
jQuery(document).ready( function($) {
	// for simplicity, the bulk-sticky option is only in the upper <select> menu
	// var $bulk_selects = $('select[name="action"],select[name="action2"]');
	// var $bulk_buttons = $('#doaction,#doaction2');
	var $bulk_selects = $('select[name="action"]'),
		$bulk_buttons = $('#doaction'),
		make_sticky = '<?php _e( 'Make Sticky', 'bulk-edit-sticky' ); ?>',
		replace_stickies = '<?php _e( 'Replace current stickies', 'bulk-edit-sticky' ); ?>',
		append_stickies = '<?php _e( 'Add to stickies', 'bulk-edit-sticky' ); ?>',
		remove_stickies = '<?php _e( 'Remove from stickies', 'bulk-edit-sticky' ); ?>';

	$bulk_selects.append( '<option value="sticky" class="hide-if-no-js">'+ make_sticky +'</option>');
	$bulk_buttons.after( '<span class="sticky-buttons hidden">'+
		'<input type="submit" class="button" name="sticky-replace" value="'+ replace_stickies +'" />'+
		'<input type="submit" class="button" name="sticky-append" value="'+ append_stickies +'" />'+
		'<input type="submit" class="button" name="sticky-remove" value="'+ remove_stickies +'" />'+
		'</span>');
	$bulk_selects.change( function() {
		if ( $(this).val() == 'sticky' ) {
			$bulk_buttons.hide();
			$('.sticky-buttons').removeClass('hidden');
		} else {
			$bulk_buttons.show();
			$('.sticky-buttons').addClass('hidden');
		}
	});
	$('.sticky-buttons input').click( function(ev) {
		ev.preventDefault();
		// get checked posts
		var checked = new Array();
		$('input[name="post[]"]:checked').each(function() {
			checked.push( $(this).val() );
		})
		// post to ajax
		$.post( ajaxurl, {
			action: 'bulk_sticky',
			method: $(this).attr('name'),
			ids: checked
		}, function( response ) {

			// console.log( response );
			for( var ind in response.changed_posts ) {
				post_title = $('#post-'+ ind ).find( '.row-title');
				post_title.siblings( '.post-state').remove();
				// there's got to be a better way
				if ( post_title.get(0) && post_title.get(0).nextSibling && post_title.get(0).nextSibling.nodeValue == " - " )
					post_title.get(0).nextSibling.remove(); //removing hanging hyphen
				post_title.after( response.changed_posts[ind] );
			}

		},'json');
	});
});
		</script><?php
	}
}