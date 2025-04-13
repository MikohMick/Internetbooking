<?php
/**
 * Class for managing time slot availability
 */
class ISB_Availability_Manager {
    /**
     * Constructor
     */
    public function __construct() {
        // Nothing to initialize
    }
    
    /**
     * Initialize time slots for future dates
     * 
     * This generates time slots for the next 30 days for all estates
     */
    public function initialize_time_slots() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_time_slots';
        
        // Get today's date
        $today = date('Y-m-d');
        
        // Get list of all estates
        $estates = array(
            'Saifee Park Nairobi Langata',
            'Kerina Apartments Nairobi Rongai',
            'AZIZ Apartments Nairobi Rongai',
            'Sir Henry\'s Apartments Kakamega',
            'Milimani Apartments Nakuru',
            'Kings Saaphire Nakuru',
            'Lavena Apartments Rongai',
            'Orange House Uthiru'
        );
        
        // Generate time slots for the next 30 days for each estate
        foreach ($estates as $estate) {
            for ($i = 0; $i < 30; $i++) {
                $date = date('Y-m-d', strtotime("+$i days", strtotime($today)));
                $day_of_week = date('w', strtotime($date)); // 0 (Sunday) to 6 (Saturday)
                
                // Generate appropriate time slots based on day of week
                if ($day_of_week == 0) { // Sunday
                    $start_hour = 10; // 10am
                    $end_hour = 16; // 4pm
                } else if ($day_of_week == 6) { // Saturday
                    $start_hour = 9; // 9am
                    $end_hour = 16; // 4pm
                } else { // Monday to Friday
                    $start_hour = 8; // 8am
                    $end_hour = 16; // 4pm
                }
                
                // Create hourly time slots
                for ($hour = $start_hour; $hour < $end_hour; $hour++) {
                    $time_slot = sprintf('%02d:00-%02d:00', $hour, $hour + 1);
                    
                    // Check if the slot already exists for this estate
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table_name WHERE booking_date = %s AND time_slot = %s AND estate = %s",
                        $date,
                        $time_slot,
                        $estate
                    ));
                    
                    // Only insert if it doesn't exist and it's not a past time slot for today
                    if (!$existing) {
                        // If it's today, check if the time has passed
                        if ($date == $today) {
                            $current_hour = (int)date('G'); // 24-hour format without leading zeros
                            
                            // Skip if the starting hour has already passed
                            if ($hour <= $current_hour) {
                                continue;
                            }
                        }
                        
                        $wpdb->insert(
                            $table_name,
                            array(
                                'booking_date' => $date,
                                'time_slot' => $time_slot,
                                'estate' => $estate,
                                'is_booked' => false,
                                'booking_id' => null
                            ),
                            array('%s', '%s', '%s', '%d', '%s')
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Get available time slots for a specific date and estate
     * 
     * @param string $date Date in Y-m-d format
     * @param string $estate Estate name
     * @return array Array of available time slots
     */
    public function get_available_slots($date, $estate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_time_slots';
        
        // Log incoming parameters for debugging
        error_log("Getting slots for date: {$date}, estate: {$estate}");
        
        // Check if date is valid
        if (!$this->is_valid_date($date)) {
            error_log("Invalid date: {$date}");
            return array();
        }
        
        // Get current time
        $current_time = current_time('H:i');
        $current_date = current_time('Y-m-d');
        
        // First, check if we need to create time slots for this date and estate
        $existing_slots = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE booking_date = %s AND estate = %s",
            $date,
            $estate
        ));
        
        error_log("Found {$existing_slots} existing slots for date: {$date}, estate: {$estate}");
        
        if ($existing_slots == 0) {
            $this->create_slots_for_estate_date($estate, $date);
            error_log("Created new slots for date: {$date}, estate: {$estate}");
        }
        
        // Prepare SQL query for time slots
        $query = "SELECT id, time_slot FROM $table_name 
                  WHERE booking_date = %s AND estate = %s AND is_booked = 0";
        
        // If today, only show future time slots
        if ($date == $current_date) {
            // Get time slots with start time after current time
            $query .= " AND SUBSTRING_INDEX(time_slot, '-', 1) > %s";
            $slots = $wpdb->get_results($wpdb->prepare(
                $query,
                $date,
                $estate,
                $current_time
            ), ARRAY_A);
        } else {
            // For future dates, get all available slots
            $slots = $wpdb->get_results($wpdb->prepare(
                $query,
                $date,
                $estate
            ), ARRAY_A);
        }
        
        error_log("Returning " . count($slots) . " available slots");
        
        // Order by time
        usort($slots, function($a, $b) {
            return strcmp($a['time_slot'], $b['time_slot']);
        });
        
        return $slots;
    }

    /**
     * Create time slots for a specific estate and date
     * 
     * @param string $estate Estate name
     * @param string $date Date in Y-m-d format
     */
    private function create_slots_for_estate_date($estate, $date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_time_slots';
        
        error_log("Creating slots for estate: {$estate}, date: {$date}");
        
        $day_of_week = date('w', strtotime($date)); // 0 (Sunday) to 6 (Saturday)
        
        // Generate appropriate time slots based on day of week
        if ($day_of_week == 0) { // Sunday
            $start_hour = 10; // 10am
            $end_hour = 16; // 4pm
        } else if ($day_of_week == 6) { // Saturday
            $start_hour = 9; // 9am
            $end_hour = 16; // 4pm
        } else { // Monday to Friday
            $start_hour = 8; // 8am
            $end_hour = 16; // 4pm
        }
        
        // Get current hour if it's today
        $current_hour = (date('Y-m-d') == $date) ? (int)date('G') : 0;
        
        // Create hourly time slots
        for ($hour = $start_hour; $hour < $end_hour; $hour++) {
            // Skip past hours for today
            if (date('Y-m-d') == $date && $hour <= $current_hour) {
                continue;
            }
            
            $time_slot = sprintf('%02d:00-%02d:00', $hour, $hour + 1);
            
            // Check if the slot already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE booking_date = %s AND time_slot = %s AND estate = %s",
                $date,
                $time_slot,
                $estate
            ));
            
            // Only insert if it doesn't exist
            if (!$existing) {
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'booking_date' => $date,
                        'time_slot' => $time_slot,
                        'estate' => $estate,
                        'is_booked' => false,
                        'booking_id' => null
                    ),
                    array('%s', '%s', '%s', '%d', '%s')
                );
                
