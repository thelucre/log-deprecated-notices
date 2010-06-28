<?php
/**
 * @package Nacin_Deprecated
 */
/*
 * Plugin Name: Log Deprecated Notices
 * Plugin URI: http://wordpress.org/extend/plugins/log-deprecated-notices/
 * Description: Logs the usage of deprecated files, functions, and function arguments, offers the alternative if available, and identifies where the deprecated functionality is being used. WP_DEBUG not required (but its general use is strongly recommended).
 * Version: 0.1-beta-3
 * Author: Andrew Nacin
 * Author URI: http://andrewnacin.com/
 * License: GPLv2
 */

/**
 * Base class.
 *
 * @package Nacin_Deprecated
 *
 * @todo Finish i18n'ing
 * @todo Menu bubble letting you know you have notices you haven't looked at yet.
 * @todo Plugin ID. Also, notice on plugins page next to said plugin.
 */
class Nacin_Deprecated {

	/**
	 * Custom post type.
	 *
	 * @var string
	 */
	var $pt = 'nacin_deprecated';

	/**
	 * Constructor. Adds hooks.
	 */
	function Nacin_Deprecated() {
		// Bail without 3.0.
		if ( ! function_exists( '__return_false' ) )
			return;

		register_activation_hook( __FILE__, array( 'Nacin_Deprecated', 'on_activation' ) );

		add_action( 'init', array( &$this, 'action_init' ) );

		if ( WP_DEBUG ) {
			foreach ( array( 'function', 'file', 'argument' ) as $item )
				add_action( "deprecated_{$item}_trigger_error", '__return_false' );
		}

		add_action( 'deprecated_function_run',  array( &$this, 'log_function' ), 10, 3 );
		add_action( 'deprecated_file_included', array( &$this, 'log_file'     ), 10, 4 );
		add_action( 'deprecated_argument_run',  array( &$this, 'log_argument' ), 10, 4 );

		if ( ! is_admin() )
			return;

		add_action( 'admin_menu',                       array( &$this, 'action_admin_menu' ) );
		add_action( 'admin_print_styles',               array( &$this, 'action_admin_print_styles' ), 20 );
		add_action( 'manage_posts_custom_column',       array( &$this, 'action_manage_posts_custom_column' ), 10, 2 );
		add_filter( "manage_{$this->pt}_posts_columns", array( &$this, 'filter_manage_post_type_posts_columns' ) );
		add_action( 'restrict_manage_posts',            array( &$this, 'action_restrict_manage_posts' ) );
		add_action( 'load-edit.php',                    array( &$this, 'action_load_edit_php' ) );
	}

	/**
	 * Attached to deprecated_function_run action.
	 */
	function log_function( $function, $replacement, $version ) {
		$backtrace = debug_backtrace();
		$deprecated = $function . '()';
		$hook = null;
		$bt = 4;
		// Check if we're a hook callback.
		if ( ! isset( $backtrace[4]['file'] ) && 'call_user_func_array' == $backtrace[5]['function'] ) {
			$hook = $backtrace[6]['args'][0];
			$bt = 6;
		}
		$in_file = $this->strip_abspath( $backtrace[ $bt ]['file'] );
		$on_line = $backtrace[ $bt ]['line'];
		$this->log( 'function', compact( 'deprecated', 'replacement', 'version', 'hook', 'in_file', 'on_line'  ) );
	}

