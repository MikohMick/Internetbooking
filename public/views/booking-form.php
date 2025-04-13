<?php
/**
 * Template for rendering the booking form
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="isb-booking-form-container">
    <form id="isb-booking-form" class="isb-form">
        <?php wp_nonce_field('isb_booking_nonce', 'isb_nonce'); ?>
        
        <div class="isb-form-section">
            <h3><?php _e('Personal Information', 'internet-service-booking'); ?></h3>
            
            <div class="isb-form-row">
                <div class="isb-form-field">
                    <label for="full_name"><?php _e('Full Name', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <input type="text" name="full_name" id="full_name" required>
                </div>

					<div class="isb-form-field">
						<label for="phone_number"><?php _e('Phone Number', 'internet-service-booking'); ?> <span class="required">*</span></label>
						<input type="tel" name="phone_number" id="phone_number" required>
						<small>Enter a phone number that will be used for <em>WhatsApp notifications</em></small>
					</div>
            </div>
            
            <div class="isb-form-row">
                <div class="isb-form-field">
                    <label for="email"><?php _e('Email', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <input type="email" name="email" id="email" required>
                </div>
                
                <div class="isb-form-field">
                    <label for="kra_pin"><?php _e('KRA PIN Number', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <input type="text" name="kra_pin" id="kra_pin" required>
                </div>
            </div>
        </div>
        
        <div class="isb-form-section">
            <h3><?php _e('Property Information', 'internet-service-booking'); ?></h3>
            
            <div class="isb-form-row">
                <div class="isb-form-field">
                    <label for="estate"><?php _e('Estate', 'internet-service-booking'); ?> <span class="required">*</span></label>
<!-- Update this part in booking-form.php -->
<select name="estate" id="estate" required>
    <option value=""><?php _e('-- Select Estate --', 'internet-service-booking'); ?></option>
    <option value="Saifee Park Nairobi Langata"><?php _e('Saifee Park Nairobi Langata', 'internet-service-booking'); ?></option>
    <option value="Kerina Apartments Nairobi Rongai"><?php _e('Kerina Apartments Nairobi Rongai', 'internet-service-booking'); ?></option>
    <option value="AZIZ Apartments Nairobi Rongai"><?php _e('AZIZ Apartments Nairobi Rongai', 'internet-service-booking'); ?></option>
    <option value="Sir Henry's Apartments Kakamega"><?php _e('Sir Henry\'s Apartments Kakamega', 'internet-service-booking'); ?></option>
    <option value="Milimani Apartments Nakuru"><?php _e('Milimani Apartments Nakuru', 'internet-service-booking'); ?></option>
    <option value="Kings Saaphire Nakuru"><?php _e('Kings Saaphire Nakuru', 'internet-service-booking'); ?></option>
    <option value="Lavena Apartments Rongai"><?php _e('Lavena Apartments Rongai', 'internet-service-booking'); ?></option>
    <option value="Orange House Uthiru"><?php _e('Orange House Uthiru', 'internet-service-booking'); ?></option>
</select>
                </div>
                
                <div class="isb-form-field">
                    <label for="block_number"><?php _e('Block Number', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <input type="text" name="block_number" id="block_number" required>
                </div>
            </div>
            
            <div class="isb-form-row">
                <div class="isb-form-field">
                    <label for="house_number"><?php _e('House Number', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <input type="text" name="house_number" id="house_number" required>
                </div>
            </div>
        </div>
        
        <div class="isb-form-section">
            <h3><?php _e('Service Package', 'internet-service-booking'); ?></h3>
            
            <div class="isb-form-row">
                <div class="isb-form-field">
                    <label for="package"><?php _e('Package', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <select name="package" id="package" required>
 <option value=""><?php _e('-- Select Package --', 'internet-service-booking'); ?></option>
                        <option value="Bronze 10 mbps @1000"><?php _e('Bronze 10 mbps @1000', 'internet-service-booking'); ?></option>
                        <option value="Silver 20 mbps @2000"><?php _e('Silver 20 mbps @2000', 'internet-service-booking'); ?></option>
                        <option value="Platinum 40mbps@4000"><?php _e('Platinum 40mbps@4000', 'internet-service-booking'); ?></option>
                        <option value="Gold 80mbps @8000"><?php _e('Gold 80mbps @8000', 'internet-service-booking'); ?></option>
                        <option value="Diamond 120mbps @10000"><?php _e('Diamond 120mbps @10000', 'internet-service-booking'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="isb-form-row">
                <div class="isb-form-field">
                    <label for="wifi_username"><?php _e('WiFi Username', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <input type="text" name="wifi_username" id="wifi_username" required>
                </div>
                
                <div class="isb-form-field">
                    <label for="wifi_password"><?php _e('WiFi Password', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <input type="text" name="wifi_password" id="wifi_password" required>
                    <small><?php _e('Minimum 8 characters with at least one uppercase letter, one lowercase letter, and one number', 'internet-service-booking'); ?></small>
                </div>
            </div>
        </div>
        
        <div class="isb-form-section">
            <h3><?php _e('Installation Schedule', 'internet-service-booking'); ?></h3>
            
            <div class="isb-form-row">
                <div class="isb-form-field">
                    <label for="booking_date"><?php _e('Select Date', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <input type="text" name="booking_date" id="booking_date" class="isb-datepicker" readonly required>
                </div>
                
                <div class="isb-form-field">
                    <label for="booking_time"><?php _e('Select Time Slot', 'internet-service-booking'); ?> <span class="required">*</span></label>
                    <select name="booking_time" id="booking_time" required disabled>
                        <option value=""><?php _e('-- Select a date first --', 'internet-service-booking'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="isb-form-actions">
            <button type="submit" class="isb-submit-button"><?php _e('Schedule Installation', 'internet-service-booking'); ?></button>
        </div>
        
        <div id="isb-form-messages" class="isb-form-messages"></div>
    </form>
</div>