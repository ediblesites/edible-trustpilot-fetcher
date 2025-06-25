jQuery(document).ready(function($) {
    $('#add-business-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('#submit');
        var $message = $('#form-message');
        
        // Disable submit button and show loading
        $submit.prop('disabled', true).val('Adding Business...');
        $message.html('<div class="notice notice-info"><p>Adding business and starting initial scrape...</p></div>');
        
        // Get form data
        var formData = {
            action: 'add_trustpilot_business',
            nonce: $('#trustpilot_nonce').val(),
            business_title: $('#business_title').val(),
            business_url: $('#business_url').val()
        };
        
        // Send AJAX request
        $.post(trustpilot_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                $form[0].reset();
                
                // Redirect to businesses list after 2 seconds
                setTimeout(function() {
                    window.location.href = 'edit.php?post_type=tp_businesses';
                }, 2000);
            } else {
                $message.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
            }
        }).fail(function() {
            $message.html('<div class="notice notice-error"><p>Network error occurred. Please try again.</p></div>');
        }).always(function() {
            // Re-enable submit button
            $submit.prop('disabled', false).val('Add Business & Start Scraping');
        });
    });
}); 