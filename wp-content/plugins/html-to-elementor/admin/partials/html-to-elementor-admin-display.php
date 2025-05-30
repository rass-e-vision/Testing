<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="html-to-elementor-container">
        <div class="html-to-elementor-form">
            <h2>Convert HTML to Elementor</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('html_to_elementor_convert', 'html_to_elementor_nonce'); ?>
                
                <div class="form-group">
                    <label for="html_content">HTML Content:</label>
                    <textarea id="html_content" name="html_content" rows="10" class="large-text code"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="conversion_options">Conversion Options:</label>
                    <div class="options-group">
                        <label>
                            <input type="checkbox" name="preserve_styles" value="1" checked>
                            Preserve inline styles
                        </label>
                        <label>
                            <input type="checkbox" name="create_sections" value="1" checked>
                            Create sections for major blocks
                        </label>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="convert_html" class="button button-primary" value="Convert to Elementor">
                </p>
            </form>
        </div>
        
        <div class="html-to-elementor-result" style="display: none;">
            <h2>Elementor Code</h2>
            <div class="result-content">
                <pre><code id="elementor_output"></code></pre>
                <button class="button button-secondary copy-code">Copy to Clipboard</button>
            </div>
        </div>
    </div>
</div> 