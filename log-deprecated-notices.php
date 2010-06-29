<?php
/**
 * @package Deprecated_Log
 */
/*
 * Plugin Name: Log Deprecated Notices
 * Plugin URI: http://wordpress.org/extend/plugins/log-deprecated-notices/
 * Description: Logs the usage of deprecated files, functions, and function arguments, offers the alternative if available, and identifies where the deprecated functionality is being used. WP_DEBUG not required (but its general use is strongly recommended).
 * Version: 0.1-beta-6
 * Author: Andrew Nacin
 * Author URI: http://andrewnacin.com/
 * License: GPLv2
 */

if ( ! class_exists( 'Deprecated_Log' ) ) :

/**
 * Base class.
 *
 * @package Deprecated_Log
 *
 * @todo Plugin ID. Also, notice on plugins page next to said plugin.
 */
class Deprecated_Log {

	/**
	 * DB version.
	 *
	 * @var int
	 */
	var $db_version = 2;

	/**
	 * Options.
	 *
	 * @var array
	 */
	var $options = array();

	/**
	 * Option name in DB.
	 *
	 * @var string
	 */
	var $option_name = 'log_deprecated_notices';

	/**
	 * Custom post type.
	 *
	 * @var string
	 */
	var $pt = 'deprecated_log';

	/**
	 * Constructor. Adds hooks.
	 */
	function Deprecated_Log() {
		// Bail without 3.0.
		if ( ! function_exists( '__return_false' ) )
			return;

		// Registers the uninstall hook.
		register_activation_hook( __FILE__, array( 'Deprecated_Log', 'on_activation' ) );

		// Registers post type.
		add_action( 'init', array( &$this, 'action_init' ) );

		// Silence E_NOTICE for deprecated usage.
		if ( WP_DEBUG ) {
			foreach ( array( 'function', 'file', 'argument' ) as $item )
				add_action( "deprecated_{$item}_trigger_error", '__return_false' );
		}

		// Log deprecated notices.
		add_action( 'deprecated_function_run',  array( &$this, 'log_function' ), 10, 3 );
		add_action( 'deprecated_file_included', array( &$this, 'log_file'     ), 10, 4 );
		add_action( 'deprecated_argument_run',  array( &$this, 'log_argument' ), 10, 4 );

		if ( ! is_admin() )
			return;

		$this->options = get_option( $this->option_name );

		// Textdomain and upgrade routine.
		add_action( 'admin_init',                       array( &$this, 'action_admin_init' ) );
		// Move post type menu to submenu.
		add_action( 'admin_menu',                       array( &$this, 'action_admin_menu' ) );
		// Basic CSS.
		add_action( 'admin_print_styles',               array( &$this, 'action_admin_print_styles' ), 20 );
		// Column handling.
		add_action( 'manage_posts_custom_column',       array( &$this, 'action_manage_posts_custom_column' ), 10, 2 );
		// Column headers.
		add_filter( "manage_{$this->pt}_posts_columns", array( &$this, 'filter_manage_post_type_posts_columns' ) );
		// Filters and 'Clear Log'.
		add_action( 'restrict_manage_posts',            array( &$this, 'action_restrict_manage_posts' ) );
		// Basic JS (changes Bulk Actions options).
		add_action( 'admin_footer-edit.php',            array( &$this, 'action_admin_footer_edit_php' ) );
		// Permissions handling, also make 'Clear Log' work.
		foreach ( array( 'edit.php', 'post.php', 'post-new.php' ) as $item )
			add_action( "load-{$item}",                 array( &$this, 'action_load_edit_php' ) );
	}

