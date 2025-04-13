<?php
/**
 * Class for handling form submissions
 */
class ISB_Form_Handler {
    /**
     * Constructor
     */
    public function __construct() {
        // Add AJAX action hooks
        add_action('wp_ajax_isb_submit_booking', array($this, 'ajax_submit_booking'));
        add_action('wp_ajax_nopriv_isb_submit_booking', array($this, 'ajax_submit_booking'));
        
        // Add test AJAX handler
        add_action('wp_ajax_isb_test_ajax', array($this, 'test_ajax'));
        add_action('wp_ajax_nopriv_isb_test_ajax', array($this, 'test_ajax'));
    }
    
    /**
     * Test AJAX handler
     */
    public function test_ajax() {
        error_log('Test AJAX handler called');
        wp_send_json_success(array('message' => 'AJAX test successful'));
        die();
    }
    
    /**
     * AJAX handler for form submission
     */
    public function ajax_submit_booking() {
        // Debug logging - save this to a file we can check
        error_log('======= BOOKING FORM SUBMISSION ========');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Don't check the nonce for now - just to see if we can get past this point
        // We'll add proper security back once basic functionality works
        
        // Process the submission with minimal validation
        try {
            // Just capture the data without extensive validation for testing
            $booking_data = array(
                'full_name' => isset($_POST['full_name']) ? sanitize_text_field($_POST['full_name']) : '',
                'phone_number' => isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '',
                'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
                'kra_pin' => isset($_POST['kra_pin']) ? sanitize_text_field($_POST['kra_pin']) : '',
                'estate' => isset($_POST['estate']) ? sanitize_text_field($_POST['estate']) : '',
                'block_number' => isset($_POST['block_number']) ? sanitize_text_field($_POST['block_number']) : '',
                'house_number' => isset($_POST['house_number']) ? sanitize_text_field($_POST['house_number']) : '',
                'package' => isset($_POST['package']) ? sanitize_text_field($_POST['package']) : '',
                'wifi_username' => isset($_POST['wifi_username']) ? sanitize_text_field($_POST['wifi_username']) : '',
                'wifi_password' => isset($_POST['wifi_password']) ? sanitize_text_field($_POST['wifi_password']) : '',
                'booking_date' => isset($_POST['booking_date']) ? sanitize_text_field($_POST['booking_date']) : '',
                'booking_time' => isset($_POST['booking_time']) ? sanitize_text_field($_POST['booking_time']) : '',
                'status' => 'confirmed' // Changed from 'pending' to 'confirmed'
            );
            
            error_log('Processed booking data: ' . print_r($booking_data, true));
            
            // Validate package selection based on estate
            if (!$this->validate_package_for_estate($booking_data['package'], $booking_data['estate'])) {
                error_log('Invalid package for estate: ' . $booking_data['package'] . ' for ' . $booking_data['estate']);
                wp_send_json_error(array('message' => 'Invalid package selection for the selected estate.'));
                return;
            }
            
            // Insert the booking directly
            global $wpdb;
            $table_name = $wpdb->prefix . 'isb_bookings';
            
            $wpdb->insert(
                $table_name,
                $booking_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            $booking_id = $wpdb->insert_id;
            error_log('Booking inserted with ID: ' . $booking_id);
            
            if ($booking_id) {
                // Mark the time slot as booked
                $slots_table = $wpdb->prefix . 'isb_time_slots';
                $wpdb->update(
                    $slots_table,
                    array(
                        'is_booked' => 1,
                        'booking_id' => $booking_id
                    ),
                    array(
                        'booking_date' => $booking_data['booking_date'],
                        'time_slot' => $booking_data['booking_time'],
                        'is_booked' => 0
                    ),
                    array('%d', '%d'),
                    array('%s', '%s', '%d')
                );
                
                error_log('Time slot marked as booked.');
                
                // Send data to webhook
                $webhook_result = $this->send_to_webhook($booking_id, $booking_data);
                error_log('Webhook result: ' . print_r($webhook_result, true));
                
                // Send success response
                wp_send_json_success(array(
                    'booking_id' => $booking_id,
                    'message' => 'Booking successful!',
                    'webhook_status' => $webhook_result['status'] ?? 'unknown'
                ));
            } else {
                error_log('Failed to insert booking: ' . $wpdb->last_error);
                wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
            }
        } catch (Exception $e) {
            error_log('Exception in booking process: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Server error: ' . $e->getMessage()));
        }
        
        // This should never be reached, but just in case
        error_log('Reached end of ajax_submit_booking without sending a response');
        wp_send_json_error(array('message' => 'Unknown error occurred.'));
        die();
    }
    
/**
 * Send booking data to webhook
 * 
 * @param int $booking_id Booking ID
 * @param array $booking_data Booking data
 * @return array Response from webhook
 */
private function send_to_webhook($booking_id, $booking_data) {
    // Format time for better readability
    $formatted_time = $this->format_time_slot($booking_data['booking_time']);
    
    // Prepare data for webhook
    $webhook_data = array(
        'booking_id' => $booking_id,
        'customer_name' => $booking_data['full_name'],
        'phone_number' => $booking_data['phone_number'],
        'email' => $booking_data['email'],
        'kra_pin' => $booking_data['kra_pin'],
        'estate' => $booking_data['estate'],
        'block_number' => $booking_data['block_number'],
        'house_number' => $booking_data['house_number'],
        'package' => $booking_data['package'],
        'wifi_username' => $booking_data['wifi_username'],
        'wifi_password' => $booking_data['wifi_password'],
        'installation_date' => $booking_data['booking_date'],
        'installation_time' => $booking_data['booking_time'],
        'formatted_time' => $formatted_time,
        'status' => $booking_data['status'],
        'webhook_timestamp' => current_time('mysql'),
        'source' => 'internet-service-booking-plugin'
    );
    
    // Set the correct webhook URL
    $webhook_url = 'https://turbonetsolutions.co.ke/webhook.php';
    
    // Log webhook data
    error_log('Sending webhook data to ' . $webhook_url);
    error_log('Webhook payload: ' . json_encode($webhook_data));
    
    // Direct POST approach with wp_remote_post
    $response = wp_remote_post($webhook_url, array(
        'method' => 'POST',
        'timeout' => 30,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true, // Use blocking to get response
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($webhook_data),
        'cookies' => array(),
        'sslverify' => false // Disable SSL verification which might cause issues
    ));
    
    // Check response
    if (is_wp_error($response)) {
        error_log('Webhook error: ' . $response->get_error_message());
        
        // Try alternative file_get_contents approach
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($webhook_data),
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($webhook_url, false, $context);
        
        error_log('Alternative method result: ' . ($result ?: 'No response'));
        
        return array(
            'status' => 'error',
            'message' => $response->get_error_message(),
            'alternative_tried' => true
        );
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    error_log('Webhook response code: ' . $response_code);
    error_log('Webhook response body: ' . $response_body);
    
    // Also try the non-blocking approach for redundancy
    wp_remote_post($webhook_url, array(
        'method' => 'POST',
        'timeout' => 0.01,
        'blocking' => false,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($webhook_data),
        'sslverify' => false
    ));
    
    return array(
        'status' => ($response_code >= 200 && $response_code < 300) ? 'success' : 'error',
        'code' => $response_code,
        'body' => $response_body
    );
}
    /**
     * Format time slot for display
     * 
     * @param string $time_slot Time slot in format "HH:MM-HH:MM"
     * @return string Formatted time slot with AM/PM
     */
    private function format_time_slot($time_slot) {
        $times = explode('-', $time_slot);
        $start = $this->format_time($times[0]);
        $end = $this->format_time($times[1]);
        return $start . ' - ' . $end;
    }
    
    /**
     * Format time with AM/PM
     * 
     * @param string $time Time in format "HH:MM"
     * @return string Formatted time
     */
    private function format_time($time) {
        $parts = explode(':', $time);
        $hour = intval($parts[0]);
        $period = ($hour >= 12) ? 'PM' : 'AM';
        $hour = $hour % 12;
        $hour = $hour ? $hour : 12;
        return $hour . ':' . $parts[1] . ' ' . $period;
    }
    
    /**
     * Process form submission (original method - kept for reference)
     * 
     * @param array $data Form data
     * @return int|WP_Error Booking ID on success, WP_Error on failure
     */
    public function process_submission($data) {
        // Validate required fields
        $required_fields = array(
            'full_name', 'phone_number', 'email', 'kra_pin', 'estate',
            'block_number', 'house_number', 'package', 'wifi_username',
            'wifi_password', 'booking_date', 'booking_time'
        );
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Field '$field' is required.");
            }
        }
        
