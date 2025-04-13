<?php
/**
 * Class for handling external API integration
 */
class ISB_API_Integration {
    /**
     * API endpoint URL
     * 
     * @var string
     */
    private $api_endpoint;
    
    /**
     * API authentication key
     * 
     * @var string
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Set default webhook URL if none is configured
        $this->api_endpoint = get_option('isb_api_endpoint', 'https://turbonetsolutions.co.ke/webhook/webhook.php');
        $this->api_key = get_option('isb_api_key', '');
    }
    
    /**
     * Send booking data to external API
     * 
     * @param int $booking_id Booking ID
     * @return array|WP_Error API response or WP_Error on failure
     */
    public function send_booking_data($booking_id) {
        // Skip if API endpoint is not configured
        if (empty($this->api_endpoint)) {
            return new WP_Error('api_not_configured', 'External API is not configured.');
        }
        
        // Get booking data
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_bookings';
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $booking_id
        ), ARRAY_A);
        
        if (!$booking) {
            return new WP_Error('booking_not_found', 'Booking not found.');
        }
        
        // Format time for better readability
        $formatted_time = $this->format_time_slot($booking['booking_time']);
        
        // Prepare data for API
        $api_data = array(
            'booking_id' => $booking_id,
            'customer_name' => $booking['full_name'],
            'phone_number' => $booking['phone_number'],
            'email' => $booking['email'],
            'kra_pin' => $booking['kra_pin'],
            'estate' => $booking['estate'],
            'block_number' => $booking['block_number'],
            'house_number' => $booking['house_number'],
            'package' => $booking['package'],
            'wifi_username' => $booking['wifi_username'],
            'wifi_password' => $booking['wifi_password'],
            'installation_date' => $booking['booking_date'],
            'installation_time' => $booking['booking_time'],
            'formatted_time' => $formatted_time,
            'status' => $booking['status'],
            'created_at' => $booking['created_at'],
            'webhook_timestamp' => current_time('mysql'),
            'source' => 'internet-service-booking-plugin'
        );
        
        // Log the data we're about to send
        error_log('Sending webhook data to ' . $this->api_endpoint);
        error_log('Webhook payload: ' . json_encode($api_data, JSON_PRETTY_PRINT));
        
        // Send data to API
        $response = wp_remote_post($this->api_endpoint, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'X-Webhook-Source' => 'wp-isb-plugin'
            ),
            'body' => json_encode($api_data),
            'cookies' => array()
        ));
        
        // Check if request was successful
        if (is_wp_error($response)) {
            error_log('Webhook error: ' . $response->get_error_message());
            return $response;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('Webhook response code: ' . $response_code);
        error_log('Webhook response: ' . $response_body);
        
        if ($response_code < 200 || $response_code >= 300) {
            return new WP_Error(
                'api_error',
                'API returned error: ' . wp_remote_retrieve_response_message($response)
            );
        }
        
        // Parse response body
        $result = json_decode($response_body, true);
        
        return $result;
    }
    
    /**
     * Format time slot for better readability
     * 
     * @param string $time_slot Time slot in format "HH:MM-HH:MM"
     * @return string Formatted time with AM/PM
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
     * Save API settings
     * 
     * @param string $endpoint API endpoint URL
     * @param string $key API key
     * @return bool True if successful, false otherwise
     */
    public function save_api_settings($endpoint, $key) {
        $updated = true;
        
        // Validate and save endpoint
        if (!empty($endpoint) && filter_var($endpoint, FILTER_VALIDATE_URL)) {
            update_option('isb_api_endpoint', sanitize_url($endpoint));
        } else {
            $updated = false;
        }
        
        // Save API key
        if (!empty($key)) {
            update_option('isb_api_key', sanitize_text_field($key));
        } else {
            $updated = false;
        }
        
        return $updated;
    }
    
    /**
     * Get API settings
     * 
     * @return array API settings
     */
    public function get_api_settings() {
        return array(
            'endpoint' => $this->api_endpoint,
            'key' => $this->api_key
        );
    }
}