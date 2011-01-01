<?php
/**
 * List table class for the deprecated log.
 *
 * @package Deprecated_Log
 * @since 0.2
 */

if ( ! class_exists( 'Deprecated_Log_List_Table' ) && class_exists( 'WP_Posts_List_Table' ) ) :

/**
 * List table class for the deprecated log.
 *
 * @package Deprecated_Log
 * @since 0.2
 */
class Deprecated_Log_List_Table extends WP_Posts_List_Table {

	function extra_tablenav( $which ) {
		global $wpdb, $typenow, $post_type_object;
?>
		<div class="alignleft actions">
<?php
		if ( 'top' == $which && !is_singular() ) {
			$types = array(
				'constant'      => __( 'Constant',        'log-deprecated' ),
				'function'      => __( 'Function',        'log-deprecated' ),
				'argument'      => __( 'Argument',        'log-deprecated' ),
				'functionality' => __( 'Functionality',   'log-deprecated' ),
				'file'          => __( 'File',            'log-deprecated' ),
				'wrong'         => __( 'Incorrect Usage', 'log-deprecated' ),
			);
			$types_used = $wpdb->get_col( "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_deprecated_log_type'" );
			if ( count( $types_used ) > 1 ) {
				echo '<select name="deprecated_type">';
				echo '<option value="">' . esc_html__( 'Show all types', 'log-deprecated' ) . '</option>';
				foreach ( $types_used as $type ) {
					$selected = ! empty( $_GET['deprecated_type'] ) ? selected( $_GET['deprecated_type'], $type, false ) : '';
					echo '<option' . $selected . ' value="' . esc_attr( $type ) . '">' . esc_html( $types[ $type ] ) . '</option>';
				}
				echo '</select> ';
			}
	
			$versions = $wpdb->get_col( "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_deprecated_log_version'" );
			if ( count( $versions ) > 1 ) {
				echo '<select name="deprecated_version">';
				echo '<option value="">' . esc_html__( 'Since', 'log-deprecated' ) . '</option>';
				usort( $versions, 'version_compare' );
				foreach ( array_reverse( $versions ) as $version ) {
					$selected = ! empty( $_GET['deprecated_version'] ) ? selected( $_GET['deprecated_version'], $version, false ) : '';
					echo '<option' . $selected . ' value="' . esc_attr( $version ) . '">' . esc_html( '&#8804; ' . $version ) . '</option>';
				}
				echo '</select> ';
			}
	
			/*
			$files = $wpdb->get_col( "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_deprecated_log_in_file'" );
			if ( count( $files > 1 ) ) {
				echo '<select name="deprecated_file">';
				echo '<option value="">' . esc_html__( 'Show all files', 'log-deprecated' ) . '</option>';
				foreach ( array_filter( $files ) as $file ) {
					$selected = '';
					if ( ! empty( $_GET['deprecated_file'] ) )
						$selected = selected( stripslashes( $_GET['deprecated_file'] ), $file, false );
					echo '<option' . $selected . ' value="' . esc_attr( $file ) . '">' . esc_html( $file ) . '</option>';
				}
				echo '</select>';
			}
			*/
			if ( count( $types_used ) > 1 || count( $versions ) > 1 )
				submit_button( __( 'Filter' ), 'secondary', 'post-query-submit', false );
		}

		if ( $this->has_items() )
			submit_button( __( 'Clear Log', 'log-deprecated' ), 'button-secondary apply', 'delete_all', false );
?>
		</div>
<?php
	}

}

endif;