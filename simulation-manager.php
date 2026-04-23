<?php
/**
 * Plugin Name: Simulation Manager
 * Description: Admin tool to upload and manage simulation HTML/ZIP packages in a fixed /simulations/ folder.
 * Version: 1.2.2
 * Author: David Oghi
 * Text Domain: simulation-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


define( 'SIMMGR_PLUGIN_FILE', __FILE__ );
define( 'SIMMGR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMMGR_DB_TABLE', 'simulation' );
define( 'SIMMGR_LIBRARY_DIR', ABSPATH . 'simulations/' );
define( 'SIMMGR_LIBRARY_URL', site_url( '/simulations/' ) );

register_activation_hook( __FILE__, 'simmgr_activate_plugin' );
register_deactivation_hook( __FILE__, 'simmgr_deactivate_plugin' );

add_action( 'admin_menu', 'simmgr_admin_menu' );
add_action( 'admin_init', 'simmgr_register_assets' );
add_action( 'admin_enqueue_scripts', 'simmgr_enqueue_assets' );
add_action( 'wp_ajax_simmgr_save_simulation', 'simmgr_handle_save' );
add_action( 'wp_ajax_simmgr_delete_simulation', 'simmgr_handle_delete' );
add_action( 'wp_ajax_simmgr_get_folder_contents', 'simmgr_handle_folder_contents' );
add_action( 'wp_ajax_simmgr_load_simulations', 'simmgr_handle_load_simulations' );

require_once SIMMGR_PLUGIN_DIR . 'admin.php';


require_once SIMMGR_PLUGIN_DIR . 'inc/plugin-update-checker/plugin-update-checker.php';

// Integrate auto update
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/davsong01/simulation-manager/', // Your repo URL
	__FILE__, // Full path to the main plugin file
	'simulation-manager' // Plugin slug
);

// Optional: If it's a private repository, you need a GitHub Token
// $myUpdateChecker->setAuthentication('your-github-personal-access-token');

// Optional: Set the branch (defaults to 'master' or 'main')
$myUpdateChecker->setBranch('main');

function simmgr_activate_plugin() {
    global $wpdb;

    $table_name = $wpdb->prefix . SIMMGR_DB_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(191) NOT NULL,
        folder_name varchar(191) NOT NULL,
        file_name varchar(191) NOT NULL,
        file_type varchar(50) NOT NULL,
        link varchar(255) NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    if ( ! file_exists( SIMMGR_LIBRARY_DIR ) ) {
        wp_mkdir_p( SIMMGR_LIBRARY_DIR );
        @file_put_contents( SIMMGR_LIBRARY_DIR . 'index.html', '<meta http-equiv="refresh" content="0; url=/" />' );
    }
}

function simmgr_deactivate_plugin() {
    // No cleanup on deactivate by default.
}

function simmgr_get_db_table_name() {
    global $wpdb;
    return $wpdb->prefix . SIMMGR_DB_TABLE;
}

function simmgr_register_assets() {
    wp_register_style( 'simmgr-admin-css', plugins_url( 'assets/css/admin.css', SIMMGR_PLUGIN_FILE ), array(), '1.0.0' );
    wp_register_script( 'simmgr-admin-js', plugins_url( 'assets/js/admin.js', SIMMGR_PLUGIN_FILE ), array( 'jquery' ), '1.0.0', true );
}

function simmgr_enqueue_assets( $hook ) {
    if ( $hook !== 'toplevel_page_simmgr' ) {
        return;
    }

    wp_enqueue_style( 'simmgr-admin-css' );
    wp_enqueue_script( 'simmgr-admin-js' );
    wp_localize_script( 'simmgr-admin-js', 'simmgrData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'simmgr-admin' ),
    ) );
}

function simmgr_sanitize_folder_name( $folder_name ) {
    $folder_name = sanitize_file_name( $folder_name );
    return trim( preg_replace( '/[^a-zA-Z0-9-_]/', '', $folder_name ), '-_' );
}

function simmgr_build_folder_name( $name ) {
    $folder_name = simmgr_sanitize_folder_name( $name );
    if ( empty( $folder_name ) ) {
        $folder_name = 'simulation';
    }
    return $folder_name . '-' . time();
}

function simmgr_prepare_upload_folder( $folder_name ) {
    $folder_name = simmgr_sanitize_folder_name( $folder_name );
    if ( empty( $folder_name ) ) {
        return false;
    }
    $target_folder = SIMMGR_LIBRARY_DIR . trailingslashit( $folder_name );
    if ( ! file_exists( $target_folder ) ) {
        wp_mkdir_p( $target_folder );
    }
    return $target_folder;
}

function simmgr_move_folder( $old_folder, $new_folder ) {
    if ( ! is_dir( $old_folder ) || empty( $new_folder ) ) {
        return false;
    }
    if ( $old_folder === $new_folder ) {
        return true;
    }
    if ( file_exists( $new_folder ) && ! is_dir( $new_folder ) ) {
        return false;
    }
    if ( ! file_exists( $new_folder ) ) {
        wp_mkdir_p( $new_folder );
    }
    $success = rename( untrailingslashit( $old_folder ), untrailingslashit( $new_folder ) );
    return $success;
}

function simmgr_handle_save() {
    if ( ! current_user_can( 'publish_posts' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    check_ajax_referer( 'simmgr-admin', 'security' );

    $action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : 'create';
    $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $folder_name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
    $record_id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;

    if ( empty( $name ) ) {
        wp_send_json_error( 'Simulation name is required.' );
    }

    if ( ! empty( $folder_name ) ) {
        $folder_name = simmgr_sanitize_folder_name( $folder_name );
    }

    if ( empty( $folder_name ) ) {
        $folder_name = simmgr_build_folder_name( $name );
    }

    $target_folder = simmgr_prepare_upload_folder( $folder_name );
    if ( ! $target_folder ) {
        wp_send_json_error( 'Unable to create simulation folder.' );
    }

    $file_name = '';
    $file_type = '';
    $link = '';

    if ( $action === 'create' ) {
        if ( empty( $_FILES['simulation_file'] ) || empty( $_FILES['simulation_file']['name'] ) ) {
            wp_send_json_error( 'Upload file is required.' );
        }

        $uploaded = simmgr_process_upload( $_FILES['simulation_file'], $target_folder );
        if ( is_wp_error( $uploaded ) ) {
            wp_send_json_error( $uploaded->get_error_message() );
        }

        $file_name = $uploaded['file_name'];
        $file_type = $uploaded['file_type'];
        $link = $uploaded['link'];

        global $wpdb;
        $table = simmgr_get_db_table_name();
        $result = $wpdb->insert( $table, array(
            'name'        => $name,
            'folder_name' => $folder_name,
            'file_name'   => $file_name,
            'file_type'   => $file_type,
            'link'        => esc_url_raw( $link ),
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );

        if ( false === $result ) {
            wp_send_json_error( 'Database insert failed.' );
        }

        wp_send_json_success( 'Simulation created successfully.' );
    }

    if ( $action === 'update' && $record_id ) {
        global $wpdb;
        $table = simmgr_get_db_table_name();
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $record_id ), ARRAY_A );
        if ( ! $existing ) {
            wp_send_json_error( 'Record not found.' );
        }

        $old_folder = simmgr_prepare_upload_folder( $existing['folder_name'] );
        if ( $existing['folder_name'] !== $folder_name ) {
            $new_folder_path = SIMMGR_LIBRARY_DIR . trailingslashit( $folder_name );
            if ( ! simmgr_move_folder( $old_folder, $new_folder_path ) ) {
                wp_send_json_error( 'Unable to rename folder safely.' );
            }
            $target_folder = $new_folder_path;
        } else {
            $target_folder = $old_folder;
        }

        $update = array(
            'name'        => $name,
            'folder_name' => $folder_name,
            'updated_at'  => current_time( 'mysql' ),
        );

        if ( ! empty( $_FILES['simulation_file'] ) && ! empty( $_FILES['simulation_file']['name'] ) ) {
            $uploaded = simmgr_process_upload( $_FILES['simulation_file'], $target_folder );
            if ( is_wp_error( $uploaded ) ) {
                wp_send_json_error( $uploaded->get_error_message() );
            }
            $update['file_name'] = $uploaded['file_name'];
            $update['file_type'] = $uploaded['file_type'];
            $update['link'] = esc_url_raw( $uploaded['link'] );
        } elseif ( $existing['folder_name'] !== $folder_name ) {
            $file_name = $existing['file_name'];
            $file_type = $existing['file_type'];
            if ( 'html' === $file_type ) {
                $update['link'] = trailingslashit( SIMMGR_LIBRARY_URL ) . $folder_name . '/' . basename( $file_name );
            } else {
                $update['link'] = trailingslashit( SIMMGR_LIBRARY_URL ) . trailingslashit( $folder_name );
            }
        }

        $wpdb->update( $table, $update, array( 'id' => $record_id ), array( '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
        wp_send_json_success( 'Simulation updated successfully.' );
    }

    wp_send_json_error( 'Invalid request.' );
}

function simmgr_process_upload( $file, $target_folder ) {
    if ( empty( $file['tmp_name'] ) || ! file_exists( $file['tmp_name'] ) ) {
        return new WP_Error( 'upload_error', 'Upload file is missing.' );
    }

    $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, array( 'html', 'htm', 'zip' ), true ) ) {
        return new WP_Error( 'invalid_type', 'Only HTML, HTM and ZIP files are allowed.' );
    }

    $sanitized_name = sanitize_file_name( wp_basename( $file['name'] ) );
    $destination = $target_folder . $sanitized_name;

    if ( in_array( $ext, array( 'html', 'htm' ), true ) ) {
        if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
            return new WP_Error( 'move_failed', 'Unable to save uploaded HTML file.' );
        }
        $link = trailingslashit( SIMMGR_LIBRARY_URL ) . basename( $target_folder ) . '/' . $sanitized_name;
        return array( 'file_name' => $sanitized_name, 'file_type' => 'html', 'link' => esc_url_raw( $link ) );
    }

    if ( $ext === 'zip' ) {
        $tmp_zip = $target_folder . uniqid( 'simmgr_', true ) . '.zip';
        if ( ! move_uploaded_file( $file['tmp_name'], $tmp_zip ) ) {
            return new WP_Error( 'move_failed', 'Unable to save uploaded ZIP file.' );
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $tmp_zip ) ) {
            @unlink( $tmp_zip );
            return new WP_Error( 'zip_open_failed', 'Unable to open ZIP archive.' );
        }

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $entry = $zip->getNameIndex( $i );
            if ( simmgr_is_zip_entry_unsafe( $entry ) ) {
                $zip->close();
                @unlink( $tmp_zip );
                return new WP_Error( 'unsafe_zip', 'ZIP archive contains unsafe paths.' );
            }
        }

        if ( ! $zip->extractTo( $target_folder ) ) {
            $zip->close();
            @unlink( $tmp_zip );
            return new WP_Error( 'extract_failed', 'Unable to extract ZIP archive.' );
        }
        $zip->close();
        @unlink( $tmp_zip );

        // Clean up unwanted folders like __MACOSX
        $macosx_path = $target_folder . DIRECTORY_SEPARATOR . '__MACOSX';
        if ( file_exists( $macosx_path ) && is_dir( $macosx_path ) ) {
            simmgr_delete_folder_recursive( $macosx_path );
        }

        $link = trailingslashit( SIMMGR_LIBRARY_URL ) . basename( $target_folder ) . '/';
        return array( 'file_name' => $sanitized_name, 'file_type' => 'zip', 'link' => esc_url_raw( $link ) );
    }

    return new WP_Error( 'unsupported', 'Unsupported file type.' );
}

function simmgr_is_zip_entry_unsafe( $entry ) {
    if ( empty( $entry ) ) {
        return true;
    }
    $normalized = str_replace( '\\', '/', $entry );
    if ( strpos( $normalized, '../' ) !== false || strpos( $normalized, '..\\' ) !== false ) {
        return true;
    }
    if ( substr( $normalized, 0, 1 ) === '/' || preg_match( '#^[A-Za-z]:/#', $normalized ) ) {
        return true;
    }
    return false;
}

function simmgr_handle_delete() {
    if ( ! current_user_can( 'publish_posts' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    check_ajax_referer( 'simmgr-admin', 'security' );

    $record_id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
    if ( ! $record_id ) {
        wp_send_json_error( 'Invalid record ID.' );
    }

    global $wpdb;
    $table = simmgr_get_db_table_name();
    $record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $record_id ), ARRAY_A );
    if ( ! $record ) {
        wp_send_json_error( 'Record not found.' );
    }

    $folder_path = SIMMGR_LIBRARY_DIR . trailingslashit( $record['folder_name'] );
    if ( file_exists( $folder_path ) && is_dir( $folder_path ) ) {
        simmgr_delete_folder_recursive( $folder_path );
    }

    $deleted = $wpdb->delete( $table, array( 'id' => $record_id ), array( '%d' ) );
    if ( false === $deleted ) {
        wp_send_json_error( 'Unable to delete record.' );
    }

    wp_send_json_success( 'Simulation deleted successfully.' );
}

function simmgr_delete_folder_recursive( $path ) {
    if ( ! is_dir( $path ) ) {
        return;
    }
    $items = scandir( $path );
    foreach ( $items as $item ) {
        if ( $item === '.' || $item === '..' ) {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if ( is_dir( $child ) ) {
            simmgr_delete_folder_recursive( $child );
        } else {
            @unlink( $child );
        }
    }
    @rmdir( $path );
}

function simmgr_handle_folder_contents() {
    if ( ! current_user_can( 'publish_posts' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }
    check_ajax_referer( 'simmgr-admin', 'security' );

    $folder_name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
    if ( empty( $folder_name ) ) {
        wp_send_json_error( 'Folder name is required.' );
    }

    $folder_path = SIMMGR_LIBRARY_DIR . trailingslashit( simmgr_sanitize_folder_name( $folder_name ) );
    if ( ! file_exists( $folder_path ) || ! is_dir( $folder_path ) ) {
        wp_send_json_error( 'Folder not found.' );
    }

    $contents = simmgr_get_directory_contents( $folder_path );
    wp_send_json_success( array( 'contents' => $contents, 'folder_name' => basename( untrailingslashit( $folder_path ) ) ) );
}

function simmgr_get_directory_contents( $folder_path ) {
    $items = array();
    $entries = scandir( $folder_path );
    foreach ( $entries as $entry ) {
        if ( $entry === '.' || $entry === '..' ) {
            continue;
        }
        $path = $folder_path . DIRECTORY_SEPARATOR . $entry;
        if ( is_dir( $path ) ) {
            $items[] = array(
                'name'     => $entry,
                'type'     => 'folder',
                'children' => simmgr_get_directory_contents( $path ),
            );
        } else {
            $items[] = array(
                'name' => $entry,
                'type' => 'file',
                'size' => filesize( $path ),
            );
        }
    }
    return $items;
}

function simmgr_handle_load_simulations() {
    if ( ! current_user_can( 'publish_posts' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }
    check_ajax_referer( 'simmgr-admin', 'security' );

    global $wpdb;
    $table = simmgr_get_db_table_name();
    $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
    wp_send_json_success( array( 'rows' => $rows ) );
}
