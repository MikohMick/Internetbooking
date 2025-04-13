<?php
/**
 * Class for displaying bookings in a list table
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class ISB_Bookings_List extends WP_List_Table {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'booking',
            'plural'   => 'bookings',
            'ajax'     => false
        ));
    }
    
    /**
     * Get columns
     */
    public function get_columns() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'id'           => __('ID', 'internet-service-booking'),
            'full_name'    => __('Full Name', 'internet-service-booking'),
            'phone_number' => __('Phone', 'internet-service-booking'),
            'email'        => __('Email', 'internet-service-booking'),
            'estate'       => __('Estate', 'internet-service-booking'),
            'package'      => __('Package', 'internet-service-booking'),
            'booking_date' => __('Date', 'internet-service-booking'),
            'booking_time' => __('Time', 'internet-service-booking'),
            'status'       => __('Status', 'internet-service-booking'),
            'created_at'   => __('Created', 'internet-service-booking')
        );
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'id'           => array('id', true),
            'full_name'    => array('full_name', false),
            'booking_date' => array('booking_date', false),
            'status'       => array('status', false),
            'created_at'   => array('created_at', false)
        );
    }
    
    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;
        
        // Set column headers
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Get table name
        $table_name = $wpdb->prefix . 'isb_bookings';
        
        // Build query
        $query = "SELECT * FROM $table_name";
        
        // Handle search
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        if (!empty($search)) {
            $query .= $wpdb->prepare(
                " WHERE full_name LIKE %s OR email LIKE %s OR phone_number LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        // Handle filtering
        $status_filter = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        if (!empty($status_filter)) {
            $query .= !empty($search) ? " AND" : " WHERE";
            $query .= $wpdb->prepare(" status = %s", $status_filter);
        }
        
        // Handle ordering
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        // Validate order and orderby
        $allowed_orderby = array('id', 'full_name', 'booking_date', 'status', 'created_at');
        $allowed_order = array('ASC', 'DESC');
        
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'id';
        }
        
        if (!in_array(strtoupper($order), $allowed_order)) {
            $order = 'DESC';
        }
        
        $query .= " ORDER BY $orderby $order";
        
        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        $offset = ($current_page - 1) * $per_page;
        $query .= " LIMIT $per_page OFFSET $offset";
        
        // Get items
        $this->items = $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'confirm' => __('Mark as Confirmed', 'internet-service-booking'),
            'cancel'  => __('Mark as Cancelled', 'internet-service-booking'),
            'delete'  => __('Delete', 'internet-service-booking')
        );
    }
    
    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        if ('delete' === $this->current_action()) {
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
            
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                wp_die('Security check failed');
            }
            
            $booking_ids = isset($_REQUEST['booking']) ? array_map('absint', $_REQUEST['booking']) : array();
            
            if (!empty($booking_ids)) {
                $this->delete_bookings($booking_ids);
            }
        } elseif ('confirm' === $this->current_action() || 'cancel' === $this->current_action()) {
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
            
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                wp_die('Security check failed');
            }
            
            $booking_ids = isset($_REQUEST['booking']) ? array_map('absint', $_REQUEST['booking']) : array();
            $status = ('confirm' === $this->current_action()) ? 'confirmed' : 'cancelled';
            
            if (!empty($booking_ids)) {
                $this->update_booking_status($booking_ids, $status);
            }
        }
    }
    
    /**
     * Delete bookings
     */
    private function delete_bookings($booking_ids) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'isb_bookings';
        $slots_table = $wpdb->prefix . 'isb_time_slots';
        
        $released_slots = 0;
        
        // First, get the booking details to release time slots
        foreach ($booking_ids as $booking_id) {
            // Get the booking details
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT booking_date, booking_time FROM $bookings_table WHERE id = %d",
                $booking_id
            ));
            
            if ($booking) {
                // Release the time slot
                $result = $wpdb->update(
                    $slots_table,
                    array(
                        'is_booked' => 0,
                        'booking_id' => null
                    ),
                    array(
                        'booking_date' => $booking->booking_date,
                        'time_slot' => $booking->booking_time,
                        'is_booked' => 1
                    ),
                    array('%d', '%s'),
                    array('%s', '%s', '%d')
                );
                
                if ($result) {
                    $released_slots++;
                    error_log("Released time slot: {$booking->booking_date} {$booking->booking_time} for booking ID: $booking_id");
                } else {
                    error_log("Failed to release time slot: {$booking->booking_date} {$booking->booking_time} for booking ID: $booking_id");
                    
                    // Double-check if the slot exists
                    $slot_exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $slots_table WHERE booking_date = %s AND time_slot = %s",
                        $booking->booking_date,
                        $booking->booking_time
                    ));
                    
                    if ($slot_exists) {
                        // Force update the slot
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $slots_table SET is_booked = 0, booking_id = NULL 
                             WHERE booking_date = %s AND time_slot = %s",
                            $booking->booking_date,
                            $booking->booking_time
                        ));
                        $released_slots++;
                        error_log("Force-released time slot: {$booking->booking_date} {$booking->booking_time}");
                    } else {
                        error_log("Time slot does not exist: {$booking->booking_date} {$booking->booking_time}");
                    }
                }
            }
        }
        
        // Convert array to comma-separated string for IN clause
        $ids_string = implode(',', array_map('absint', $booking_ids));
        
        // Delete bookings
        $wpdb->query("DELETE FROM $bookings_table WHERE id IN ($ids_string)");
        
        // Show admin notice
        add_action('admin_notices', function() use ($wpdb, $released_slots) {
            $count = $wpdb->rows_affected;
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                _n(
                    '%d booking was deleted and %d time slot was released.',
                    '%d bookings were deleted and %d time slots were released.',
                    $count,
                    'internet-service-booking'
                ),
                $count,
                $released_slots
            );
            echo '</p></div>';
        });
    }
    
    /**
     * Update booking status
     */
    private function update_booking_status($booking_ids, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_bookings';
        
        // Convert array to comma-separated string for IN clause
        $ids_string = implode(',', array_map('absint', $booking_ids));
        
        // Update status
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET status = %s WHERE id IN ($ids_string)",
            $status
        ));
        
        // If status is cancelled, release the time slots
        if ($status === 'cancelled') {
            $slots_table = $wpdb->prefix . 'isb_time_slots';
            $released_slots = 0;
            
            foreach ($booking_ids as $booking_id) {
                // Get the booking details
                $booking = $wpdb->get_row($wpdb->prepare(
                    "SELECT booking_date, booking_time FROM $table_name WHERE id = %d",
                    $booking_id
                ));
                
                if ($booking) {
                    // Release the time slot
                    $result = $wpdb->update(
                        $slots_table,
                        array(
                            'is_booked' => 0,
                            'booking_id' => null
                        ),
                        array(
                            'booking_date' => $booking->booking_date,
                            'time_slot' => $booking->booking_time,
                            'booking_id' => $booking_id
                        ),
                        array('%d', '%s'),
                        array('%s', '%s', '%d')
                    );
                    
                    if ($result) {
                        $released_slots++;
                        error_log("Released time slot due to cancellation: {$booking->booking_date} {$booking->booking_time} for booking ID: $booking_id");
                    } else {
                        // Force update if needed
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $slots_table SET is_booked = 0, booking_id = NULL 
                             WHERE booking_date = %s AND time_slot = %s",
                            $booking->booking_date,
                            $booking->booking_time
                        ));
                        error_log("Force-released time slot due to cancellation: {$booking->booking_date} {$booking->booking_time}");
                    }
                }
            }
        }
        
        // Show admin notice
        add_action('admin_notices', function() use ($wpdb, $status) {
            $count = $wpdb->rows_affected;
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                _n(
                    '%d booking was marked as %s.',
                    '%d bookings were marked as %s.',
                    $count,
                    'internet-service-booking'
                ),
                $count,
                $status
            );
            echo '</p></div>';
        });
    }
    
    /**
     * Column default
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'full_name':
            case 'phone_number':
            case 'email':
            case 'estate':
            case 'package':
                return $item[$column_name];
            case 'booking_time':
                // Format time with AM/PM
                $times = explode('-', $item[$column_name]);
                $start_hour = intval(substr($times[0], 0, 2));
                $start_period = $start_hour >= 12 ? 'PM' : 'AM';
                $start_hour = $start_hour % 12;
                $start_hour = $start_hour ? $start_hour : 12;
                $start_formatted = $start_hour . ':' . substr($times[0], 3, 2) . ' ' . $start_period;
                
                $end_hour = intval(substr($times[1], 0, 2));
                $end_period = $end_hour >= 12 ? 'PM' : 'AM';
                $end_hour = $end_hour % 12;
                $end_hour = $end_hour ? $end_hour : 12;
                $end_formatted = $end_hour . ':' . substr($times[1], 3, 2) . ' ' . $end_period;
                
                return $start_formatted . ' - ' . $end_formatted;
            case 'booking_date':
                return date_i18n(get_option('date_format'), strtotime($item[$column_name]));
            case 'created_at':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item[$column_name]));
            case 'status':
                return $this->get_status_badge($item[$column_name]);
            default:
                return print_r($item, true);
        }
    }
    
    /**
     * Get status badge
     */
    private function get_status_badge($status) {
        $status_classes = array(
            'pending'   => 'isb-status-pending',
            'confirmed' => 'isb-status-confirmed',
            'cancelled' => 'isb-status-cancelled',
            'completed' => 'isb-status-completed',
            'failed'    => 'isb-status-failed'
        );
        
        $class = isset($status_classes[$status]) ? $status_classes[$status] : '';
        
        return sprintf(
            '<span class="isb-status-badge %s">%s</span>',
            esc_attr($class),
            esc_html(ucfirst($status))
        );
    }
    
    /**
     * Column cb
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="booking[]" value="%d" />',
            $item['id']
        );
    }
    
    /**
     * Column full_name
     */
    public function column_full_name($item) {
        // Build row actions
        $actions = array(
            'edit'    => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=internet-service-booking&action=edit&booking=' . $item['id']),
                __('Edit', 'internet-service-booking')
            ),
            'confirm' => sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=internet-service-booking&action=confirm&booking=' . $item['id']),
                    'confirm-booking-' . $item['id']
                ),
                __('Confirm', 'internet-service-booking')
            ),
            'cancel'  => sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=internet-service-booking&action=cancel&booking=' . $item['id']),
                    'cancel-booking-' . $item['id']
                ),
                __('Cancel', 'internet-service-booking')
            ),
            'delete'  => sprintf(
                '<a href="%s" class="isb-delete-booking">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=internet-service-booking&action=delete&booking=' . $item['id']),
                    'delete-booking-' . $item['id']
                ),
                __('Delete', 'internet-service-booking')
            )
        );
        
        return sprintf(
            '<a href="%s"><strong>%s</strong></a> %s',
            admin_url('admin.php?page=internet-service-booking&action=edit&booking=' . $item['id']),
            $item['full_name'],
            $this->row_actions($actions)
        );
    }
    
    /**
     * No items
     */
    public function no_items() {
        _e('No bookings found.', 'internet-service-booking');
    }
    
/**
     * Extra tablenav
     */
    public function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }
        
        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        ?>
        <div class="alignleft actions">
            <select name="status">
                <option value=""><?php _e('All statuses', 'internet-service-booking'); ?></option>
                <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'internet-service-booking'); ?></option>
                <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php _e('Confirmed', 'internet-service-booking'); ?></option>
                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'internet-service-booking'); ?></option>
                <option value="completed" <?php selected($status, 'completed'); ?>><?php _e('Completed', 'internet-service-booking'); ?></option>
                <option value="failed" <?php selected($status, 'failed'); ?>><?php _e('Failed', 'internet-service-booking'); ?></option>
            </select>
            <?php submit_button(__('Filter', 'internet-service-booking'), '', 'filter_action', false); ?>
        </div>
        <?php
    }
}