jQuery(document).ready(function($) {
    'use strict';

    // Initialize color pickers
    $('.color-picker').wpColorPicker();

    // Regenerate API key
    $('#regenerate_api_key').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to regenerate the API key? This will invalidate the current key.')) {
            return;
        }

        $.ajax({
            url: KindlinksAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'kindlinks_regenerate_api_key',
                nonce: KindlinksAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#api_key').val(response.data.api_key);
                    alert('API key regenerated successfully!');
                } else {
                    alert('Failed to regenerate API key.');
                }
            }
        });
    });

    // Confirm bulk delete
    $('.delete').on('click', function() {
        return confirm('Are you sure you want to delete this term?');
    });
});