	/**
	 * Attached to admin_init. Loads the textdomain and the upgrade routine.
	 */
	function action_admin_init() {
		if ( false === $this->options || ! isset( $this->options['db_version'] ) || $this->options['db_version'] < $this->db_version ) {
			if ( ! is_array( $this->options ) )
				$this->options = array();
			$current_db_version = isset( $this->options['db_version'] ) ? $this->options['db_version'] : 0;
			$this->upgrade( $current_db_version );
			$this->options['db_version'] = $this->db_version;
			update_option( $this->option_name, $this->options );
		}
		load_plugin_textdomain('log-deprecated', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Upgrade routine.
	 */
	function upgrade( $current_db_version ) {
		global $wpdb;
		if ( $current_db_version < 1 ) {
			$wpdb->update( $wpdb->posts, array( 'post_type' => 'deprecated_log' ), array( 'post_type' => 'nacin_deprecated' ) );
			$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_deprecated_log_meta' ), array( 'meta_key' => '_nacin_deprecated_meta' ) );
			$this->options['last_viewed'] = current_time( 'mysql' );
		}
		if ( $current_db_version < 2 )
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'post_type' => 'deprecated_log' ) );
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
				$deprecated = __( 'Unregistered Setting', 'log-deprecated' );
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
		return ltrim( str_replace( array( untrailingslashit( ABSPATH ), '\\' ), array( '', '/' ), $path ), '/' );
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
				$deprecated = sprintf( __( 'Functionality: %s', 'log-deprecated' ), $deprecated );
				break;
			case 'constant' :
				$deprecated  = sprintf( __( 'Constant: %s', 'log-deprecated' ), $deprecated );
				break;
			case 'function' :
				$deprecated = sprintf( __( 'Function: %s', 'log-deprecated' ), $deprecated );
				break;
			case 'file' :
				$deprecated = sprintf( __( 'File: %s', 'log-deprecated' ), $deprecated );
				break;
			case 'argument' :
				$deprecated = sprintf( __( 'Argument in %s', 'log-deprecated' ), $deprecated );
				break;
		}

		$content = '';
		if ( ! empty( $replacement ) )
			// translators: %s is name of function.
			$content = sprintf( __( 'Use %s instead.', 'log-deprecated' ), $replacement );
		if ( ! empty( $message ) )
			$content .= ( strlen( $content ) ? ' ' : '' ) . (string) $message;
		if ( empty( $content ) )
			$content = __( 'No alternative available.', 'log-deprecated' );
		$content .= "\n" . sprintf( __( 'Deprecated in version %s.', 'log-deprecated' ), $version );

		if ( ! empty( $hook ) ) {
			$excerpt = sprintf( __( 'Attached to the %1$s hook, fired in %2$s on line %3$d.', 'log-deprecated' ), $hook, $in_file, $on_line );
		} elseif ( ! empty( $menu ) ) {
			$excerpt = __( 'An admin menu page is using user levels instead of capabilities. There is likely a related log item with specifics.', 'log-deprecated' );
		} elseif ( ! empty( $on_line ) ) {
			$excerpt = sprintf( __( 'Used in %1$s on line %2$d.', 'log-deprecated' ), $in_file, $on_line );
		} elseif ( ! empty( $in_file ) ) {
			// translators: %s is file name.
			$excerpt = sprintf( __( 'Used in %s.', 'log-deprecated' ), $in_file );
		} else {
			$excerpt = '';
		}

		$post_name = md5( $type . implode( $args ) );