                if ($result === false) {
                    error_log("Failed to insert time slot: " . $wpdb->last_error);
                } else {
                    error_log("Created time slot: {$date} {$time_slot} for estate: {$estate}");
                }
            }
        }
    }

    /**
     * Book a specific time slot
     * 
     * @param string $date Date in Y-m-d format
     * @param string $time_slot Time slot (e.g., "08:00-09:00")
     * @param string $estate Estate name
     * @param int $booking_id ID of the booking
     * @return bool True if successful, false otherwise
     */
    public function book_time_slot($date, $time_slot, $estate, $booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_time_slots';
        
        // Check if the slot is available
        $slot = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name 
            WHERE booking_date = %s AND time_slot = %s AND estate = %s AND is_booked = 0",
            $date,
            $time_slot,
            $estate
        ));
        
        if (!$slot) {
            error_log("No available slot found for date: {$date}, time: {$time_slot}, estate: {$estate}");
            return false; // Slot not available
        }
        
        // Update the slot to mark it as booked
        $updated = $wpdb->update(
            $table_name,
            array(
                'is_booked' => 1,
                'booking_id' => $booking_id
            ),
            array('id' => $slot->id),
            array('%d', '%d'),
            array('%d')
        );
        
        if ($updated === false) {
            error_log("Failed to update time slot: " . $wpdb->last_error);
        } else {
            error_log("Booked time slot: {$date} {$time_slot} for estate: {$estate}, booking ID: {$booking_id}");
        }
        
        return ($updated !== false);
    }
    
    /**
     * Check if a date is valid for booking
     * 
     * @param string $date Date in Y-m-d format
     * @return bool True if valid, false otherwise
     */
    private function is_valid_date($date) {
        // Convert to timestamp
        $date_timestamp = strtotime($date);
        
        // Get current date timestamp (start of day)
        $today = strtotime(date('Y-m-d'));
        
        // Check if date is valid and today or in the future
        return ($date_timestamp && $date_timestamp >= $today);
    }
    
    /**
     * Add more time slots for future dates
     * 
     * This is used to keep adding future dates as time progresses
     */
    public function add_future_slots($days_to_add = 7) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_time_slots';
        
        // Get list of all estates
        $estates = array(
            'Saifee Park Nairobi Langata',
            'Kerina Apartments Nairobi Rongai',
            'AZIZ Apartments Nairobi Rongai',
            'Sir Henry\'s Apartments Kakamega',
            'Milimani Apartments Nakuru',
            'Kings Saaphire Nakuru',
            'Lavena Apartments Rongai',
            'Orange House Uthiru'
        );
        
        foreach ($estates as $estate) {
            // Get the latest date in the database for this estate
            $latest_date = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(booking_date) FROM $table_name WHERE estate = %s",
                $estate
            ));
            
            if (!$latest_date) {
                // If no dates exist for this estate, use today as the starting point
                $latest_date = date('Y-m-d');
            }
            
            // Start from the day after the latest date
            $start_date = date('Y-m-d', strtotime("+1 day", strtotime($latest_date)));
            
            // Generate time slots for additional days
            for ($i = 0; $i < $days_to_add; $i++) {
                $date = date('Y-m-d', strtotime("+$i days", strtotime($start_date)));
                $this->create_slots_for_estate_date($estate, $date);
            }
        }
    }
    
    /**
     * Release a time slot (mark as available)
     * 
     * @param string $date Date in Y-m-d format
     * @param string $time_slot Time slot (e.g., "08:00-09:00")
     * @param string $estate Estate name
     * @param int|null $booking_id Optional booking ID to ensure correct release
     * @return bool True if successful, false otherwise
     */
    public function release_time_slot($date, $time_slot, $estate, $booking_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'isb_time_slots';
        
        // Prepare where clause
        $where = array(
            'booking_date' => $date,
            'time_slot' => $time_slot,
            'estate' => $estate
        );
        
        // Add booking ID to where clause if provided
        if ($booking_id !== null) {
            $where['booking_id'] = $booking_id;
        }
        
        // Update the slot to mark it as available
        $updated = $wpdb->update(
            $table_name,
            array(
                'is_booked' => 0,
                'booking_id' => null
            ),
            $where,
            array('%d', '%s'),
            array_fill(0, count($where), '%s')
        );
        
        if ($updated) {
            error_log("Released time slot: $date $time_slot for estate: $estate" . ($booking_id ? " and booking ID: $booking_id" : ""));
        } else {
            error_log("Failed to release time slot: $date $time_slot for estate: $estate " . $wpdb->last_error);
        }
        
        return ($updated !== false);
    }
}