	/**
	 * Attached to deprecated_argument_run action.
	 */
	function log_argument( $function, $message, $version ) {
		$backtrace = debug_backtrace();
		$deprecated = $function . '()';
		$menu = $in_file = $on_line = null;
		// @todo [core] Introduce _deprecated_message() or something.
		switch ( $function ) {
			case 'options.php' :
				$deprecated = __( 'Unregistered Setting' );
				$this->log( 'functionality', compact( 'deprecated', 'message', 'version' ) );
				return;
			case 'has_cap' :
				if ( 0 === strpos( $backtrace[7]['function'], 'add_' ) && '_page' == substr( $backtrace[7]['function'], -5 ) ) {
					$bt = 7;
					if ( 0 === strpos( $backtrace[8]['function'], 'add_' ) && '_page' == substr( $backtrace[8]['function'], -5 ) )
						$bt = 8;
					$in_file = $this->strip_abspath( $backtrace[ $bt ]['file'] );
					$on_line = $backtrace[ $bt ]['line'];
					$deprecated = $backtrace[ $bt ]['function'] . '()';
				} elseif ( '_wp_menu_output' == $backtrace[7]['function'] ) {
					$deprecated = 'current_user_can()';
					$menu = true;
				} else {
					$in_file = $this->strip_abspath( $backtrace[6]['file'] );
					$on_line = $backtrace[6]['line'];
					$deprecated = 'current_user_can()';
				}
				break;
			case 'get_plugin_data' :
				$in_file = $this->strip_abspath( $backtrace[4]['args'][0] );
				break;
			case 'define()' :
			case 'define' :
				if ( 'ms_subdomain_constants' == $backtrace[4]['function'] ) {
					$deprecated = 'VHOST';
					$this->log( 'constant', compact( 'deprecated', 'message', 'menu', 'version' ) );
					return;
				}
				// Fall through.
			default :
				$in_file = $this->strip_abspath( $backtrace[4]['file'] );
				$on_line = $backtrace[4]['line'];
				break;
		}
		$this->log( 'argument', compact( 'deprecated', 'message', 'menu', 'version', 'in_file', 'on_line' ) );
	}

	/**
	 * Attached to deprecated_file_included action.
	 */
	function log_file( $file, $replacement, $version, $message ) {
		$backtrace = debug_backtrace();
		$deprecated = $this->strip_abspath( $backtrace[3]['file'] );
		$in_file = $this->strip_abspath( $backtrace[4]['file'] );
		$on_line = $backtrace[4]['line'];
		$this->log( 'file', compact( 'deprecated', 'replacement', 'message', 'version', 'in_file', 'on_line' ) );
	}

	/**
	 * Strip ABSPATH from an absolute filepath. Also, Windows is lame.
	 */
	function strip_abspath( $path ) {
		return ltrim( str_replace( array( untrailingslashit( ABSPATH, '/' ), '\\' ), array( '', '/' ), $path ), '/' );
	}

