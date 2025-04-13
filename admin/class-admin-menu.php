<?php
/**
 * Class for managing admin menu and settings
 */
class ISB_Admin_Menu {
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Internet Service Booking', 'internet-service-booking'),
            __('ISB', 'internet-service-booking'),
            'manage_options',
            'internet-service-booking',
            array($this, 'render_bookings_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Bookings submenu
        add_submenu_page(
            'internet-service-booking',
            __('Bookings', 'internet-service-booking'),
            __('Bookings', 'internet-service-booking'),
            'manage_options',
            'internet-service-booking',
            array($this, 'render_bookings_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'internet-service-booking',
            __('Settings', 'internet-service-booking'),
            __('Settings', 'internet-service-booking'),
            'manage_options',
            'isb-settings',
            array($this, 'render_settings_page')
        );
        
        // Synchronize submenu
        add_submenu_page(
            'internet-service-booking',
            __('Synchronize Slots', 'internet-service-booking'),
            __('Synchronize Slots', 'internet-service-booking'),
            'manage_options',
            'isb-synchronize',
            array($this, 'render_sync_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // API settings section
        add_settings_section(
            'isb_api_settings',
            __('API Integration Settings', 'internet-service-booking'),
            array($this, 'render_api_settings_section'),
            'isb-settings'
        );
        
        // API endpoint
        register_setting(
            'isb_settings',
            'isb_api_endpoint',
            array(
                'sanitize_callback' => 'esc_url_raw',
                'default' => ''
            )
        );
        
        add_settings_field(
            'isb_api_endpoint',
            __('API Endpoint URL', 'internet-service-booking'),
            array($this, 'render_api_endpoint_field'),
            'isb-settings',
            'isb_api_settings'
        );
        
        // API key
        register_setting(
            'isb_settings',
            'isb_api_key',
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );
        
        add_settings_field(
            'isb_api_key',
            __('API Key', 'internet-service-booking'),
            array($this, 'render_api_key_field'),
            'isb-settings',
            'isb_api_settings'
        );
        
        // General settings section
        add_settings_section(
            'isb_general_settings',
            __('General Settings', 'internet-service-booking'),
            array($this, 'render_general_settings_section'),
            'isb-settings'
        );
        
        // Booking window
        register_setting(
            'isb_settings',
            'isb_booking_window',
            array(
                'sanitize_callback' => 'absint',
                'default' => 30
            )
        );
        
        add_settings_field(
            'isb_booking_window',
            __('Booking Window (days)', 'internet-service-booking'),
            array($this, 'render_booking_window_field'),
            'isb-settings',
            'isb_general_settings'
        );
    }
    
    /**
     * Render bookings page
     */
    public function render_bookings_page() {
        // Initialize bookings list table
        $bookings_list = new ISB_Bookings_List();
        $bookings_list->prepare_items();
        
        // Include template
        include ISB_PLUGIN_DIR . 'admin/views/bookings-page.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Include template
        include ISB_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
    
    /**
     * Render synchronize utility page
     */
    public function render_sync_page() {
        // Process synchronization if requested
        if (isset($_POST['isb_sync_slots']) && check_admin_referer('isb_sync_action')) {
            $synchronized = $this->synchronize_time_slots();
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(__('Synchronized %d time slots successfully.', 'internet-service-booking'), $synchronized);
            echo '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Synchronize Time Slots', 'internet-service-booking'); ?></h1>
            <p><?php _e('Use this utility to synchronize time slots with bookings. This will release any time slots that are marked as booked but have no associated booking.', 'internet-service-booking'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('isb_sync_action'); ?>
                <input type="hidden" name="isb_sync_slots" value="1">
                <?php submit_button(__('Synchronize Now', 'internet-service-booking'), 'primary', 'submit', true); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Synchronize time slots with bookings
     * 
     * @return int Number of synchronized time slots
     */
    public function synchronize_time_slots() {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'isb_bookings';
        $slots_table = $wpdb->prefix . 'isb_time_slots';
        
        // Find all time slots that are marked as booked
        $booked_slots = $wpdb->get_results(
            "SELECT id, booking_date, time_slot, booking_id FROM $slots_table WHERE is_booked = 1",
            ARRAY_A
        );
        
        $synchronized = 0;
        
        // Check each booked slot to see if the associated booking exists
        foreach ($booked_slots as $slot) {
            if (!empty($slot['booking_id'])) {
                // Check if the booking exists
                $booking_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $bookings_table WHERE id = %d",
                    $slot['booking_id']
                ));
                
                // If booking doesn't exist, release the time slot
                if ($booking_exists == 0) {
                    $wpdb->update(
                        $slots_table,
                        array(
                            'is_booked' => 0,
                            'booking_id' => null
                        ),
                        array('id' => $slot['id']),
                        array('%d', '%s'),
                        array('%d')
                    );
                    $synchronized++;
                    error_log("Released orphaned time slot: {$slot['booking_date']} {$slot['time_slot']} with booking_id: {$slot['booking_id']}");
                }
            } else {
                // If booking_id is null but slot is marked as booked, that's an error - fix it
                $wpdb->update(
                    $slots_table,
                    array('is_booked' => 0),
                    array('id' => $slot['id']),
                    array('%d'),
                    array('%d')
                );
                $synchronized++;
                error_log("Released time slot with null booking_id: {$slot['booking_date']} {$slot['time_slot']}");
            }
        }
        
        // Also check for any bookings that don't have a corresponding booked time slot
        $bookings = $wpdb->get_results(
            "SELECT id, booking_date, booking_time FROM $bookings_table 
             WHERE status NOT IN ('cancelled', 'failed')",
            ARRAY_A
        );
        
        foreach ($bookings as $booking) {
            // Check if time slot exists and is booked
            $slot = $wpdb->get_row($wpdb->prepare(
                "SELECT id, is_booked, booking_id FROM $slots_table 
                 WHERE booking_date = %s AND time_slot = %s",
                $booking['booking_date'],
                $booking['booking_time']
            ));
            
            if ($slot) {
                // If slot exists but is not booked or has a different booking ID
                if ($slot->is_booked == 0 || $slot->booking_id != $booking['id']) {
                    $wpdb->update(
                        $slots_table,
                        array(
                            'is_booked' => 1,
                            'booking_id' => $booking['id']
                        ),
                        array('id' => $slot->id),
                        array('%d', '%d'),
                        array('%d')
                    );
                    $synchronized++;
                    error_log("Updated time slot to match booking: {$booking['booking_date']} {$booking['booking_time']} for booking ID: {$booking['id']}");
                }
            } else {
                // If the time slot doesn't exist, create it
                $wpdb->insert(
                    $slots_table,
                    array(
                        'booking_date' => $booking['booking_date'],
                        'time_slot' => $booking['booking_time'],
                        'is_booked' => 1,
                        'booking_id' => $booking['id']
                    ),
                    array('%s', '%s', '%d', '%d')
                );
                $synchronized++;
                error_log("Created missing time slot: {$booking['booking_date']} {$booking['booking_time']} for booking ID: {$booking['id']}");
            }
        }
        
        return $synchronized;
    }
    
    /**
     * Render API settings section
     */
    public function render_api_settings_section() {
        echo '<p>' . __('Configure the external API integration for sending booking data.', 'internet-service-booking') . '</p>';
    }
    
    /**
     * Render general settings section
     */
    public function render_general_settings_section() {
        echo '<p>' . __('Configure general booking settings.', 'internet-service-booking') . '</p>';
    }
    
    /**
     * Render API endpoint field
     */
    public function render_api_endpoint_field() {
        $value = get_option('isb_api_endpoint', '');
        echo '<input type="url" name="isb_api_endpoint" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('Enter the full URL of the API endpoint to send booking data to.', 'internet-service-booking') . '</p>';
    }
    
    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $value = get_option('isb_api_key', '');
        echo '<input type="text" name="isb_api_key" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('Enter the API key for authentication.', 'internet-service-booking') . '</p>';
    }
    
    /**
     * Render booking window field
     */
    public function render_booking_window_field() {
        $value = get_option('isb_booking_window', 30);
        echo '<input type="number" name="isb_booking_window" value="' . esc_attr($value) . '" class="small-text" min="1" max="90">';
        echo '<p class="description">' . __('Number of days in advance that users can book appointments.', 'internet-service-booking') . '</p>';
    }
}

// Initialize the admin menu
new ISB_Admin_Menu();