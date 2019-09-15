<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class AdminLogger_List extends WP_List_Table {

    /** Class constructor */
    public function __construct() {

        parent::__construct( [
            'singular' => __( 'Admin Logger', 'sp' ), //singular name of the listed records
            'plural'   => __( 'Admin Logger', 'sp' ), //plural name of the listed records
            'ajax'     => false //does this table support ajax?
        ] );
    }

    /**
     * Retrieve loggers data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_loggers( $per_page = 10, $page_number = 1 ) {
        global $wpdb;

        $search = '';
        //Retrieve $customvar for use in query to get items.
        $customvar = ( isset($_REQUEST['customvar']) ? $_REQUEST['customvar'] : '');
        if($customvar != '') {
            $search_custom_vars= "AND status = " . ((esc_sql( $wpdb->esc_like( $customvar )) == 'trash') ? 1 : 0 ) ;
        } else	{
            $search_custom_vars = 'AND status = 0 ';
        }
        if ( ! empty( $_REQUEST['s'] ) ) {
            $search = "AND concat(event, username, role, ip_address) LIKE '%" . esc_sql( $wpdb->esc_like( $_REQUEST['s'] ) ) . "%'";
        }

        $sql = "SELECT * FROM {$wpdb->prefix}admlogger WHERE 1 = 1 {$search} {$search_custom_vars}" ;

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
            $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
        }

        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


        $result = $wpdb->get_results( $sql, 'ARRAY_A' );

        return $result;
    }

    /**
     * Update a admlogger record.
     *
     * @param int $id admlogger ID
     */
    public static function update_logger( $id, $status ) {
        global $wpdb;

        $wpdb->update(
            "{$wpdb->prefix}admlogger",
            array(
                'status' => $status
            ),
            array( 'ID' => $id ),
            array(
                '%d'
            ),
            array( '%d' )
        );
    }

    /**
     * Delete a admlogger record.
     *
     * @param int $id admlogger ID
     */
    public static function delete_logger( $id ) {
        global $wpdb;

        $wpdb->delete(
            "{$wpdb->prefix}admlogger",
            [ 'ID' => $id ],
            [ '%d' ]
        );
    }

    /**
     * Returns the count of records in the database per status
     *
     * @return null|string
     */
    public static function record_count_status($status) {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}admlogger WHERE status = ".$status;

        return $wpdb->get_var( $sql );
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count() {
        global $wpdb;

        $customvar = ( isset($_REQUEST['customvar']) ? $_REQUEST['customvar'] : '');
        if ($customvar != '') {
            $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}admlogger WHERE status = 1 ";
        } else {
            $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}admlogger WHERE status = 0 ";
        }

        return $wpdb->get_var( $sql );
    }

    /**
     * Text displayed when no admlogger data is available
     */
    public function no_items() {
        _e( 'No loggers avaliable.', 'sp' );
    }

    /**
     * Render a column when no column specific method exist.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'event':
            case 'username':
            case 'role':
            case 'ip_address':
            case 'logtime':
                return $item[ $column_name ];
            default:
                return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="bulk-action[]" value="%s" />', $item['id']
        );
    }

    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_id( $item ) {

        $delete_nonce = wp_create_nonce( 'sp_delete_admlogger' );

        $title = '<strong>' . $item['id'] . '</strong>';

        $customvar = ( isset($_REQUEST['customvar']) ? $_REQUEST['customvar'] : '');
        if ($customvar != '') {

            $actions = [
                'restore' => sprintf( '<a href="?page=%s&action=%s&admlogger=%s&_wpnonce=%s&customvar=%s">Restore</a>', esc_attr( $_REQUEST['page'] ), 'restore', absint( $item['id'] ), $delete_nonce, $customvar ),
                'delete' => sprintf( '<a href="?page=%s&action=%s&admlogger=%s&_wpnonce=%s&customvar=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce, $customvar )
            ];
        } else	{

            $actions = [
                'delete' => sprintf( '<a href="?page=%s&action=%s&admlogger=%s&_wpnonce=%s&customvar=%s">Trash</a>', esc_attr( $_REQUEST['page'] ), 'trash', absint( $item['id'] ), $delete_nonce, $customvar )
            ];
        }

        return $title . $this->row_actions( $actions );
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    function get_columns() {
        $columns = [
            'cb'      => '<input type="checkbox" />',
            'ID'    => __( 'ID', 'sp' ),
            'event'    => __( 'Event', 'sp' ),
            'username' => __( 'Username', 'sp' ),
            'role'    => __( 'Role', 'sp' ),
            'ip_address'    => __( 'IP Address', 'sp' ),
            'logtime'    => __( 'Time', 'sp' )
        ];

        return $columns;
    }

    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'ID' => array( 'ID', true ),
            'event' => array( 'event', true ),
            'username' => array( 'username', false ),
            'role' => array( 'role', false ),
            'ip_address' => array( 'ip_address', false ),
            'logtime' => array( 'logtime', true ),
        );

        return $sortable_columns;
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions() {
        $customvar = ( isset($_REQUEST['customvar']) ? $_REQUEST['customvar'] : '');
        if ($customvar != '') {

            $actions = [
                'bulk-restore' => 'Restore',
                'bulk-delete' => 'Delete'
            ];
        } else	{

            $actions = [
                'bulk-trash' => 'Trash'
            ];
        }

        return $actions;
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_views() {
        $views = array();
        $current = ( !empty($_REQUEST['customvar']) ? $_REQUEST['customvar'] : 'all');

        //All link
        $class = ($current == 'all' ? ' class="current"' :'');
        $all_url = remove_query_arg('customvar');
        $views['all'] = "<a href='{$all_url }' {$class} >All (".$this->record_count_status(0).")</a>";

        //Trash link
        $foo_url = add_query_arg('customvar','trash');
        $class = ($current == 'trash' ? ' class="current"' :'');
        $views['trash'] = "<a href='{$foo_url}' {$class} >Trash (".$this->record_count_status(1).")</a>";

        return $views;
    }

    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items() {

        $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        //$this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'loggers_per_page', 5 );
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        $this->set_pagination_args( [
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );

        if ($current_page > abs($total_items/$per_page)) {
            $current_page = 1;
        }

        $this->items = self::get_loggers( $per_page, $current_page );
    }

    /**
     * Handles bulk action
     */
    public function process_bulk_action() {

        //Detect when a bulk action is being triggered...
        if ( 'trash' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'sp_delete_admlogger' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                self::update_logger( absint( $_GET['admlogger'] ), 1 );
                return;
            }

        }

        if ( 'restore' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'sp_delete_admlogger' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                self::update_logger( absint( $_GET['admlogger'] ), 0 );
                return;
            }

        }

        if ( 'delete' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'sp_delete_admlogger' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                self::delete_logger( absint( $_GET['admlogger'] ) );
                return;
            }

        }

        // If the delete bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
            || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
        ) {

            $delete_ids = esc_sql( $_POST['bulk-action'] );

            // loop over the array of record IDs and delete them
            foreach ( $delete_ids as $id ) {
                self::delete_logger( $id );
            }
            return;
        }

        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-trash' )
            || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-trash' )
        ) {

            $delete_ids = esc_sql( $_POST['bulk-action'] );

            // loop over the array of record IDs and delete them
            foreach ( $delete_ids as $id ) {
                self::update_logger( $id,  1);
            }
            return;
        }

        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-restore' )
            || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-restore' )
        ) {

            $delete_ids = esc_sql( $_POST['bulk-action'] );

            // loop over the array of record IDs and delete them
            foreach ( $delete_ids as $id ) {
                self::update_logger( $id,  0);
            }
            return;
        }
    }

}