	/**
	 * Used to log deprecated usage.
	 *
	 * @todo Logging what I end up displaying is probably a bad idea.
	 */
	function log( $type, $args ) {
		global $wpdb;

		static $existing = null;
		if ( is_null( $existing ) )
			$existing = (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_name, ID FROM $wpdb->posts WHERE post_type = %s", $this->pt ), OBJECT_K );

		extract( $args );

		switch ( $type ) {
			case 'functionality' :
				$deprecated = sprintf( __( 'Functionality: %s' ), $deprecated );
				break;
			case 'constant' :
				$deprecated  = sprintf( __( 'Constant: %s' ), $deprecated );
				break;
			case 'function' :
				$deprecated = sprintf( __( 'Function: %s' ), $deprecated );
				break;
			case 'file' :
				$deprecated = sprintf( __( 'File: %s' ), $deprecated );
				break;
			case 'argument' :
				$deprecated = sprintf( __( 'Argument in %s' ), $deprecated );
				break;
		}

		$content = '';
		if ( ! empty( $replacement ) )
			$content = sprintf( __( 'Use %s instead.' ), $replacement );
		if ( ! empty( $message ) )
			$content .= ( strlen( $content ) ? ' ' : '' ) . (string) $message;
		if ( empty( $content ) )
			$content = __( 'No alternative available.' );
		$content .= "\n" . sprintf( __( 'Deprecated in version %s.' ), $version );

		if ( ! empty( $hook ) ) {
			$excerpt = sprintf( __( 'Attached to the %1$s hook, fired in %2$s on line %3$d.' ), $hook, $in_file, $on_line );
		} elseif ( ! empty( $menu ) ) {
			$excerpt = __( 'An admin menu page is using user levels instead of capabilities. There is likely a related log item with specifics.' );
		} elseif ( ! empty( $on_line ) ) {
			$excerpt = sprintf( __( 'Used in %1$s on line %2$d.' ), $in_file, $on_line );
		} elseif ( ! empty( $in_file ) ) {
			$excerpt = sprintf( __( 'Used in %1$s.' ), $in_file );
		} else {
			$excerpt = '';
		}

		$post_name = md5( $type . implode( $args ) );

		if ( ! isset( $existing[ $post_name ] ) ) {
			$post_id = wp_insert_post( array(
				'post_date' => current_time( 'mysql' ),
				'post_excerpt' => $excerpt,
				'post_type' => $this->pt,
				'post_title' => $deprecated,
				'post_content' => $content . "\n<!--more-->\n" . $excerpt, // searches
				'post_name' => $post_name,
			) );
			// For safe keeping.
			update_post_meta( $post_id, '_nacin_deprecated_meta', array_merge( array( 'type' => $type ), $args ) );
			$existing[ $post_name ] = $post_id;
		} else {
			$post_id = $existing[ $post_name ]->ID;
		}
		// Update comment_count.
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET comment_count = comment_count + 1, post_date = %s WHERE ID = %d", current_time( 'mysql' ), $post_id ) );
	}

	/**
	 * Attached to manage_posts_custom_column action.
	 */
	function action_manage_posts_custom_column( $col, $post_id ) {
		switch ( $col ) {
			case 'deprecated_title' :
				// We just want a 'Delete' link. Trash here is excessive.
				// @todo [core] Custom post types should be able to disable trash.
				$post = get_post( $post_id );
				echo '<strong>' . esc_html( $post->post_title ) . '</strong>';
				echo '<br/>' . esc_html( $post->post_excerpt );
				echo '<div class="row-actions"><span class="delete"><a class="submitdelete" title="' . esc_attr( __('Delete') ) . '" href="' . get_delete_post_link( $post_id, '', true ) . '">' . __( 'Delete' ) . '</a></span></div>';
				break;
			case 'deprecated_count' :
				$post = get_post( $post_id );
				$count = $post->comment_count ? $post->comment_count : 1; // Caching. Don't want 0
				echo number_format_i18n( $count );
				break;
			case 'deprecated_modified' :
				echo get_the_date( __('Y/m/d g:i:s A' ) );
				break;
			case 'deprecated_version' :
				$meta = get_post_meta( $post_id, '_nacin_deprecated_meta', true );
				echo $meta['version'];
				break;
			case 'deprecated_alternative':
				$post = get_post( $post_id );
				echo nl2br( preg_replace( '/<!--more(.*?)?-->(.*)/s', '', $post->post_content ) );
				break;
		}
	}

	/**
	 * Attached to manage_{post_type}_posts_columns filter.
	 *
	 * @todo Is a separate version column desirable?
	 * @todo Checkbox for bulk deletes. Would need JS to remove bulk edit.
	 * @todo Filter on type (file/function/argument), filter on specific function etc.
	 */
	function filter_manage_post_type_posts_columns( $cols ) {
		// @todo Can't use cb as it expects edit_post and doesn't check delete_post.
		$cols = array(
			'deprecated_title' => __('Deprecated Call'),
		//	'deprecated_version' => __('Version'),
			'deprecated_alternative' => __('Alternative'),
			'deprecated_count' => __('Count'),
			'deprecated_modified' => __('Last Used'),
		);
		return $cols;
	}

	/**
	 * Prints basic CSS. Also an unrelated cheap hack.
	 */
	function action_admin_print_styles() {
		global $current_screen;
		if ( 'edit-' . $this->pt != $current_screen->id )
			return;

		// Hides 'Mine (X)', though I hide those via CSS anyway. Again, cheap hack.
		$GLOBALS['user_posts'] = false;

		// Hides Add New button, bulk actions, sets some column widths, uses the plugins screen icon.
		// @todo Should probably actually disable new posts.
	?>
<style type="text/css">
.add-new-h2, .view-switch, .subsubsub, .tablenav select[name^=action], #doaction, #doaction2 { display: none }
.widefat .column-deprecated_modified, .widefat .column-deprecated_version { width: 10%; }
.widefat .column-deprecated_count { width: 10%; text-align: right }
.widefat .column-deprecated_cb { padding: 0; width: 2.2em }
#icon-edit { background-position: -432px -5px; }
</style>
	<?php
	}

	/**
	 * Cheap hack to show a 'Clear Log' button.
	 * Somehow, there is not a decent hook anywhere on edit.php (but there is for edit-comments.php).
	 */
	function action_restrict_manage_posts() {
		$GLOBALS['is_trash'] = true;
		add_filter( 'gettext', array( &$this, 'filter_gettext_empty_trash' ), 10, 2 );
	}

	/**
	 * Modifies 'Empty Trash' to 'Clear Log'.
	 */
	function filter_gettext_empty_trash( $translation, $text ) {
		if ( 'Empty Trash' == $text ) {
			remove_filter( 'gettext', array( &$this, 'filter_gettext_empty_trash' ), 10, 2 );
			$GLOBALS['is_trash'] = false;
			return __( 'Clear Log' );
		}
		return $translation;
	}

	/**
	 * Cheap hack so 'Empty Trash' works.
	 */
	function action_load_edit_php() {
		global $current_screen;
		if ( 'edit-' . $this->pt != $current_screen->id )
			return;
		if ( isset( $_GET['delete_all'] ) || isset( $_GET['delete_all2'] ) )
			$_GET['post_status'] = 'draft';
	}

	/**
	 * Cheap hack to make my show_ui post type a submenu.
	 *
	 * Should hypothetically be forwards compatible.
	 */
	function action_admin_menu() {
		global $menu, $submenu;
		unset( $menu[2048] );
		add_submenu_page( 'tools.php', __( 'Deprecated Calls' ), __( 'Deprecated Calls' ), 'activate_plugins', 'edit.php?post_type=' . $this->pt );
	}

	/**
	 * Registers the custom post type.
	 */
	function action_init() {
		register_post_type( $this->pt, array(
			'labels' => array(
				'name' => __( 'Deprecated Calls' ),
				'singular_name' => __( 'Deprecated Call' ),
				// add_new, add_new_item, edit_item, new_item, view_item, not_found_in_trash
				'search_items' => __( 'Search Logs' ),
				'not_found' => __( 'Nothing in the log! Your plugins are oh so fine.' ),
			),
			'menu_position' => 2048, // cheap hack so I know exactly where it is (hopefully).
			'show_ui' => true,
			'public' => false,
			'capabilities' => array(
				'edit_post'          => 'do_not_allow',
				'edit_posts'         => 'activate_plugins',
				'edit_others_posts'  => 'activate_plugins',
				'publish_posts'      => 'do_not_allow',
				'read_post'          => 'activate_plugins',
				'read_private_posts' => 'do_not_allow',
				'delete_post'        => 'activate_plugins',
			),
			'rewrite'      => false,
			'query_var'    => false,
		) );
	}

	/**
	 * Runs on activation. Simply registers the uninstall routine.
	 */
	function on_activation() {
		register_uninstall_hook( __FILE__, array( 'Nacin_Deprecated', 'on_uninstall' ) );
	}

	/**
	 * Runs on uninstall. Removes all log data.
	 */
	function on_uninstall() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'nacin_deprecated'" );
	}
}
/** Initialize. */
$GLOBALS['nacin_deprecated'] = new Nacin_Deprecated;