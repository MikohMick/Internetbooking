<?php
/**
 * Admin view for plugin settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php _e('Internet Service Booking Settings', 'internet-service-booking'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        // Output security fields
        settings_fields('isb_settings');
        
        // Output setting sections
        do_settings_sections('isb-settings');
        
        // Submit button
        submit_button();
        ?>
    </form>
    
    <div class="isb-settings-info">
        <h2><?php _e('Shortcode Usage', 'internet-service-booking'); ?></h2>
        <p><?php _e('To display the booking form on any page or post, use the following shortcode:', 'internet-service-booking'); ?></p>
        <code>[internet_service_booking]</code>
        
        <h2><?php _e('REST API Integration', 'internet-service-booking'); ?></h2>
        <p><?php _e('The plugin provides REST API endpoints for integration with external systems:', 'internet-service-booking'); ?></p>
        
        <table class="widefat" style="max-width: 600px;">
            <thead>
                <tr>
                    <th><?php _e('Endpoint', 'internet-service-booking'); ?></th>
                    <th><?php _e('Method', 'internet-service-booking'); ?></th>
                    <th><?php _e('Description', 'internet-service-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>/wp-json/isb/v1/availability/{date}</code></td>
                    <td><code>GET</code></td>
                    <td><?php _e('Get available time slots for a specific date', 'internet-service-booking'); ?></td>
                </tr>
                <tr>
                    <td><code>/wp-json/isb/v1/booking</code></td>
                    <td><code>POST</code></td>
                    <td><?php _e('Submit a booking', 'internet-service-booking'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.isb-settings-info {
    margin-top: 30px;
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.isb-settings-info h2 {
    margin-top: 0;
    font-size: 18px;
}

.isb-settings-info p {
    margin-bottom: 15px;
}

.isb-settings-info code {
    padding: 5px 10px;
    background: #f5f5f5;
    border-radius: 3px;
}

.isb-settings-info table {
    margin-top: 15px;
}

.isb-settings-info th {
    text-align: left;
    padding: 10px;
}
</style>