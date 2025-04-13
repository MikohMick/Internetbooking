/**
 * JavaScript for handling the booking calendar functionality
 */
jQuery(document).ready(function($) {
    // Initialize datepicker
    initDatePicker();
    
    // Handle time slot selection
    handleTimeSlotSelection();
    
    // Form submission
    handleFormSubmission();
    
    // Initialize estate-package connection
    initEstatePackageSelection();
    
    /**
     * Initialize the date picker
     */
    function initDatePicker() {
        // First destroy any existing datepickers
        try {
            $('.isb-datepicker').datepicker('destroy');
        } catch (e) {
            console.log('No datepicker to destroy');
        }
        
        // Initialize the datepicker as a popup
        $('.isb-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 0, // Restrict to future dates
            maxDate: '+2m', // Allow booking up to 2 months in advance
            showOn: "focus", // Only show when input is focused
            beforeShowDay: function(date) {
                // All days are available, but Sundays have different hours
                return [true, ''];
            },
            onSelect: function(dateText) {
                // When a date is selected, load available time slots
                loadTimeSlots(dateText);
                
                // Hide the datepicker after selection
                $(this).datepicker('hide');
            },
            // Force the datepicker to appear as popup
            beforeShow: function(input, inst) {
                inst.dpDiv.css({
                    position: 'absolute',
                    top: $(input).offset().top + $(input).outerHeight(),
                    left: $(input).offset().left
                });
            }
        });
        
        // Extra handling to ensure it works as a popup
        $('.isb-datepicker').on('focus', function() {
            $(this).datepicker('show');
        });
    }
    
    /**
     * Load available time slots for the selected date
     *
     * @param {string} date Selected date in YYYY-MM-DD format
     */
    /**
     * Load available time slots for the selected date and estate
     *
     * @param {string} date Selected date in YYYY-MM-DD format
     */
    function loadTimeSlots(date) {
        var timeSlotSelect = $('#booking_time');
        var estateSelect = $('#estate');
        var selectedEstate = estateSelect.val();
        
        // Don't load time slots if no estate is selected
        if (!selectedEstate) {
            timeSlotSelect.empty();
            timeSlotSelect.append($('<option>', {
                value: '',
                text: '-- Select an estate first --'
            }));
            timeSlotSelect.prop('disabled', true);
            return;
        }
        
        // Clear existing options
        timeSlotSelect.empty();
        timeSlotSelect.append($('<option>', {
            value: '',
            text: 'Loading time slots...'
        }));
        
        // Disable the select while loading
        timeSlotSelect.prop('disabled', true);
        
        // Get day of week (0 = Sunday, 1 = Monday, etc.)
        var dayOfWeek = new Date(date).getDay();
        
        // Log the REST URL for debugging
        console.log('REST URL base:', isb_ajax.rest_url);
        
        // Build the correct URL with estate parameter
        var encodedEstate = encodeURIComponent(selectedEstate);
        var apiUrl = isb_ajax.rest_url + 'availability/' + date + '/' + encodedEstate;
        
        console.log('Final API URL:', apiUrl);
        
        // Make AJAX request to get available time slots
        $.ajax({
            url: apiUrl,
            method: 'GET',
            cache: false, // Disable caching
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', isb_ajax.nonce);
            },
            success: function(response) {
                console.log('Time slots response:', response);
                
                // Clear loading option
                timeSlotSelect.empty();
                
                // Remove any previous notification message
                $('#time-slot-message').remove();
                
                // Check if response exists and has data
                if (response && (response.length > 0 || (typeof response === 'object' && !Array.isArray(response)))) {
                    // Add default option
                    timeSlotSelect.append($('<option>', {
                        value: '',
                        text: '-- Select a time slot --'
                    }));
                    
                    // Handle both array and object responses
                    if (Array.isArray(response)) {
                        // Add available time slots (array format)
                        $.each(response, function(index, slot) {
                            // Format time slot to show AM/PM
                            var formattedTime = formatTimeSlot(slot.time_slot);
                            
                            timeSlotSelect.append($('<option>', {
                                value: slot.time_slot,
                                text: formattedTime
                            }));
                        });
                    } else {
                        // Handle if the response is an object with slots property
                        var slots = response.slots || response.time_slots || response;
                        if (Array.isArray(slots) && slots.length > 0) {
                            $.each(slots, function(index, slot) {
                                var timeSlotValue = typeof slot === 'object' ? slot.time_slot : slot;
                                var formattedTime = formatTimeSlot(timeSlotValue);
                                
                                timeSlotSelect.append($('<option>', {
                                    value: timeSlotValue,
                                    text: formattedTime
                                }));
                            });
                        } else {
                            // Try to extract slots from the response object in a different way
                            var foundSlots = false;
                            $.each(response, function(key, value) {
                                if (Array.isArray(value) && value.length > 0) {
                                    foundSlots = true;
                                    $.each(value, function(i, slot) {
                                        var timeSlotValue = typeof slot === 'object' ? slot.time_slot : slot;
                                        var formattedTime = formatTimeSlot(timeSlotValue);
                                        
                                        timeSlotSelect.append($('<option>', {
                                            value: timeSlotValue,
                                            text: formattedTime
                                        }));
                                    });
                                    return false; // Break the loop
                                }
                            });
                            
                            if (!foundSlots) {
                                // No slots found in response object
                                displayNoSlotsMessage(timeSlotSelect);
                                return;
                            }
                        }
                    }
                    
                    // Enable the select if we have options
                    if (timeSlotSelect.find('option').length > 1) {
                        timeSlotSelect.prop('disabled', false);
                        console.log('Time slot select enabled with ' + (timeSlotSelect.find('option').length - 1) + ' slots');
                    } else {
                        // No options added
                        displayNoSlotsMessage(timeSlotSelect);
                    }
                } else {
                    // No available slots or empty response
                    displayNoSlotsMessage(timeSlotSelect);
                }
                
                // Helper function to display no slots message
                function displayNoSlotsMessage(select) {
                    select.empty().append($('<option>', {
                        value: '',
                        text: 'No time slots available'
                    }));
                    
                    select.prop('disabled', true);
                    
                    // Add notification message if it doesn't exist
                    if ($('#time-slot-message').length === 0) {
                        var currentTime = new Date();
                        var message = '';
                        
                        // Check if it's after 4:00 PM
                        if (currentTime.getHours() >= 16) {
                            message = 'Sorry, bookings are not available after 4:00 PM. Please select another date.';
                        } else {
                            message = 'Sorry, all time slots for this date have been booked. Please select another date.';
                        }
                        
                        // Add the message after the time slot dropdown
                        $('<div id="time-slot-message" class="isb-message isb-error-message">' + message + '</div>').insertAfter(select);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading time slots:', error);
                console.log('XHR details:', xhr);
                
                // Handle error
                timeSlotSelect.empty();
                timeSlotSelect.append($('<option>', {
                    value: '',
                    text: 'Failed to load time slots. Please try again.'
                }));
                
                timeSlotSelect.prop('disabled', true);
            }
        });
    }
    
    /**
     * Format time slot to show AM/PM
     * 
     * @param {string} timeSlot Time slot in format "HH:MM-HH:MM"
     * @return {string} Formatted time slot with AM/PM
     */
    function formatTimeSlot(timeSlot) {
        // Example input: "08:00-09:00"
        var times = timeSlot.split('-');
        var startTime = formatTime(times[0]);
        var endTime = formatTime(times[1]);
        return startTime + ' - ' + endTime;
    }
    
    /**
     * Format time to show AM/PM
     * 
     * @param {string} time Time in format "HH:MM"
     * @return {string} Formatted time with AM/PM
     */
    function formatTime(time) {
        // Example input: "08:00" or "14:00"
        var timeParts = time.split(':');
        var hours = parseInt(timeParts[0], 10);
        var minutes = timeParts[1];
        var period = hours >= 12 ? 'PM' : 'AM';
        
        // Convert to 12-hour format
        hours = hours % 12;
        hours = hours ? hours : 12; // Convert '0' to '12'
        
        return hours + ':' + minutes + ' ' + period;
    }
    
    /**
     * Handle time slot selection
     */
    function handleTimeSlotSelection() {
        $('#booking_time').on('change', function() {
            // You can add additional logic here if needed
            // For example, highlighting the selected time slot
        });
    }
    
    /**
     * Handle estate selection to show relevant packages
     */
    function initEstatePackageSelection() {
        // Get the estate and package select elements
        var estateSelect = $('#estate');
        var packageSelect = $('#package');
        
        // Set up the package options for different estates
        var packageOptions = {
            // Saifee Park specific packages
            'Saifee Park Nairobi Langata': [
                { value: 'Bronze 10 mbps @1000', text: 'Bronze 10mbps at 1000' },
                { value: 'Silver 20 mbps @2000', text: 'Silver 20mbps at 2000' },
                { value: 'Platinum 40mbps@4000', text: 'Platinum 40mbps at 4000' },
                { value: 'Gold 80mbps @8000', text: 'Gold 80mbps at 8000' },
                { value: 'Diamond 120mbps @10000', text: 'Diamond 120mbps at 10000' }
            ],
            // Default packages for all other estates
            'default': [
                { value: 'Starter 5mbps @1500', text: 'Starter - 5mbps at 1500' },
                { value: 'Bronze 10mbps @2500', text: 'Bronze - 10mbps at 2500' },
                { value: 'Silver Pack 20mbps @3500', text: 'Silver Pack - 20mbps at 3500' },
                { value: 'Gold 40mbps @4500', text: 'Gold - 40mbps at 4500' },
                { value: 'Diamond 80mbps @8500', text: 'Diamond - 80mbps at 8500' }
            ]
        };
        
        // Function to update package options based on selected estate
        function updatePackageOptions() {
            var selectedEstate = estateSelect.val();
            var options = [];
            
            // If Saifee Park is selected, use its specific packages
            if (selectedEstate === 'Saifee Park Nairobi Langata') {
                options = packageOptions['Saifee Park Nairobi Langata'];
            } else if (selectedEstate) {
                // For any other estate, use the default packages
                options = packageOptions['default'];
            }
            
            // Clear existing options
            packageSelect.empty();
            
            // Add default prompt option
            packageSelect.append($('<option>', {
                value: '',
                text: '-- Select Package --'
            }));
            
            // Add new options based on selected estate
            if (options.length > 0) {
                $.each(options, function(index, option) {
                    packageSelect.append($('<option>', {
                        value: option.value,
                        text: option.text
                    }));
                });
            }
        }
        
        // Update packages when estate selection changes
        estateSelect.on('change', updatePackageOptions);
        
        // Initialize packages on page load
        updatePackageOptions();
    }
    
    /**
     * Handle form submission
     */
    function handleFormSubmission() {
        $('#isb-booking-form').on('submit', function(e) {
            e.preventDefault();
            
            // Disable submit button to prevent double submission
            var submitButton = $(this).find('button[type="submit"]');
            var originalButtonText = submitButton.text();
            submitButton.prop('disabled', true).text('Processing...');
            
            // Clear previous messages
            $('#isb-form-messages').empty();
            
            // Validate required fields
            var valid = true;
            $(this).find('input[required], select[required]').each(function() {
                if (!$(this).val()) {
                    valid = false;
                    var fieldName = $(this).attr('name');
                    console.error('Required field missing:', fieldName);
                    // Highlight the field
                    $(this).addClass('isb-error-field');
                } else {
                    $(this).removeClass('isb-error-field');
                }
            });
            
            if (!valid) {
                // Re-enable the submit button
                submitButton.prop('disabled', false).text(originalButtonText);
                
                // Show error message
                $('#isb-form-messages').html('<div class="isb-message isb-error-message">Please fill in all required fields.</div>');
                return;
            }
            
            // Make sure we have a date and time slot
            if (!$('#booking_date').val() || !$('#booking_time').val()) {
                // Re-enable the submit button
                submitButton.prop('disabled', false).text(originalButtonText);
                
                // Show error message
                $('#isb-form-messages').html('<div class="isb-message isb-error-message">Please select a date and time slot.</div>');
                return;
            }
            
            // Prepare data for submission
            var bookingData = {
                full_name: $('#full_name').val(),
                phone_number: $('#phone_number').val(),
                email: $('#email').val(),
                kra_pin: $('#kra_pin').val(),
                estate: $('#estate').val(),
                block_number: $('#block_number').val(),
                house_number: $('#house_number').val(),
                package: $('#package').val(),
                wifi_username: $('#wifi_username').val(),
                wifi_password: $('#wifi_password').val(),
                booking_date: $('#booking_date').val(),
                booking_time: $('#booking_time').val()
            };
            
            console.log('Submitting booking data via REST API:', bookingData);
            
            // Submit the form via REST API
            $.ajax({
                url: isb_ajax.rest_url + 'booking',
                type: 'POST',
                data: JSON.stringify(bookingData),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', isb_ajax.nonce);
                },
                success: function(response) {
                    console.log('Form submission response:', response);
                    
                    // Re-enable the submit button
                    submitButton.prop('disabled', false).text(originalButtonText);
                    
                    if (response.success) {
                        // Show custom success message
                        $('#isb-form-messages').html('<div class="isb-message isb-success-message">Your booking has been confirmed. Our agents will get in touch with you shortly.</div>');
                        
                        // Reset form
                        $('#isb-booking-form')[0].reset();
                        
                        // Disable time slot select
                        $('#booking_time').prop('disabled', true).empty().append($('<option>', {
                            value: '',
                            text: '-- Select a date first --'
                        }));
                        
                        // Reset package select
                        $('#package').empty().append($('<option>', {
                            value: '',
                            text: '-- Select Package --'
                        }));
                    } else {
                        // Show error message
                        $('#isb-form-messages').html('<div class="isb-message isb-error-message">' + (response.message || 'An unknown error occurred.') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Form submission error:', error);
                    console.log('XHR response:', xhr.responseText);
                    console.log('Status:', status);
                    
                    // Try to parse response if it's JSON
                    var errorMessage = 'There was an error processing your booking. Please try again.';
                    try {
                        if (xhr.responseText) {
                            var jsonResponse = JSON.parse(xhr.responseText);
                            if (jsonResponse.message) {
                                errorMessage = jsonResponse.message;
                            }
                        }
                    } catch (e) {
                        console.log('Could not parse error response:', e);
                    }
                    
                    // Re-enable the submit button
                    submitButton.prop('disabled', false).text(originalButtonText);
                    
                    // Show error message
                    $('#isb-form-messages').html('<div class="isb-message isb-error-message">' + errorMessage + '</div>');
                }
            });
        });
    }
    
    // Add test AJAX function for troubleshooting
    window.testAjax = function() {
        console.log('Running test AJAX call');
        $.ajax({
            url: isb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'isb_test_ajax'
            },
            success: function(response) {
                console.log('Test AJAX success:', response);
            },
            error: function(xhr, status, error) {
                console.error('Test AJAX error:', error);
                console.log('XHR response:', xhr.responseText);
            }
        });
    };
    
    // MOVED INSIDE: When estate selection changes, clear and disable time slots
    $('#estate').on('change', function() {
        var dateField = $('#booking_date');
        
        // If date is already selected, reload time slots for the new estate
        if (dateField.val()) {
            loadTimeSlots(dateField.val());
        } else {
            // Clear and disable time slots if no date is selected
            $('#booking_time').empty().append($('<option>', {
                value: '',
                text: '-- Select a date first --'
            })).prop('disabled', true);
        }
    });
});