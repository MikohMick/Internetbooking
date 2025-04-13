<?php
/**
 * Class for handling database operations
 */
class ISB_Database_Handler {
/**
 * Create plugin tables
 */
public function create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $bookings_table = $wpdb->prefix . 'isb_bookings';
    $time_slots_table = $wpdb->prefix . 'isb_time_slots';
    
    // SQL for bookings table
    $bookings_sql = "CREATE TABLE IF NOT EXISTS $bookings_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        email VARCHAR(100) NOT NULL,
        kra_pin VARCHAR(50) NOT NULL,
        estate VARCHAR(100) NOT NULL,
        block_number VARCHAR(20) NOT NULL,
        house_number VARCHAR(20) NOT NULL,
        package VARCHAR(50) NOT NULL,
        wifi_username VARCHAR(50) NOT NULL,
        wifi_password VARCHAR(50) NOT NULL,
        booking_date DATE NOT NULL,
        booking_time VARCHAR(20) NOT NULL,
        status VARCHAR(20) DEFAULT 'confirmed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    // SQL for time slots table - Adding estate column
    $time_slots_sql = "CREATE TABLE IF NOT EXISTS $time_slots_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NULL,
        booking_date DATE NOT NULL,
        time_slot VARCHAR(20) NOT NULL,
        estate VARCHAR(100) NOT NULL,
        is_booked BOOLEAN DEFAULT FALSE,
        UNIQUE KEY estate_date_time (estate, booking_date, time_slot)
    ) $charset_collate;";
    
    // Include WordPress database upgrade functions
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Create/update tables
    dbDelta($bookings_sql);
    dbDelta($time_slots_sql);
    
    // Store the current database version
    update_option('isb_db_version', ISB_VERSION);
}
    
    /**
     * Insert a new booking
     * 
     * @param array $booking_data Booking information
     * @return int|false Booking ID if successful, false otherwise
     */
    public function insert_booking($booking_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_bookings';
        
        // Format the data
        $data = array(
            'full_name' => sanitize_text_field($booking_data['full_name']),
            'phone_number' => sanitize_text_field($booking_data['phone_number']),
            'email' => sanitize_email($booking_data['email']),
            'kra_pin' => sanitize_text_field($booking_data['kra_pin']),
            'estate' => sanitize_text_field($booking_data['estate']),
            'block_number' => sanitize_text_field($booking_data['block_number']),
            'house_number' => sanitize_text_field($booking_data['house_number']),
            'package' => sanitize_text_field($booking_data['package']),
            'wifi_username' => sanitize_text_field($booking_data['wifi_username']),
            'wifi_password' => sanitize_text_field($booking_data['wifi_password']),
            'booking_date' => sanitize_text_field($booking_data['booking_date']),
            'booking_time' => sanitize_text_field($booking_data['booking_time']),
            'status' => 'pending'
        );
        
        $format = array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        );
        
        // Insert the booking
        $result = $wpdb->insert($table_name, $data, $format);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get all bookings or a specific booking
     * 
     * @param int|null $booking_id Optional booking ID
     * @return array Array of bookings or a specific booking
     */
    public function get_bookings($booking_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_bookings';
        
        if ($booking_id !== null) {
            // Get a specific booking
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $booking_id
            ), ARRAY_A);
        } else {
            // Get all bookings
            return $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY booking_date, booking_time",
                ARRAY_A
            );
        }
    }
    
    /**
     * Update booking status
     * 
     * @param int $booking_id Booking ID
     * @param string $status New status
     * @return bool True if successful, false otherwise
     */
    public function update_booking_status($booking_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_bookings';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => sanitize_text_field($status)),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        return ($result !== false);
    }
}