		if ( ! isset( $existing[ $post_name ] ) ) {
			$post_id = wp_insert_post( array(
				'post_date'    => current_time( 'mysql' ),
				'post_excerpt' => $excerpt,
				'post_type'    => $this->pt,
				'post_status'  => 'publish',
				'post_title'   => $deprecated,
				'post_content' => $content . "\n<!--more-->\n" . $excerpt, // searches
				'post_name'    => $post_name,
			) );
			// For safe keeping.
			update_post_meta( $post_id, '_deprecated_log_meta', array_merge( array( 'type' => $type ), $args ) );
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
				$post_type_object = get_post_type_object( $post->post_type );
				echo '<strong>' . esc_html( $post->post_title ) . '</strong>';
				echo '<br/>' . esc_html( $post->post_excerpt );
				echo '<div class="row-actions">';
				if ( $GLOBALS['is_trash'] )
					echo "<span class='untrash'><a title='" . esc_attr__( 'Unmute', 'log-deprecated' ) . "' href='" . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post_id ) ), 'untrash-' . $post->post_type . '_' . $post_id ) . "'>" . __( 'Unmute', 'log-deprecated' ) . '</a></span> | ';
				else
					echo "<span class='mute'><a class='submitdelete' title='" . esc_attr__( 'Mute', 'log-deprecated' ) . "' href='" . get_delete_post_link($post->ID) . "'>" . __( 'Mute', 'log-deprecated' ) . '</a></span> | ';
				echo '<span class="delete"><a class="submitdelete" title="' . esc_attr__( 'Delete', 'log-deprecated' ) . '" href="' . get_delete_post_link( $post_id, '', true ) . '">' . __( 'Delete', 'log-deprecated' ) . '</a></span></div>';
				break;
			case 'deprecated_count' :
				$post = get_post( $post_id );
				$count = $post->comment_count ? $post->comment_count : 1; // Caching. Don't want 0
				echo number_format_i18n( $count );
				break;
			case 'deprecated_modified' :
				echo get_the_date( __('Y/m/d g:i:s A', 'log-deprecated' ) );
				break;
			case 'deprecated_version' :
				$meta = get_post_meta( $post_id, '_deprecated_log_meta', true );
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
	 * @todo Filter on type (file/function/argument), filter on specific function etc.
	 */
	function filter_manage_post_type_posts_columns( $cols ) {
		$cols = array(
			'cb' => '<input type="checkbox" />',
			'deprecated_title'       => __( 'Deprecated Call', 'log-deprecated' ),
			'deprecated_version'     => __( 'Version',         'log-deprecated' ),
			'deprecated_alternative' => __( 'Alternative',     'log-deprecated' ),
			'deprecated_count'       => __( 'Count',           'log-deprecated' ),
			'deprecated_modified'    => __( 'Last Used',       'log-deprecated' ),
		);
		unset( $cols['deprecated_version'] ); // Get it translated.
		return $cols;
	}

	/**
	 * Prints basic CSS.
	 */
	function action_admin_print_styles() {
		global $current_screen;
		if ( 'edit-' . $this->pt != $current_screen->id )
			return;

		// Hides Add New button, bulk actions, sets some column widths, uses the plugins screen icon.
	?>
<style type="text/css">
.add-new-h2, .view-switch, /* .subsubsub, */
body.no-js .tablenav select[name^=action], body.no-js #doaction, body.no-js #doaction2 { display: none }
.widefat .column-deprecated_modified, .widefat .column-deprecated_version { width: 10%; }
.widefat .column-deprecated_count { width: 10%; text-align: right }
.widefat .column-deprecated_cb { padding: 0; width: 2.2em }
#icon-edit { background-position: -432px -5px; }
</style>
	<?php
	}

	/**
	 * Basic JS -- changes Bulk Action options.
	 */
	function action_admin_footer_edit_php() {
		global $current_screen;
		if ( 'edit-' . $this->pt != $current_screen->id )
			return;
?>
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready( function($) {
	var s = $('div.actions select[name^=action]');
	s.find('option[value=edit], option[value=delete]').remove();
	s.find('option[value=trash]').text('<?php echo addslashes( __( 'Mute', 'log-deprecated' ) ); ?>');
	s.find('option[value=untrash]').text('<?php echo addslashes( __( 'Unmute', 'log-deprecated' ) ); ?>');
	s.append('<option value="delete"><?php echo addslashes( __( 'Delete', 'log-deprecated' ) ); ?></option>');
});
//]]>
</script>
<?php
	}

	/**
	 * Cheap hack to show a 'Clear Log' button.
	 * Somehow, there is not a decent hook anywhere on edit.php (but there is for edit-comments.php).
	 */
	function action_restrict_manage_posts() {
		$this->_is_trash = $GLOBALS['is_trash'];
		$GLOBALS['is_trash'] = true;
		add_filter( 'gettext', array( &$this, 'filter_gettext_empty_trash' ), 10, 2 );
	}

	/**
	 * Modifies 'Empty Trash' to 'Clear Log'.
	 */
	function filter_gettext_empty_trash( $translation, $text ) {
		if ( 'Empty Trash' == $text ) {
			remove_filter( 'gettext', array( &$this, 'filter_gettext_empty_trash' ), 10, 2 );
			$GLOBALS['is_trash'] = $this->_is_trash;
			return __( 'Clear Log', 'log-deprecated' );
		}
		return $translation;
	}

	function filter_ngettext( $translation, $single, $plural, $number ) {
		switch ( $single ) {
			case 'All <span class="count">(%s)</span>' :
				return _n( 'Log <span class="count">(%s)</span>', 'Log <span class="count">(%s)</span>', $number, 'log-deprecated' );
			case 'Trash <span class="count">(%s)</span>' :
				return _n( 'Muted <span class="count">(%s)</span>', 'Muted <span class="count">(%s)</span>', $number, 'log-deprecated' );
			case 'Item moved to the trash.' : // 3.0
			case 'Item moved to the Trash.' : // 3.1
				return _n( 'Entry muted.', 'Entries muted.', $number, 'log-deprecated' );
			case 'Item permanently deleted.' :
				return _n( 'Entry deleted.', 'Entries deleted.', $number, 'log-deprecated' );
			case 'Item restored from the Trash.' :
				return _n ( 'Entry unmuted.', 'Entries unmuted.', $number, 'log-deprecated' );
		}
		return $translation;
	}

	/**
	 * Cheap hacks when we're in the post type UI.
	 *
	 * First, it locks out post.php and post-new.php, since our permissions
	 * don't cover that.
	 *
	 * Then it gets our 'Clear Log' button to work on the 'All' page, as it
	 * uses the 'Empty Trash' functionality and requires a post status to work
	 * off of, not the 'all' status.
	 *
	 * We're using 'All' because we can't remove that, but by directly modifying
	 * the show_in_admin_status_list property of the publish post status (also in
	 * this function), we can hide that one.
	 *
	 * This function also sets the last_viewed option, for the unread menu bubble.
	 *
	 * This function adds a filter to _n() and _nx() to change the text of status links.
	 *
	 * @todo [core] Filter on $status_links in edit.php
	 * @todo [core] Custom post stati should have granular properties per post type.
	 */
	function action_load_edit_php() {
		global $current_screen;
		if ( 'edit-' . $this->pt != $current_screen->id ) {
			if ( $this->pt == $current_screen->id )
				wp_die( __( 'Invalid post type.', 'log-deprecated' ) );
			return;
		}

		if ( ( empty( $_GET['post_status'] ) || 'all' == $_GET['post_status'] )
			&& ( isset( $_GET['delete_all'] ) || isset( $_GET['delete_all2'] ) )
		)
			$_GET['post_status'] = 'publish';

		$this->options['last_viewed'] = current_time('mysql');
		update_option( $this->option_name, $this->options );

		global $wp_post_statuses;
		// You know where this is going.
		$wp_post_statuses['publish']->show_in_admin_status_list = false;

		foreach ( array( 'ngettext', 'ngettext_with_context' ) as $filter )
			add_filter( $filter, array( &$this, 'filter_ngettext' ), 10, 4 );
	}

	/**
	 * Cheap hack to make my show_ui post type a submenu.
	 *
	 * Should hypothetically be forwards compatible.
	 */
	function action_admin_menu() {
		global $menu, $submenu, $typenow, $wpdb;
		unset( $menu[2048] );

		$page_title = $label = __( 'Deprecated Calls', 'log-deprecated' );
		if ( $this->pt != $typenow && $this->options && ! empty( $this->options['last_viewed'] ) ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = %s AND post_date > %s AND post_status = %s", $this->pt, $this->options['last_viewed'], 'publish' ) );
			if ( $count )
				$label = sprintf( __( 'Deprecated Calls %s', 'log-deprecated' ), "<span class='update-plugins count-$count'><span class='update-count'>" . number_format_i18n( $count ) . '</span></span>' );
		}

		add_submenu_page( 'tools.php', $page_title, $label, 'activate_plugins', 'edit.php?post_type=' . $this->pt );
	}

	/**
	 * Registers the custom post type.
	 */
	function action_init() {
		register_post_type( $this->pt, array(
			'labels' => array(
				'name' => __( 'Deprecated Calls', 'log-deprecated' ),
				'singular_name' => __( 'Deprecated Call', 'log-deprecated' ),
				// add_new, add_new_item, edit_item, new_item, view_item
				'search_items' => __( 'Search Logs', 'log-deprecated' ),
				'not_found' => __( 'Nothing in the log! Your plugins are oh so fine.', 'log-deprecated' ),
				'not_found_in_trash' => __( 'Nothing muted.', 'log-deprecated' ),
			),
			'menu_position' => 2048, // cheap hack so I know exactly where it is (hopefully).
			'show_ui' => true,
			'public' => false,
			'capabilities' => array(
				'edit_post'          => 'activate_plugins',
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
		register_uninstall_hook( __FILE__, array( 'Deprecated_Log', 'on_uninstall' ) );
	}

	/**
	 * Runs on uninstall. Removes all log data.
	 */
	function on_uninstall() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'deprecated_log'" );
		delete_option( $this->option_name );
	}
}
/** Initialize. */
$GLOBALS['deprecated_log_instance'] = new Deprecated_Log;

endif;