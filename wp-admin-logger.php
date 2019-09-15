<?php
/**
 * Plugin Name: Wordpress Admin Logger
 * Description: Plugin for log User login and logout
 * Plugin URI: https://www.wpsecurityauditlog.com
 * Author: Mukh. Kurniawan
 * Author URI: https://www.wpsecurityauditlog.com
 * Version: 1.0.0
 * License: GNU General Public License v2.0
 * Text Domain: wp-admin-logger
 *
 * @package wp-admin-logger
 */

defined( 'ABSPATH' ) or die( 'Not Authorized!' );

class wpAdminLogger {

    /**
     * Table Name for Admin Logger : admlogger
     *
     * @var string
     */
    protected $tblName = '';

    /**
     * Current user object
     *
     * @var WP_User
     */
    protected $current_user = null;

    /**
     * Admin Logger List
     */
    protected $adminLoggerList = null;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->setTblName();

        add_action( 'wp_login', array( $this, 'eventLogin' ), 10, 2 );
        add_action( 'wp_logout', array( $this, 'eventLogout' ) );
        add_action( 'clear_auth_cookie', array( $this, 'GetCurrentUser' ), 10 );
        add_filter( 'set-screen-option',  array( $this, 'set_screen' ), 10, 3 );
        add_action( 'admin_menu', array($this, 'add_admin_logger_menu') );

        // Active Plugin Hook
        register_activation_hook(__FILE__, array($this, 'plugin_activate'));
        // Deactive Plugin Hook
        register_deactivation_hook(__FILE__, array($this, 'plugin_deactivate'));

    }

    /**
     * Event Login.
     *
     * @param string $user_login - Username.
     * @param object $user - WP_User object.
     */
    public function eventLogin( $user_login, $user ) {
        $this->current_user = $user;
        if ( $this->current_user->ID ) {
            $this->insert_admin_log('Login');
        }
    }

    /**
     * Event Logout.
     */
    public function eventLogout() {
        if ( $this->current_user->ID ) {
            $this->insert_admin_log('Logout');
        }
    }

    /**
     * Sets current user.
     */
    public function GetCurrentUser() {
        $this->current_user = wp_get_current_user();
    }

    /**
     * Sets $tblName.
     */
    public function setTblName() {
        global $table_prefix, $wpdb;

        $tblname = 'admlogger';
        $wp_track_table = $table_prefix . "$tblname";
        $this->tblName = $wp_track_table;
    }

    /**
     * Add submenu under Tools
     */
    public function add_admin_logger_menu() {

        $hook = add_options_page(
            'WP Admin Logger',
            'WP Admin Logger',
            'manage_options',
            'admin-logger',
            array($this, 'display_admin_logger_page')
        );

        add_action( "load-$hook",  array($this, 'screen_option') );

        $this->adminLoggerList = new AdminLogger_List();
    }

    /**
     * Get User IP address
     */
    function get_the_user_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public static function set_screen( $status, $option, $value ) {
        return $value;
    }

    /**
     * Display WP Admin Logger page
     */
    function display_admin_logger_page() {
        ?>
        <div class="wrap">
            <h2>WP Admin Logger</h2>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <form method="post">
                                <?php
                                $this->adminLoggerList->process_bulk_action();
                                $this->adminLoggerList->views();
                                $this->adminLoggerList->prepare_items();
                                $this->adminLoggerList->search_box( 'search', 'search_id' );
                                $this->adminLoggerList->display();
                                ?>
                            </form>
                        </div>
                    </div>
                </div>
                <br class="clear">
            </div>
        </div>
        <script type="text/javascript" defer="defer">

        </script>
        <?php
    }

    /**
     * Screen options
     */
    public function screen_option() {

        $option = 'per_page';
        $args   = [
            'label'   => 'Per page',
            'default' => 10,
            'option'  => 'loggers_per_page'
        ];

        add_screen_option( $option, $args );
    }

    /**
     * Create admlogger if not exists
     */
    function create_admin_logger_table() {
        global $wpdb;

        $wp_track_table = $this->tblName;

        #Check to see if the table exists already, if not, then create it

        if($wpdb->get_var( "show tables like '$wp_track_table'" ) != $wp_track_table)
        {
            $sql = "CREATE TABLE $wp_track_table (
            id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
            event varchar(50) NOT NULL,
            username varchar(50) NOT NULL,
            role varchar(50) NOT NULL,
            ip_address varchar(50) NOT NULL,
            logtime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status mediumint(9) unsigned DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id)
            );";

            require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
            dbDelta($sql);
        }
    }

    /**
     * Add log on database
     */
    public function insert_admin_log($event) {
        global $wpdb;

        $user = $this->current_user;
        $roles = ( array ) $user->roles;

        $wpdb->insert(
            $this->tblName,
            array(
                'event'         => $event,
                'username'      => $user->data->user_login,
                'role'          => implode($roles, ", "),
                'ip_address'    => $this->get_the_user_ip(),
                'logtime'      => date("Y-m-d H:i:s")
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );
    }

    /**
     * Plugin Activation
     *
     * @since 1.0.0
     */
    public function plugin_activate() {
        $this->create_admin_logger_table();
        flush_rewrite_rules();
    }

    /**
     * Plugin Deactivation
     *
     * @since 1.0.0
     */
    public function plugin_deactivate() {
        flush_rewrite_rules();
    }
}

//include Admin Logger List
include(plugin_dir_path(__FILE__) . 'inc/wp-admin-logger-list.php');

new wpAdminLogger();