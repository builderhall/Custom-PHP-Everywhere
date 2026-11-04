<?php
/*
Plugin Name: Custom PHP Everywhere
Plugin URI: 
Description: A plugin that allows you to add custom PHP code to your posts and pages using shortcodes
Version: 1.0
Author: 
Author URI: 
License: GPL2
*/

// Create the custom_php_scripts table in the database when the plugin is activated
function custom_php_everywhere_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_php_scripts';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        script text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Ensure the administrator role has the execute_php capability
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( 'execute_php' ) ) {
        $role->add_cap( 'execute_php' );
    }
}
register_activation_hook( __FILE__, 'custom_php_everywhere_activate' );

// Handle the custom_php shortcode
function custom_php_everywhere_shortcode( $atts ) {
    // Check if user is logged in and has the 'execute_php' capability
    if ( is_user_logged_in() && current_user_can( 'execute_php' ) ) {
        // Retrieve the custom PHP script from the database
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_php_scripts';
        $id = isset( $atts['id'] ) ? (int) $atts['id'] : 0;
        if ( ! $id ) {
            return '';
        }
        $script = $wpdb->get_var( $wpdb->prepare( "SELECT script FROM $table_name WHERE id = %d", $id ) );
        if ( null === $script ) {
            return '';
        }
        // Execute the script
        ob_start();
        eval( '?>' . $script );
        $output = ob_get_clean();
        return $output;
    }
    // Return an error message if the user is not logged in or does not have the necessary capability
    return '<p>You do not have permission to execute PHP code.</p>';
}
add_shortcode( 'custom_php', 'custom_php_everywhere_shortcode' );

// Create the plugin's admin menu and page
function custom_php_everywhere_admin_menu() {
    add_menu_page(
        'Custom PHP Everywhere',
        'Custom PHP Everywhere',
        'manage_options',
        'custom-php-everywhere',
        'custom_php_everywhere_admin_page',
        '',
        99
    );
    add_submenu_page(
        'custom-php-everywhere',
        'Custom PHP Scripts',
        'Custom PHP Scripts',
        'manage_options',
        'custom-php-scripts',
        'custom_php_scripts_admin_page'
    );
}
add_action( 'admin_menu', 'custom_php_everywhere_admin_menu' );

