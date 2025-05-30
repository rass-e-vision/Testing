jQuery(document).ready(function($) {
    // Helper function to safely extract content
    function extractContent(code, pattern) {
        const match = code.match(pattern);
        return match ? match[0] : '';
    }

    // Helper function to safely extract inner content
    function extractInnerContent(code, pattern) {
        const match = code.match(pattern);
        return match ? match[1] : '';
    }

    // Function to import content to Elementor
    window.importToElementor = function() {
        const output = $('#elementor-output').text();
        if (!output) {
            alert('Please convert your HTML first before importing.');
            return;
        }

        try {
            // Validate JSON
            const elementorData = JSON.parse(output);
            
            // Get import options
            const importType = $('input[name="hte_import_type"]:checked').val() || 'new';
            const postType = $('#hte_post_type').val() || 'post';
            const templateType = $('#hte_template_type').val() || 'page';
            const postId = $('#hte_post_id').val() || '';

            // Create form data
            const formData = new FormData();
            formData.append('action', 'hte_import_to_elementor');
            formData.append('nonce', hte_ajax.nonce);
            formData.append('elementor_data', output);
            formData.append('import_type', importType);
            formData.append('post_type', postType);
            formData.append('template_type', templateType);
            if (postId) {
                formData.append('post_id', postId);
            }

            // Show loading state
            const $button = $('.output-actions .button-primary');
            const originalText = $button.text();
            $button.addClass('converting');
            $button.prop('disabled', true);
            $button.text('Importing...');

            // Send AJAX request
            $.ajax({
                url: hte_ajax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        if (response.data.edit_url) {
                            window.location.href = response.data.edit_url;
                        } else {
                            alert('Content imported successfully!');
                        }
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('An error occurred while importing to Elementor. Please try again.');
                },
                complete: function() {
                    $button.removeClass('converting');
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        } catch (error) {
            console.error('Parse Error:', error);
            alert('Invalid Elementor data. Please try converting again.');
        }
    };

    // Handle preview button click
    $('#preview-button').on('click', function() {
        const code = $('#hte_raw_code').val();
        
        // Extract HTML content
        let htmlContent = extractContent(code, /<html[^>]*>[\s\S]*?<\/html>/i) || 
                         extractContent(code, /<body[^>]*>[\s\S]*?<\/body>/i) || 
                         code;
        
        // Extract CSS content
        const cssContent = extractInnerContent(code, /<style[^>]*>([\s\S]*?)<\/style>/i);
        
        // Extract JavaScript content
        const jsContent = extractInnerContent(code, /<script[^>]*>([\s\S]*?)<\/script>/i);
        
        // Clean HTML content by removing style and script tags
        htmlContent = htmlContent.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
                               .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
        
        // Create preview content
        const previewContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                ${cssContent ? `<style>${cssContent}</style>` : ''}
            </head>
            <body>
                ${htmlContent}
                ${jsContent ? `<script>${jsContent}<\/script>` : ''}
            </body>
            </html>
        `;
        
        // Update preview iframe
        const previewFrame = document.getElementById('preview-frame');
        const frameDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(previewContent);
        frameDoc.close();
        
        // Switch to preview tab
        $('a[href="#preview"]').click();
    });

    // Handle device switcher
    $('.device-button').on('click', function() {
        const width = $(this).data('width');
        const device = $(this).data('device');
        
        $('.device-button').removeClass('active');
        $(this).addClass('active');
        
        const frame = $('#preview-frame');
        if (width === '100%') {
            frame.css('width', '100%');
        } else {
            frame.css('width', width + 'px');
        }
    });

    // Handle form submission
    $('#conversion-form').on('submit', function(e) {
        e.preventDefault();
        
        const code = $('#hte_raw_code').val();
        
        // Extract HTML content for conversion
        let htmlContent = extractContent(code, /<html[^>]*>[\s\S]*?<\/html>/i) || 
                         extractContent(code, /<body[^>]*>[\s\S]*?<\/body>/i) || 
                         code;
        
        // Clean HTML content by removing style and script tags
        htmlContent = htmlContent.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
                               .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
        
        const formData = new FormData();
        formData.append('action', 'hte_convert_html');
        formData.append('nonce', hte_ajax.nonce);
        formData.append('hte_raw_html', htmlContent);
        
        // Show loading state
        const $button = $('#convert-button');
        const originalText = $button.find('.button-text').text();
        $button.addClass('converting');
        $button.prop('disabled', true);
        $button.find('.button-text').text('Converting...');
        
        $.ajax({
            url: hte_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#elementor-output').text(JSON.stringify(response.data, null, 2));
                    $('a[href="#elementor"]').click();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('An error occurred while processing your request.');
            },
            complete: function() {
                $button.removeClass('converting');
                $button.prop('disabled', false);
                $button.find('.button-text').text(originalText);
            }
        });
    });

    // Handle copy to clipboard
    $('.output-actions .button-secondary').on('click', function() {
        const code = $('#elementor-output').text();
        navigator.clipboard.writeText(code).then(function() {
            const $button = $(this);
            const originalText = $button.text();
            $button.text('Copied!');
            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        }.bind(this)).catch(function(err) {
            console.error('Failed to copy text: ', err);
            alert('Failed to copy to clipboard. Please try again.');
        });
    });

    // Handle custom width input
    $('#custom-width').on('input', function() {
        const width = $(this).val();
        if (width >= 200 && width <= 2000) {
            const $frame = $('#preview-frame');
            $frame.removeClass().addClass('preview-frame custom');
            $frame.css('width', width + 'px');
            $frame.attr('data-width', width);
            
            // Remove active state from device buttons
            $('.device-button').removeClass('active');
        }
    });

    // Handle guide toggle
    $('.guide-toggle').on('click', function() {
        const $content = $('.guide-content');
        const isHidden = $content.is(':hidden');
        $content.slideToggle();
        $(this).toggleClass('active');
        $(this).find('.button-text').text(isHidden ? 'Hide Guide' : 'Show Guide');
    });

    // Handle import options
    $('#hte_import_elementor').on('change', function() {
        const $importOptions = $('.import-options');
        $importOptions.slideToggle(this.checked);
        
        if (!this.checked) {
            // Reset all import options
            $('input[name="hte_import_type"]').prop('checked', false);
            $('#hte_post_type, #hte_template_type').prop('disabled', true);
            $('.post-search-container').hide();
        } else {
            // Enable first option by default
            $('input[name="hte_import_type"][value="new"]').prop('checked', true).trigger('change');
        }
    });

    // Handle import type changes
    $('input[name="hte_import_type"]').on('change', function() {
        const $postTypeSelect = $('#hte_post_type');
        const $templateTypeSelect = $('#hte_template_type');
        const $postSearchContainer = $('.post-search-container');
        
        // Reset all selects
        $postTypeSelect.prop('disabled', true);
        $templateTypeSelect.prop('disabled', true);
        $postSearchContainer.hide();
        
        // Enable appropriate select
        switch($(this).val()) {
            case 'new':
                $postTypeSelect.prop('disabled', false);
                break;
            case 'existing':
                $postSearchContainer.show();
                break;
            case 'template':
                $templateTypeSelect.prop('disabled', false);
                break;
        }
    });

    // Tab Navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });
}); 