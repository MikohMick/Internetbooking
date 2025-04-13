<?php
/**
 * Admin view for editing a booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Process form submission
if (isset($_POST['isb_edit_booking'])) {
    // Verify nonce
    check_admin_referer('isb_edit_booking_' . $booking_id);
    
    // Get form data
    $full_name = sanitize_text_field($_POST['full_name']);
    $phone_number = sanitize_text_field($_POST['phone_number']);
    $email = sanitize_email($_POST['email']);
    $status = sanitize_text_field($_POST['status']);
    $booking_date = sanitize_text_field($_POST['booking_date']);
    $booking_time = sanitize_text_field($_POST['booking_time']);
    
    // Get original booking details
    global $wpdb;
    $table_name = $wpdb->prefix . 'isb_bookings';
    $original_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT booking_date, booking_time FROM $table_name WHERE id = %d",
        $booking_id
    ), ARRAY_A);
    
    // Check if date or time has changed
    $date_time_changed = ($original_booking['booking_date'] !== $booking_date || 
                          $original_booking['booking_time'] !== $booking_time);
    
    if ($date_time_changed) {
        // Release the old time slot
        $slots_table = $wpdb->prefix . 'isb_time_slots';
        $wpdb->update(
            $slots_table,
            array(
                'is_booked' => 0,
                'booking_id' => null
            ),
            array(
                'booking_date' => $original_booking['booking_date'],
                'time_slot' => $original_booking['booking_time'],
                'booking_id' => $booking_id
            ),
            array('%d', '%s'),
            array('%s', '%s', '%d')
        );
        
        // Mark the new time slot as booked
        $wpdb->update(
            $slots_table,
            array(
                'is_booked' => 1,
                'booking_id' => $booking_id
            ),
            array(
                'booking_date' => $booking_date,
                'time_slot' => $booking_time,
                'is_booked' => 0
            ),
            array('%d', '%d'),
            array('%s', '%s', '%d')
        );
        
        error_log('Booking rescheduled from ' . $original_booking['booking_date'] . ' ' . 
                 $original_booking['booking_time'] . ' to ' . $booking_date . ' ' . $booking_time);
    }
    
    // Update booking
    $wpdb->update(
        $table_name,
        array(
            'full_name' => $full_name,
            'phone_number' => $phone_number,
            'email' => $email,
            'status' => $status,
            'booking_date' => $booking_date,
            'booking_time' => $booking_time
        ),
        array('id' => $booking_id),
        array('%s', '%s', '%s', '%s', '%s', '%s'),
        array('%d')
    );
    
    // Show success message
    echo '<div class="notice notice-success is-dismissible"><p>';
    _e('Booking updated successfully.', 'internet-service-booking');
    echo '</p></div>';
    
    // Refresh booking data
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $booking_id
    ), ARRAY_A);
}

// Load available time slots for the booking date
$availability = new ISB_Availability_Manager();
$available_slots = $availability->get_available_slots($booking['booking_date']);

// Add the current time slot to available slots if it's not already there
$current_slot_exists = false;
foreach ($available_slots as $slot) {
    if ($slot['time_slot'] === $booking['booking_time']) {
        $current_slot_exists = true;
        break;
    }
}

if (!$current_slot_exists) {
    $available_slots[] = array(
        'id' => 0,
        'time_slot' => $booking['booking_time']
    );
}
?>

<div class="wrap">
    <h1><?php _e('Edit Booking', 'internet-service-booking'); ?></h1>
    
    <a href="<?php echo admin_url('admin.php?page=internet-service-booking'); ?>" class="page-title-action"><?php _e('Back to List', 'internet-service-booking'); ?></a>
    
    <hr class="wp-header-end">
    
    <form method="post" action="">
        <?php wp_nonce_field('isb_edit_booking_' . $booking_id); ?>
        <input type="hidden" name="isb_edit_booking" value="1">
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Booking ID', 'internet-service-booking'); ?></th>
                <td>#<?php echo esc_html($booking['id']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Full Name', 'internet-service-booking'); ?></th>
                <td>
                    <input type="text" name="full_name" value="<?php echo esc_attr($booking['full_name']); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Phone Number', 'internet-service-booking'); ?></th>
                <td>
                    <input type="text" name="phone_number" value="<?php echo esc_attr($booking['phone_number']); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Email', 'internet-service-booking'); ?></th>
                <td>
                    <input type="email" name="email" value="<?php echo esc_attr($booking['email']); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('KRA PIN', 'internet-service-booking'); ?></th>
                <td><?php echo esc_html($booking['kra_pin']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Estate', 'internet-service-booking'); ?></th>
                <td><?php echo esc_html($booking['estate']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Block Number', 'internet-service-booking'); ?></th>
                <td><?php echo esc_html($booking['block_number']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('House Number', 'internet-service-booking'); ?></th>
                <td><?php echo esc_html($booking['house_number']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Package', 'internet-service-booking'); ?></th>
                <td><?php echo esc_html($booking['package']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('WiFi Username', 'internet-service-booking'); ?></th>
                <td><?php echo esc_html($booking['wifi_username']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('WiFi Password', 'internet-service-booking'); ?></th>
                <td><?php echo esc_html($booking['wifi_password']); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Installation Date', 'internet-service-booking'); ?></th>
                <td>
                    <input type="text" name="booking_date" id="admin_booking_date" value="<?php echo esc_attr($booking['booking_date']); ?>" class="regular-text isb-admin-datepicker">
                    <p class="description"><?php _e('Format: YYYY-MM-DD', 'internet-service-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Installation Time', 'internet-service-booking'); ?></th>
                <td>
                    <select name="booking_time" id="admin_booking_time">
                        <?php foreach ($available_slots as $slot): ?>
                            <?php 
                            // Format the time slot for display
                            $times = explode('-', $slot['time_slot']);
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
                            
                            $formatted_slot = $start_formatted . ' - ' . $end_formatted;
                            ?>
                            <option value="<?php echo esc_attr($slot['time_slot']); ?>" <?php selected($booking['booking_time'], $slot['time_slot']); ?>><?php echo esc_html($formatted_slot); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Status', 'internet-service-booking'); ?></th>
                <td>
                    <select name="status">
                        <option value="pending" <?php selected($booking['status'], 'pending'); ?>><?php _e('Pending', 'internet-service-booking'); ?></option>
                        <option value="confirmed" <?php selected($booking['status'], 'confirmed'); ?>><?php _e('Confirmed', 'internet-service-booking'); ?></option>
                        <option value="cancelled" <?php selected($booking['status'], 'cancelled'); ?>><?php _e('Cancelled', 'internet-service-booking'); ?></option>
                        <option value="completed" <?php selected($booking['status'], 'completed'); ?>><?php _e('Completed', 'internet-service-booking'); ?></option>
                        <option value="failed" <?php selected($booking['status'], 'failed'); ?>><?php _e('Failed', 'internet-service-booking'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Created At', 'internet-service-booking'); ?></th>
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking['created_at']))); ?></td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Update Booking', 'internet-service-booking'); ?>">
        </p>
    </form>
</div>

<style>
.form-table th {
    width: 200px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize datepicker
    $('.isb-admin-datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        onSelect: function(dateText) {
            // Load available time slots for the selected date
            loadAdminTimeSlots(dateText);
        }
    });
    
    // Function to load time slots for the selected date
    function loadAdminTimeSlots(date) {
        var timeSlotSelect = $('#admin_booking_time');
        
        // Clear existing options
        timeSlotSelect.empty();
        timeSlotSelect.append($('<option>', {
            value: '',
            text: 'Loading time slots...'
        }));
        
        // Make AJAX request to get available time slots
        $.ajax({
            url: '<?php echo rest_url('isb/v1/availability/'); ?>' + date,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                // Clear loading option
                timeSlotSelect.empty();
                
                if (response && response.length > 0) {
                    // Add available time slots
                    $.each(response, function(index, slot) {
                        // Format the time slot
                        var times = slot.time_slot.split('-');
                        var startHour = parseInt(times[0].substr(0, 2));
                        var startPeriod = startHour >= 12 ? 'PM' : 'AM';
                        startHour = startHour % 12;
                        startHour = startHour ? startHour : 12;
                        var startFormatted = startHour + ':' + times[0].substr(3, 2) + ' ' + startPeriod;
                        
                        var endHour = parseInt(times[1].substr(0, 2));
                        var endPeriod = endHour >= 12 ? 'PM' : 'AM';
                        endHour = endHour % 12;
                        endHour = endHour ? endHour : 12;
                        var endFormatted = endHour + ':' + times[1].substr(3, 2) + ' ' + endPeriod;
                        
                        var formattedSlot = startFormatted + ' - ' + endFormatted;
                        
                        timeSlotSelect.append($('<option>', {
                            value: slot.time_slot,
                            text: formattedSlot
                        }));
                    });
                } else {
                    // No available slots
                    timeSlotSelect.append($('<option>', {
                        value: '',
                        text: 'No time slots available for this date'
                    }));
                }
                
                // Re-select the current time slot if it exists
                var currentTimeSlot = '<?php echo esc_js($booking['booking_time']); ?>';
                if (currentTimeSlot) {
                    // Try to select the current time slot
                    var exists = false;
                    timeSlotSelect.find('option').each(function() {
                        if ($(this).val() === currentTimeSlot) {
                            exists = true;
                            return false; // Break the loop
                        }
                    });
                    
                    // If it doesn't exist in the new options, add it
                    if (!exists && currentTimeSlot) {
                        // Format the current time slot
                        var times = currentTimeSlot.split('-');
                        var startHour = parseInt(times[0].substr(0, 2));
                        var startPeriod = startHour >= 12 ? 'PM' : 'AM';
                        startHour = startHour % 12;
                        startHour = startHour ? startHour : 12;
                        var startFormatted = startHour + ':' + times[0].substr(3, 2) + ' ' + startPeriod;
                        
                        var endHour = parseInt(times[1].substr(0, 2));
                        var endPeriod = endHour >= 12 ? 'PM' : 'AM';
                        endHour = endHour % 12;
                        endHour = endHour ? endHour : 12;
                        var endFormatted = endHour + ':' + times[1].substr(3, 2) + ' ' + endPeriod;
                        
                        var formattedSlot = startFormatted + ' - ' + endFormatted;
                        
                        timeSlotSelect.append($('<option>', {
                            value: currentTimeSlot,
                            text: formattedSlot + ' (current)'
                        }));
                    }
                    
                    timeSlotSelect.val(currentTimeSlot);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading time slots:', error);
                
                // Handle error
                timeSlotSelect.empty();
                timeSlotSelect.append($('<option>', {
                    value: '',
                    text: 'Failed to load time slots. Please try again.'
                }));
            }
        });
    }
});
</script>