// Render the main Custom PHP Everywhere admin dashboard page
function custom_php_everywhere_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'custom-php-everywhere' ) );
    }

    // Ensure administrator role has execute_php capability
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( 'execute_php' ) ) {
        $role->add_cap( 'execute_php' );
    }

    global $wpdb;
    $table_name    = $wpdb->prefix . 'custom_php_scripts';
    $total_scripts = 0;

    // Check if table exists and count scripts
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
        $total_scripts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Custom PHP Everywhere', 'custom-php-everywhere' ); ?></h1>
        <p class="description">
            <?php echo esc_html__( 'Easily write and manage custom PHP scripts, then execute them anywhere on your site using shortcodes.', 'custom-php-everywhere' ); ?>
        </p>

        <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
            <h2><?php echo esc_html__( 'Overview', 'custom-php-everywhere' ); ?></h2>
            <p>
                <strong><?php echo esc_html__( 'Total Saved Scripts:', 'custom-php-everywhere' ); ?></strong>
                <?php echo esc_html( $total_scripts ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-php-scripts' ) ); ?>" class="button button-primary">
                    <?php echo esc_html__( 'Manage Custom PHP Scripts', 'custom-php-everywhere' ); ?>
                </a>
            </p>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
            <h2><?php echo esc_html__( 'How It Works', 'custom-php-everywhere' ); ?></h2>
            <ol style="line-height: 1.8;">
                <li>
                    <strong><?php echo esc_html__( 'Create a Script:', 'custom-php-everywhere' ); ?></strong>
                    <?php echo esc_html__( 'Navigate to ', 'custom-php-everywhere' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-php-scripts' ) ); ?>"><strong><?php echo esc_html__( 'Custom PHP Scripts', 'custom-php-everywhere' ); ?></strong></a>
                    <?php echo esc_html__( 'and enter a descriptive name along with your custom PHP code.', 'custom-php-everywhere' ); ?>
                </li>
                <li>
                    <strong><?php echo esc_html__( 'Embed the Shortcode:', 'custom-php-everywhere' ); ?></strong>
                    <?php echo esc_html__( 'Insert the shortcode into any post or page content:', 'custom-php-everywhere' ); ?><br />
                    <code>[custom_php id="1"]</code>
                    <em><?php echo esc_html__( '(replace 1 with your saved script ID)', 'custom-php-everywhere' ); ?></em>
                </li>
                <li>
                    <strong><?php echo esc_html__( 'Display Output:', 'custom-php-everywhere' ); ?></strong>
                    <?php echo esc_html__( 'Anything returned or echoed by the script will be rendered in place of the shortcode.', 'custom-php-everywhere' ); ?>
                </li>
            </ol>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
            <h2><?php echo esc_html__( 'Security & Capabilities', 'custom-php-everywhere' ); ?></h2>
            <p>
                <?php echo esc_html__( 'To ensure site security, executing custom PHP code via shortcodes requires the ', 'custom-php-everywhere' ); ?>
                <code>execute_php</code>
                <?php echo esc_html__( ' capability. This capability is automatically granted to Administrators.', 'custom-php-everywhere' ); ?>
            </p>
        </div>
    </div>
    <?php
}

// Render the custom PHP scripts admin page (List, Add, Edit, Delete)
function custom_php_scripts_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'custom-php-everywhere' ) );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_php_scripts';

    $message      = '';
    $message_type = 'success';

    // Handle Delete Action
    if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
        $delete_id = (int) $_GET['id'];
        check_admin_referer( 'cpe_delete_script_' . $delete_id );

        $deleted = $wpdb->delete( $table_name, array( 'id' => $delete_id ), array( '%d' ) );
        if ( false !== $deleted ) {
            $message = __( 'Script deleted successfully.', 'custom-php-everywhere' );
        } else {
            $message      = __( 'Failed to delete script.', 'custom-php-everywhere' );
            $message_type = 'error';
        }
    }

    // Handle Add / Edit Submission
    if ( isset( $_POST['cpe_save_script'] ) ) {
        check_admin_referer( 'cpe_save_script_action', 'cpe_script_nonce' );

        $script_id = isset( $_POST['script_id'] ) ? (int) $_POST['script_id'] : 0;
        $name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $script    = isset( $_POST['script'] ) ? wp_unslash( $_POST['script'] ) : '';

        if ( empty( $name ) || empty( $script ) ) {
            $message      = __( 'Please provide both a script name and the PHP code.', 'custom-php-everywhere' );
            $message_type = 'error';
        } else {
            if ( $script_id > 0 ) {
                $updated = $wpdb->update(
                    $table_name,
                    array(
                        'name'   => $name,
                        'script' => $script,
                    ),
                    array( 'id' => $script_id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );

                if ( false !== $updated ) {
                    $message = __( 'Script updated successfully.', 'custom-php-everywhere' );
                } else {
                    $message      = __( 'Failed to update script.', 'custom-php-everywhere' );
                    $message_type = 'error';
                }
            } else {
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'name'   => $name,
                        'script' => $script,
                    ),
                    array( '%s', '%s' )
                );

                if ( false !== $inserted ) {
                    $message = __( 'Script added successfully.', 'custom-php-everywhere' );
                } else {
                    $message      = __( 'Failed to save script.', 'custom-php-everywhere' );
                    $message_type = 'error';
                }
            }
        }
    }

    // Check if in edit mode
    $editing_script = null;
    if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['id'] ) ) {
        $edit_id        = (int) $_GET['id'];
        $editing_script = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $edit_id ) );
    }

    // Retrieve all scripts
    $scripts = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Custom PHP Scripts', 'custom-php-everywhere' ); ?></h1>

        <?php if ( ! empty( $message ) ) : ?>
            <div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
                <p><?php echo esc_html( $message ); ?></p>
            </div>
        <?php endif; ?>

        <!-- Scripts List -->
        <h2><?php echo esc_html__( 'Saved Scripts', 'custom-php-everywhere' ); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 60px;"><?php echo esc_html__( 'ID', 'custom-php-everywhere' ); ?></th>
                    <th scope="col"><?php echo esc_html__( 'Name', 'custom-php-everywhere' ); ?></th>
                    <th scope="col"><?php echo esc_html__( 'Shortcode', 'custom-php-everywhere' ); ?></th>
                    <th scope="col" style="width: 160px;"><?php echo esc_html__( 'Actions', 'custom-php-everywhere' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $scripts ) ) : ?>
                    <?php foreach ( $scripts as $item ) : ?>
                        <tr>
                            <td><?php echo esc_html( $item->id ); ?></td>
                            <td><strong><?php echo esc_html( $item->name ); ?></strong></td>
                            <td><code>[custom_php id="<?php echo esc_attr( $item->id ); ?>"]</code></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-php-scripts&action=edit&id=' . $item->id ) ); ?>" class="button button-small">
                                    <?php echo esc_html__( 'Edit', 'custom-php-everywhere' ); ?>
                                </a>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=custom-php-scripts&action=delete&id=' . $item->id ), 'cpe_delete_script_' . $item->id ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this script?', 'custom-php-everywhere' ) ); ?>');">
                                    <?php echo esc_html__( 'Delete', 'custom-php-everywhere' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4"><?php echo esc_html__( 'No custom PHP scripts found. Create your first script below.', 'custom-php-everywhere' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <hr style="margin: 30px 0;" />

        <!-- Add / Edit Script Form -->
        <h2>
            <?php
            if ( $editing_script ) {
                /* translators: %d: script ID */
                printf( esc_html__( 'Edit Script #%d', 'custom-php-everywhere' ), esc_html( $editing_script->id ) );
            } else {
                echo esc_html__( 'Add New Script', 'custom-php-everywhere' );
            }
            ?>
        </h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=custom-php-scripts' ) ); ?>" style="max-width: 800px;">
            <?php wp_nonce_field( 'cpe_save_script_action', 'cpe_script_nonce' ); ?>
            <input type="hidden" name="cpe_save_script" value="1" />
            <input type="hidden" name="script_id" value="<?php echo esc_attr( $editing_script ? $editing_script->id : 0 ); ?>" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="cpe_name"><?php echo esc_html__( 'Script Name', 'custom-php-everywhere' ); ?></label>
                    </th>
                    <td>
                        <input name="name" type="text" id="cpe_name" value="<?php echo esc_attr( $editing_script ? $editing_script->name : '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( 'e.g. Current Date Display', 'custom-php-everywhere' ); ?>" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cpe_script"><?php echo esc_html__( 'PHP Script', 'custom-php-everywhere' ); ?></label>
                    </th>
                    <td>
                        <textarea name="script" id="cpe_script" rows="12" class="large-text code" placeholder="<?php echo esc_attr( "<?php\n// Your PHP code here\necho 'Hello World!';\n?>" ); ?>" required><?php echo esc_textarea( $editing_script ? $editing_script->script : '' ); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__( 'Enter your custom PHP code. Output from echo/print or return will be displayed by the shortcode.', 'custom-php-everywhere' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr( $editing_script ? __( 'Update Script', 'custom-php-everywhere' ) : __( 'Save Script', 'custom-php-everywhere' ) ); ?>" />
                <?php if ( $editing_script ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-php-scripts' ) ); ?>" class="button">
                        <?php echo esc_html__( 'Cancel', 'custom-php-everywhere' ); ?>
                    </a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    <?php
}