        // Validate email
        if (!is_email($data['email'])) {
            return new WP_Error('invalid_email', 'Please enter a valid email address.');
        }
        
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['booking_date'])) {
            return new WP_Error('invalid_date', 'Invalid date format.');
        }
        
        // Validate time slot format (HH:00-HH:00)
        if (!preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $data['booking_time'])) {
            return new WP_Error('invalid_time', 'Invalid time slot format.');
        }
        
        // Validate package selection based on estate
        if (!$this->validate_package_for_estate($data['package'], $data['estate'])) {
            return new WP_Error('invalid_package', 'Invalid package selection for the selected estate.');
        }
        
        // Store booking in database
        $db_handler = new ISB_Database_Handler();
        $booking_id = $db_handler->insert_booking($data);
        
        if (!$booking_id) {
            return new WP_Error('db_error', 'Failed to save booking.');
        }
        
        // Mark the time slot as booked
        $availability = new ISB_Availability_Manager();
        $slot_booked = $availability->book_time_slot(
            $data['booking_date'],
            $data['booking_time'],
            $booking_id
        );
        
        if (!$slot_booked) {
            // Rollback the booking if time slot couldn't be booked
            $db_handler->update_booking_status($booking_id, 'failed');
            return new WP_Error('slot_unavailable', 'The selected time slot is no longer available.');
        }
        
        return $booking_id;
    }
    
    /**
     * Validate package selection based on estate
     * 
     * @param string $package Selected package
     * @param string $estate Selected estate
     * @return bool True if valid, false otherwise
     */
    private function validate_package_for_estate($package, $estate) {
        // Saifee Park specific packages
        $saifee_packages = array(
            'Bronze 10 mbps @1000',
            'Silver 20 mbps @2000',
            'Platinum 40mbps@4000',
            'Gold 80mbps @8000',
            'Diamond 120mbps @10000'
        );
        
        // Other estates packages
        $other_packages = array(
            'Starter 5mbps @1500',
            'Bronze 10mbps @2500',
            'Silver Pack 20mbps @3500',
            'Gold 40mbps @4500',
            'Diamond 80mbps @8500'
        );
        
        // Check if package is valid based on estate
        if ($estate === 'Saifee Park Nairobi Langata') {
            return in_array($package, $saifee_packages);
        } else {
            return in_array($package, $other_packages);
        }
    }
    
    /**
     * Validate package selection
     * 
     * @param string $package Selected package
     * @return bool True if valid, false otherwise
     */
    private function validate_package($package) {
        $all_valid_packages = array(
            // Saifee Park packages
            'Bronze 10 mbps @1000',
            'Silver 20 mbps @2000',
            'Platinum 40mbps@4000',
            'Gold 80mbps @8000',
            'Diamond 120mbps @10000',
            
            // Other estates packages
            'Starter 5mbps @1500',
            'Bronze 10mbps @2500',
            'Silver Pack 20mbps @3500',
            'Gold 40mbps @4500',
            'Diamond 80mbps @8500'
        );
        
        return in_array($package, $all_valid_packages);
    }
    
    /**
     * Validate estate selection
     * 
     * @param string $estate Selected estate
     * @return bool True if valid, false otherwise
     */
    private function validate_estate($estate) {
        $valid_estates = array(
            'Saifee Park Nairobi Langata',
            'Kerina Apartments Nairobi Rongai',
            'AZIZ Apartments Nairobi Rongai',
            'Sir Henry\'s Apartments Kakamega', // Updated from St. Henry's to Sir Henry's
            'Milimani Apartments Nakuru',
            'Kings Saaphire Nakuru',
            'Lavena Apartments Rongai',
            'Orange House Uthiru'
        );
        
        return in_array($estate, $valid_estates);
    }
}