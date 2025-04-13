<?php
/**
 * Plugin Name: Internet Service Booking
 * Description: A booking system for internet service installation with date/time selection and external API integration.
 * Version: 1.0.0
 * Author: Michael Mwanzia
 * Text Domain: internet-service-booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ISB_VERSION', '1.0.0');
define('ISB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ISB_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Internet_Service_Booking {
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once ISB_PLUGIN_DIR . 'includes/class-database-handler.php';
        require_once ISB_PLUGIN_DIR . 'includes/class-form-handler.php';
        require_once ISB_PLUGIN_DIR . 'includes/class-api-integration.php';
        require_once ISB_PLUGIN_DIR . 'includes/class-availability-manager.php';

        // Admin classes
        if (is_admin()) {
            require_once ISB_PLUGIN_DIR . 'admin/class-admin-menu.php';
            require_once ISB_PLUGIN_DIR . 'admin/class-bookings-list.php';
        }
    }

    /**
     * Register all hooks
     */
    private function register_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Shortcode for the booking form
        add_shortcode('internet_service_booking', array($this, 'render_booking_form'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Debug log
        error_log('Activating Internet Service Booking plugin');
        
        // Create database tables
        $db_handler = new ISB_Database_Handler();
        error_log('Creating database tables');
        $db_handler->create_tables();
        error_log('Database tables created');
        
        // Create initial time slots if needed
        $availability = new ISB_Availability_Manager();
        error_log('Initializing time slots');
        $availability->initialize_time_slots();
        error_log('Time slots initialized');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Render booking form
     */
    public function render_booking_form($atts) {
        // Enqueue necessary scripts
        wp_enqueue_script('isb-booking-form');
        wp_enqueue_style('isb-booking-styles');
        
        // Start output buffering
        ob_start();
        
        // Include form template
        include ISB_PLUGIN_DIR . 'public/views/booking-form.php';
        
        // Return buffered content
        return ob_get_clean();
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Register CSS
        wp_register_style(
            'isb-booking-styles',
            ISB_PLUGIN_URL . 'public/css/booking-styles.css',
            array(),
            ISB_VERSION
        );

        // Register JavaScript
        wp_register_script(
            'isb-booking-form',
            ISB_PLUGIN_URL . 'public/js/booking-calendar.js',
            array('jquery', 'jquery-ui-datepicker'),
            ISB_VERSION,
            true
        );
        
        // Add validation script
        wp_register_script(
            'isb-form-validation',
            ISB_PLUGIN_URL . 'public/js/form-validation.js',
            array('jquery'),
            ISB_VERSION,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('isb-booking-form', 'isb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('isb/v1/'), // Ensure this includes the trailing slash
            'nonce' => wp_create_nonce('wp_rest'),
        ));
    }

/**
 * Register REST API endpoints
 */
public function register_rest_routes() {
    // Register endpoint for checking time slot availability
    register_rest_route('isb/v1', '/availability/(?P<date>\d{4}-\d{2}-\d{2})/(?P<estate>[a-zA-Z0-9\s%]+)', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_available_time_slots'),
        'permission_callback' => '__return_true',
    ));
    
    // Register endpoint for form submission
    register_rest_route('isb/v1', '/booking', array(
        'methods' => 'POST',
        'callback' => array($this, 'process_booking_rest'),
        'permission_callback' => '__return_true',
    ));
}

/**
 * REST API callback for getting available time slots
 */
public function get_available_time_slots($request) {
    $date = sanitize_text_field($request['date']);
    $estate = sanitize_text_field(urldecode($request['estate']));
    
    $availability = new ISB_Availability_Manager();
    $time_slots = $availability->get_available_slots($date, $estate);
    
    return rest_ensure_response($time_slots);
}
    
/**
 * REST API callback for processing bookings
 */
public function process_booking_rest($request) {
    error_log('======= REST BOOKING FORM SUBMISSION ========');
    error_log('Request params: ' . print_r($request->get_params(), true));
    
    try {
        // Extract booking data
        $params = $request->get_params();
        $booking_data = array(
            'full_name' => isset($params['full_name']) ? sanitize_text_field($params['full_name']) : '',
            'phone_number' => isset($params['phone_number']) ? sanitize_text_field($params['phone_number']) : '',
            'email' => isset($params['email']) ? sanitize_email($params['email']) : '',
            'kra_pin' => isset($params['kra_pin']) ? sanitize_text_field($params['kra_pin']) : '',
            'estate' => isset($params['estate']) ? sanitize_text_field($params['estate']) : '',
            'block_number' => isset($params['block_number']) ? sanitize_text_field($params['block_number']) : '',
            'house_number' => isset($params['house_number']) ? sanitize_text_field($params['house_number']) : '',
            'package' => isset($params['package']) ? sanitize_text_field($params['package']) : '',
            'wifi_username' => isset($params['wifi_username']) ? sanitize_text_field($params['wifi_username']) : '',
            'wifi_password' => isset($params['wifi_password']) ? sanitize_text_field($params['wifi_password']) : '',
            'booking_date' => isset($params['booking_date']) ? sanitize_text_field($params['booking_date']) : '',
            'booking_time' => isset($params['booking_time']) ? sanitize_text_field($params['booking_time']) : '',
            'status' => 'confirmed'
        );
        
        error_log('Processed booking data: ' . print_r($booking_data, true));
        
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
            
            // ADDED: Send data to webhook
            $webhook_url = 'https://turbonetsolutions.co.ke/webhook.php';
            error_log('Sending data to webhook: ' . $webhook_url);
            
            wp_remote_post($webhook_url, array(
                'method' => 'POST',
                'timeout' => 5,
                'blocking' => false, // Non-blocking
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($booking_data),
                'sslverify' => false
            ));
            
            // Return success response
            return rest_ensure_response(array(
                'success' => true,
                'booking_id' => $booking_id,
                'message' => 'Booking successful!'
            ));
        } else {
            error_log('Failed to insert booking: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error, array('status' => 500));
        }
    } catch (Exception $e) {
        error_log('Exception in REST booking process: ' . $e->getMessage());
        return new WP_Error('server_error', 'Server error: ' . $e->getMessage(), array('status' => 500));
    }
}
    
    /**
     * REST API callback for processing bookings (original version)
     */
    public function process_booking($request) {
        $form_handler = new ISB_Form_Handler();
        $result = $form_handler->process_submission($request->get_params());
        
        if (is_wp_error($result)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $result->get_error_message()
            ));
        }
        
        // Send data to external API if booking was successful
        $api = new ISB_API_Integration();
        $api_result = $api->send_booking_data($result);
        
        return rest_ensure_response(array(
            'success' => true,
            'booking_id' => $result,
            'api_response' => $api_result
        ));
    }
}

// Initialize the plugin
function isb_init() {
    return Internet_Service_Booking::get_instance();
}
add_action('plugins_loaded', 'isb_init');