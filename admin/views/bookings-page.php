<?php
/**
 * Admin view for bookings list
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Internet Service Bookings', 'internet-service-booking'); ?></h1>
    
    <hr class="wp-header-end">
    
    <?php
    // Display notices
    settings_errors();
    
    // Handle single booking actions
    $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
    $booking_id = isset($_REQUEST['booking']) ? absint($_REQUEST['booking']) : 0;
    
    // Handle edit action
    if ('edit' === $action && $booking_id > 0) {
        // Get booking data
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_bookings';
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $booking_id
        ), ARRAY_A);
        
        if ($booking) {
            include ISB_PLUGIN_DIR . 'admin/views/edit-booking.php';
            return;
        }
    }
    
    // Handle confirm action
    if ('confirm' === $action && $booking_id > 0) {
        // Verify nonce
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
        if (wp_verify_nonce($nonce, 'confirm-booking-' . $booking_id)) {
            // Update status
            global $wpdb;
            $table_name = $wpdb->prefix . 'isb_bookings';
            $wpdb->update(
                $table_name,
                array('status' => 'confirmed'),
                array('id' => $booking_id),
                array('%s'),
                array('%d')
            );
            
            // Show notice
            echo '<div class="notice notice-success is-dismissible"><p>';
            _e('Booking confirmed successfully.', 'internet-service-booking');
            echo '</p></div>';
        }
    }
    
    // Handle cancel action
    if ('cancel' === $action && $booking_id > 0) {
        // Verify nonce
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
        if (wp_verify_nonce($nonce, 'cancel-booking-' . $booking_id)) {
            // Update status
            global $wpdb;
            $table_name = $wpdb->prefix . 'isb_bookings';
            $wpdb->update(
                $table_name,
                array('status' => 'cancelled'),
                array('id' => $booking_id),
                array('%s'),
                array('%d')
            );
            
            // Show notice
            echo '<div class="notice notice-success is-dismissible"><p>';
            _e('Booking cancelled successfully.', 'internet-service-booking');
            echo '</p></div>';
        }
    }
    
    // Handle delete action
    if ('delete' === $action && $booking_id > 0) {
        // Verify nonce
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
        if (wp_verify_nonce($nonce, 'delete-booking-' . $booking_id)) {
            // Delete booking
            global $wpdb;
            $table_name = $wpdb->prefix . 'isb_bookings';
            $wpdb->delete(
                $table_name,
                array('id' => $booking_id),
                array('%d')
            );
            
            // Show notice
            echo '<div class="notice notice-success is-dismissible"><p>';
            _e('Booking deleted successfully.', 'internet-service-booking');
            echo '</p></div>';
        }
    }
    ?>
    
    <!-- Search form -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php
        $bookings_list->search_box(__('Search Bookings', 'internet-service-booking'), 'isb-search');
        $bookings_list->display();
        ?>
    </form>
</div>

<style>
/* Status badges */
.isb-status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.isb-status-pending {
    background-color: #f0f0f0;
    color: #777;
}

.isb-status-confirmed {
    background-color: #e7f5ea;
    color: #388e3c;
}

.isb-status-cancelled {
    background-color: #ffebee;
    color: #d32f2f;
}

.isb-status-completed {
    background-color: #e3f2fd;
    color: #1565c0;
}

.isb-status-failed {
    background-color: #fce4ec;
    color: #c2185b;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Confirm delete action
    $('.isb-delete-booking').on('click', function(e) {
        if (!confirm('<?php _e('Are you sure you want to delete this booking? This action cannot be undone.', 'internet-service-booking'); ?>')) {
            e.preventDefault();
        }
    });
});
</script>