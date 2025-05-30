<?php
/**
 * Plugin Name: HTML to Elementor Converter
 * Description: Converts raw HTML/CSS to Elementor-compatible containers/widgets.
 * Version: 0.1
 * Author: Your Name
 */

defined('ABSPATH') or die('No script kiddies please!');

// Check if Elementor is active
function hte_is_elementor_active() {
    return did_action('elementor/loaded');
}

// Get supported post types
function hte_get_supported_post_types() {
    $post_types = get_post_types(array(
        'public' => true,
        'show_in_rest' => true,
    ), 'objects');

    // Remove unsupported post types
    unset($post_types['attachment']);
    unset($post_types['revision']);
    unset($post_types['nav_menu_item']);
    unset($post_types['custom_css']);
    unset($post_types['customize_changeset']);
    unset($post_types['oembed_cache']);
    unset($post_types['user_request']);
    unset($post_types['wp_block']);
    unset($post_types['wp_navigation']);

    return $post_types;
}

// Admin page hook
add_action('admin_menu', function () {
    add_menu_page(
        'HTML to Elementor',
        'HTML to Elementor',
        'manage_options',
        'html-to-elementor',
        'hte_render_admin_page',
        'dashicons-editor-code',
        100
    );
});

// Add AJAX handler for post search
add_action('wp_ajax_hte_search_posts', function() {
    check_ajax_referer('hte_search_nonce', 'nonce');

    $search = sanitize_text_field($_POST['search']);
    $post_types = array_keys(hte_get_supported_post_types());

    $posts = get_posts(array(
        'post_type' => $post_types,
        'posts_per_page' => 10,
        's' => $search,
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    $results = array();
    foreach ($posts as $post) {
        $results[] = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'type' => get_post_type_object($post->post_type)->labels->singular_name,
            'status' => get_post_status_object($post->post_status)->label,
        );
    }

    wp_send_json_success($results);
});

// Add AJAX handler for HTML conversion
add_action('wp_ajax_hte_convert_html', function() {
    check_ajax_referer('hte_convert_nonce', 'nonce');

    $html = isset($_POST['hte_raw_html']) ? wp_kses_post($_POST['hte_raw_html']) : '';
    $preserve_styles = isset($_POST['hte_preserve_styles']);
    $create_sections = isset($_POST['hte_create_sections']);
    $enable_dynamic = isset($_POST['hte_enable_dynamic']);

    if (empty($html)) {
        wp_send_json_error('Please enter some HTML to convert.');
        return;
    }

    try {
        // Convert HTML to Elementor format
        $converted = hte_convert_to_elementor($html, $preserve_styles, $create_sections, $enable_dynamic);
        
        if (empty($converted)) {
            throw new Exception('Conversion failed. Please check your HTML input.');
        }

        // Parse the JSON to ensure it's valid
        $parsed = json_decode($converted);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON generated: ' . json_last_error_msg());
        }

        wp_send_json_success($parsed);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

function hte_render_admin_page() {
    // Check if Elementor is active
    if (!hte_is_elementor_active()) {
        ?>
        <div class="notice notice-warning">
            <p><strong>Warning:</strong> Elementor plugin is not active. Please install and activate Elementor to use this converter.</p>
        </div>
        <?php
        return;
    }
    ?>
    <div class="wrap">
        <h1>HTML to Elementor Converter</h1>
        
        <!-- Tabs Navigation -->
        <nav class="nav-tab-wrapper">
            <a href="#input" class="nav-tab nav-tab-active">Input</a>
            <a href="#preview" class="nav-tab">Live Preview</a>
            <a href="#elementor" class="nav-tab">Elementor Output</a>
        </nav>

        <!-- Input Tab -->
        <div id="input" class="tab-content active">
            <form method="post" id="conversion-form">
                <?php wp_nonce_field('hte_convert_nonce', 'hte_nonce'); ?>
                
                <div class="form-group">
                    <label for="hte_raw_code" class="required-field">Paste your code here:</label>
                    <textarea name="hte_raw_code" id="hte_raw_code" class="code-input" required placeholder="Paste your HTML, CSS, and JavaScript code here..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" id="preview-button" class="button button-secondary">Preview</button>
                    <button type="submit" id="convert-button" class="button button-primary">
                        <span class="button-text">Convert to Elementor</span>
                        <span class="loading-spinner"></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Preview Tab -->
        <div id="preview" class="tab-content">
            <div class="preview-controls">
                <div class="device-switcher">
                    <button class="device-button active" data-device="desktop" data-width="100%">
                        <span class="dashicons dashicons-desktop"></span>
                        Desktop
                    </button>
                    <button class="device-button" data-device="tablet" data-width="768">
                        <span class="dashicons dashicons-tablet"></span>
                        Tablet
                    </button>
                    <button class="device-button" data-device="mobile" data-width="375">
                        <span class="dashicons dashicons-smartphone"></span>
                        Mobile
                    </button>
                </div>
            </div>
            <div class="preview-container">
                <iframe id="preview-frame" class="preview-frame"></iframe>
            </div>
        </div>

        <!-- Elementor Output Tab -->
        <div id="elementor" class="tab-content">
            <div class="output-actions">
                <div class="output-buttons">
                    <button type="button" class="button button-secondary copy-button" onclick="copyToClipboard('elementor-output')">
                        <span class="dashicons dashicons-clipboard"></span>
                        Copy to Clipboard
                    </button>
                    <button type="button" class="button button-primary" onclick="importToElementor()">
                        <span class="dashicons dashicons-download"></span>
                        Import to Elementor
                    </button>
                </div>
                <div class="copy-notice" style="display: none;">
                    <span class="dashicons dashicons-yes"></span>
                    Copied to clipboard!
                </div>
            </div>
            <div class="output-container">
                <pre id="elementor-output" class="elementor-code"></pre>
            </div>
        </div>
    </div>

    <style>
        .output-actions {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .output-buttons {
            display: flex;
            gap: 10px;
        }
        .copy-button {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .copy-notice {
            color: #46b450;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        .output-container {
            position: relative;
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 15px;
        }
        .elementor-code {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 500px;
            overflow-y: auto;
        }
    </style>

    <script>
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent;
        
        navigator.clipboard.writeText(text).then(function() {
            const notice = document.querySelector('.copy-notice');
            notice.style.display = 'flex';
            setTimeout(function() {
                notice.style.display = 'none';
            }, 2000);
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
            alert('Failed to copy to clipboard. Please try again.');
        });
    }
    </script>
    <?php
}

/**
 * Create a new post with Elementor data
 * 
 * @param string $elementor_data The Elementor JSON data
 * @param string $post_type The post type
 * @return int|false The post ID on success, false on failure
 */
function hte_create_elementor_post($elementor_data, $post_type = 'post') {
    // Create post object
    $post_data = array(
        'post_title'    => 'HTML Import ' . current_time('Y-m-d H:i:s'),
        'post_content'  => '',
        'post_status'   => 'draft',
        'post_type'     => $post_type,
    );

    // Insert the post
    $post_id = wp_insert_post($post_data);

    if (!is_wp_error($post_id)) {
        // Add Elementor data
        update_post_meta($post_id, '_elementor_data', $elementor_data);
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');
        update_post_meta($post_id, '_elementor_template_type', $post_type);
        
        return $post_id;
    }

    return false;
}

/**
 * Create a new Elementor template
 * 
 * @param string $elementor_data The Elementor JSON data
 * @param string $template_type The template type (page, section, container)
 * @return int|false The template ID on success, false on failure
 */
function hte_create_elementor_template($elementor_data, $template_type = 'page') {
    // Create template post
    $post_data = array(
        'post_title'    => 'HTML Import Template ' . current_time('Y-m-d H:i:s'),
        'post_content'  => '',
        'post_status'   => 'publish',
        'post_type'     => 'elementor_library',
    );

    // Insert the template
    $template_id = wp_insert_post($post_data);

    if (!is_wp_error($template_id)) {
        // Add Elementor data
        update_post_meta($template_id, '_elementor_data', $elementor_data);
        update_post_meta($template_id, '_elementor_edit_mode', 'builder');
        update_post_meta($template_id, '_elementor_template_type', $template_type);
        
        return $template_id;
    }

    return false;
}

/**
 * Update an existing post with Elementor data
 * 
 * @param int $post_id The post ID to update
 * @param string $elementor_data The Elementor JSON data
 * @return int|false The post ID on success, false on failure
 */
function hte_update_elementor_post($post_id, $elementor_data) {
    if (!get_post($post_id)) {
        return false;
    }

    // Update Elementor data
    update_post_meta($post_id, '_elementor_data', $elementor_data);
    update_post_meta($post_id, '_elementor_edit_mode', 'builder');
    
    return $post_id;
}

/**
 * Get Elementor editor URL for a post
 * 
 * @param int $post_id The post ID
 * @return string The Elementor editor URL
 */
function hte_get_elementor_editor_url($post_id) {
    return add_query_arg(
        array(
            'post' => $post_id,
            'action' => 'elementor',
        ),
        admin_url('post.php')
    );
}

/**
 * Convert HTML to Elementor format
 * 
 * @param string $html The HTML content to convert
 * @param bool $preserve_styles Whether to preserve inline styles
 * @param bool $create_sections Whether to create sections for major blocks
 * @param bool $enable_dynamic Whether to enable dynamic content conversion
 * @return string The converted Elementor JSON
 * @throws Exception If HTML parsing fails
 */
function hte_convert_to_elementor($html, $preserve_styles = true, $create_sections = true, $enable_dynamic = false) {
    // Validate input
    if (empty($html)) {
        throw new Exception('No HTML content provided.');
    }

    // Load DOMDocument
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    
    // Add a root element if not present
    if (!preg_match('/<html[^>]*>/', $html)) {
        $html = '<html><body>' . $html . '</body></html>';
    }
    
    // Try to load the HTML
    $load_result = $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    if (!$load_result) {
        throw new Exception('Failed to parse HTML content. Please check your input.');
    }

    // Initialize Elementor content array
    $elementor_content = array();

    // Get the body content
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        throw new Exception('No body content found in HTML.');
    }

    // Process each node in the body
    foreach ($body->childNodes as $node) {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            // Create a section for each top-level element
            $section = array(
                'id' => uniqid(),
                'elType' => 'section',
                'settings' => array(
                    'layout' => 'boxed',
                    'gap' => 'no',
                    'height' => 'default',
                    'structure' => '20',
                    'content_width' => 'full',
                    'width' => 'full',
                ),
                'elements' => array()
            );

            // Process the node based on its type
            switch (strtolower($node->nodeName)) {
                case 'div':
                    if ($node->getAttribute('class') === 'section') {
                        // Process section content
                        foreach ($node->childNodes as $sectionNode) {
                            if ($sectionNode->nodeType === XML_ELEMENT_NODE) {
                                if ($sectionNode->getAttribute('class') === 'container') {
                                    $column = hte_create_column();
                                    foreach ($sectionNode->childNodes as $containerNode) {
                                        if ($containerNode->nodeType === XML_ELEMENT_NODE) {
                                            $element = hte_process_element($containerNode, $preserve_styles, $enable_dynamic);
                                            if ($element) {
                                                $column['elements'][] = $element;
                                            }
                                        }
                                    }
                                    $section['elements'][] = $column;
                                }
                            }
                        }
                    } else {
                        // Process regular div as a container
                        $column = hte_create_column();
                        $element = hte_process_element($node, $preserve_styles, $enable_dynamic);
                        if ($element) {
                            $column['elements'][] = $element;
                        }
                        $section['elements'][] = $column;
                    }
                    break;

                case 'section':
                    // Process section element
                    $column = hte_create_column();
                    foreach ($node->childNodes as $sectionNode) {
                        if ($sectionNode->nodeType === XML_ELEMENT_NODE) {
                            $element = hte_process_element($sectionNode, $preserve_styles, $enable_dynamic);
                            if ($element) {
                                $column['elements'][] = $element;
                            }
                        }
                    }
                    $section['elements'][] = $column;
                    break;

                default:
                    // Process other elements
                    $column = hte_create_column();
                    $element = hte_process_element($node, $preserve_styles, $enable_dynamic);
                    if ($element) {
                        $column['elements'][] = $element;
                    }
                    $section['elements'][] = $column;
                    break;
            }

            if (!empty($section['elements'])) {
                $elementor_content[] = $section;
            }
        }
    }

    if (empty($elementor_content)) {
        throw new Exception('No valid HTML elements found in the input.');
    }

    return json_encode($elementor_content, JSON_PRETTY_PRINT);
}

function hte_create_section() {
    return array(
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => array(
            'layout' => 'boxed',
            'gap' => 'no',
            'height' => 'default',
            'custom_height' => array(
                'unit' => 'px',
                'size' => '',
            ),
            'structure' => '20',
        ),
        'elements' => array(),
    );
}

function hte_create_column() {
    return array(
        'id' => uniqid(),
        'elType' => 'column',
        'settings' => array(
            '_column_size' => 100,
        ),
        'elements' => array(),
    );
}

function hte_process_element($node, $preserve_styles, $enable_dynamic) {
    $element = array(
        'id' => uniqid(),
        'elType' => 'widget',
        'settings' => array(),
    );

    // Get element classes
    $classes = $node->getAttribute('class');
    $class_array = explode(' ', $classes);

    // Get element attributes
    $attributes = array();
    foreach ($node->attributes as $attr) {
        $attributes[$attr->name] = $attr->value;
    }

    // Process inline styles
    $inline_styles = array();
    if ($node->hasAttribute('style')) {
        $style_string = $node->getAttribute('style');
        $styles = explode(';', $style_string);
        foreach ($styles as $style) {
            if (strpos($style, ':') !== false) {
                list($property, $value) = explode(':', $style);
                $property = trim($property);
                $value = trim($value);
                $inline_styles[$property] = $value;
            }
        }
    }

    // Process based on element type and classes
    switch (strtolower($node->nodeName)) {
        case 'a':
            // Always convert links to buttons if they have any styling or button classes
            if (strpos($classes, 'button') !== false || 
                strpos($classes, 'btn') !== false || 
                isset($inline_styles['background-color']) || 
                isset($inline_styles['background']) ||
                isset($inline_styles['padding']) ||
                isset($inline_styles['border']) ||
                isset($inline_styles['border-radius'])) {
                
                $element['widgetType'] = 'button';
                $element['settings'] = array(
                    'text' => trim($node->textContent),
                    'link' => array(
                        'url' => $node->getAttribute('href'),
                        'is_external' => strpos($node->getAttribute('href'), 'http') === 0 ? 'yes' : 'no',
                        'nofollow' => '',
                    ),
                    'align' => isset($inline_styles['text-align']) ? $inline_styles['text-align'] : 'left',
                    'button_type' => 'info',
                    'size' => 'md',
                    'typography_typography' => 'custom',
                    'typography_font_family' => 'inherit',
                    'typography_font_size' => array(
                        'unit' => 'px',
                        'size' => isset($inline_styles['font-size']) ? intval($inline_styles['font-size']) : 16,
                    ),
                    'typography_font_weight' => isset($inline_styles['font-weight']) ? $inline_styles['font-weight'] : '400',
                    'typography_text_transform' => isset($inline_styles['text-transform']) ? $inline_styles['text-transform'] : 'none',
                    'typography_font_style' => isset($inline_styles['font-style']) ? $inline_styles['font-style'] : 'normal',
                    'typography_text_decoration' => isset($inline_styles['text-decoration']) ? $inline_styles['text-decoration'] : 'none',
                    'typography_line_height' => array(
                        'unit' => 'em',
                        'size' => isset($inline_styles['line-height']) ? floatval($inline_styles['line-height']) : 1.5,
                    ),
                    'typography_letter_spacing' => array(
                        'unit' => 'px',
                        'size' => isset($inline_styles['letter-spacing']) ? intval($inline_styles['letter-spacing']) : 0,
                    ),
                    'button_text_color' => isset($inline_styles['color']) ? $inline_styles['color'] : '#ffffff',
                    'background_color' => isset($inline_styles['background-color']) ? $inline_styles['background-color'] : '#61ce70',
                    'hover_color' => isset($inline_styles['background-color']) ? $inline_styles['background-color'] : '#61ce70',
                    'hover_background_color' => isset($inline_styles['background-color']) ? $inline_styles['background-color'] : '#61ce70',
                    'hover_animation' => 'grow',
                );

                // Process button styles
                if (isset($inline_styles['background-color'])) {
                    $element['settings']['background_color'] = $inline_styles['background-color'];
                    $element['settings']['hover_background_color'] = $inline_styles['background-color'];
                }
                if (isset($inline_styles['color'])) {
                    $element['settings']['button_text_color'] = $inline_styles['color'];
                    $element['settings']['hover_color'] = $inline_styles['color'];
                }
                if (isset($inline_styles['border-radius'])) {
                    $radius = str_replace('px', '', $inline_styles['border-radius']);
                    $element['settings']['border_radius'] = array(
                        'unit' => 'px',
                        'top' => $radius,
                        'right' => $radius,
                        'bottom' => $radius,
                        'left' => $radius,
                    );
                }
                if (isset($inline_styles['padding'])) {
                    $padding = explode(' ', $inline_styles['padding']);
                    $element['settings']['button_padding'] = array(
                        'unit' => 'px',
                        'top' => $padding[0] ?? '10',
                        'right' => $padding[1] ?? '20',
                        'bottom' => $padding[2] ?? '10',
                        'left' => $padding[3] ?? '20',
                    );
                }
                if (isset($inline_styles['border'])) {
                    $element['settings']['border_border'] = 'solid';
                    $element['settings']['border_width'] = array(
                        'unit' => 'px',
                        'top' => '1',
                        'right' => '1',
                        'bottom' => '1',
                        'left' => '1',
                    );
                    $element['settings']['border_color'] = $inline_styles['border-color'] ?? '#000000';
                }

                // Add hover effects
                $element['settings']['hover_animation'] = 'grow';
                if (isset($inline_styles['transition'])) {
                    $element['settings']['button_transition_duration'] = array(
                        'unit' => 'ms',
                        'size' => 300,
                    );
                }

                // Ensure button is visible
                $element['settings']['button_type'] = 'info';
                $element['settings']['size'] = 'md';
                $element['settings']['selected_icon'] = '';
                $element['settings']['icon_align'] = 'left';
                $element['settings']['button_css_id'] = '';
                $element['settings']['button_css_classes'] = $classes;
            } else {
                // Convert regular links to text editor with link
                $element['widgetType'] = 'text-editor';
                $element['settings'] = array(
                    'text' => '<a href="' . esc_url($node->getAttribute('href')) . '">' . $node->textContent . '</a>',
                );
            }
            break;

        case 'div':
            // Check if it's a container or has background
            if (in_array('container', $class_array) || 
                isset($inline_styles['background-color']) || 
                isset($inline_styles['background-image'])) {
                
                $element['elType'] = 'section';
                $element['settings'] = array(
                    'layout' => 'boxed',
                    'gap' => 'no',
                    'height' => 'default',
                    'structure' => '20',
                    'content_width' => 'full',
                    'width' => 'full',
                );

                // Process background
                if (isset($inline_styles['background-color'])) {
                    $element['settings']['background_color'] = $inline_styles['background-color'];
                }
                if (isset($inline_styles['background-image'])) {
                    $bg_image = hte_process_background_image($inline_styles['background-image']);
                    if ($bg_image) {
                        $element['settings']['background_image'] = array(
                            'url' => $bg_image,
                            'id' => '',
                        );
                        $element['settings']['background_type'] = 'classic';
                        $element['settings']['background_position'] = 'center center';
                        $element['settings']['background_size'] = 'cover';
                        $element['settings']['background_repeat'] = 'no-repeat';
                    }
                }

                // Process padding and margin
                if (isset($inline_styles['padding'])) {
                    $padding = explode(' ', $inline_styles['padding']);
                    $element['settings']['padding'] = array(
                        'unit' => 'px',
                        'top' => $padding[0] ?? '0',
                        'right' => $padding[1] ?? '0',
                        'bottom' => $padding[2] ?? '0',
                        'left' => $padding[3] ?? '0',
                    );
                }
                if (isset($inline_styles['margin'])) {
                    $margin = explode(' ', $inline_styles['margin']);
                    $element['settings']['margin'] = array(
                        'unit' => 'px',
                        'top' => $margin[0] ?? '0',
                        'right' => $margin[1] ?? '0',
                        'bottom' => $margin[2] ?? '0',
                        'left' => $margin[3] ?? '0',
                    );
                }
            } else {
                // Convert other divs to text editor
                $element['widgetType'] = 'text-editor';
                $element['settings'] = array(
                    'text' => $node->ownerDocument->saveHTML($node),
                );
            }
            break;

        case 'p':
            $element['widgetType'] = 'text-editor';
            $element['settings'] = array(
                'text' => $node->ownerDocument->saveHTML($node),
                'align' => isset($inline_styles['text-align']) ? $inline_styles['text-align'] : 'left',
            );

            // Process text styles
            if (isset($inline_styles['color'])) {
                $element['settings']['text_color'] = $inline_styles['color'];
            }
            if (isset($inline_styles['font-size'])) {
                $element['settings']['typography_font_size'] = array(
                    'unit' => 'px',
                    'size' => intval($inline_styles['font-size']),
                );
            }
            if (isset($inline_styles['font-weight'])) {
                $element['settings']['typography_font_weight'] = $inline_styles['font-weight'];
            }
            if (isset($inline_styles['line-height'])) {
                $element['settings']['typography_line_height'] = array(
                    'unit' => 'em',
                    'size' => floatval($inline_styles['line-height']),
                );
            }
            break;

        case 'img':
            $element['widgetType'] = 'image';
            $src = $node->getAttribute('src');
            
            // Handle placeholder images
            if (empty($src) || strpos($src, 'placeholder') !== false) {
                $width = $node->getAttribute('width') ?: '800';
                $height = $node->getAttribute('height') ?: '600';
                $src = "https://placehold.co/{$width}x{$height}";
            }

            $element['settings'] = array(
                'image' => array(
                    'url' => $src,
                    'id' => '',
                ),
                'caption' => $node->getAttribute('alt'),
                'align' => in_array('text-center', $class_array) ? 'center' : 'left',
                'width' => array(
                    'unit' => '%',
                    'size' => 100,
                ),
            );

            // Process image styles
            if (isset($inline_styles['max-width'])) {
                $element['settings']['width'] = array(
                    'unit' => 'px',
                    'size' => intval($inline_styles['max-width']),
                );
            }
            break;

        case 'h1':
        case 'h2':
        case 'h3':
        case 'h4':
        case 'h5':
        case 'h6':
            $element['widgetType'] = 'heading';
            $element['settings'] = array(
                'title' => $node->textContent,
                'size' => $node->nodeName,
                'align' => isset($inline_styles['text-align']) ? $inline_styles['text-align'] : 'left',
            );

            // Process heading styles
            if (isset($inline_styles['color'])) {
                $element['settings']['title_color'] = $inline_styles['color'];
            }
            if (isset($inline_styles['font-size'])) {
                $element['settings']['title_size'] = array(
                    'unit' => 'px',
                    'size' => intval($inline_styles['font-size']),
                );
            }
            if (isset($inline_styles['font-weight'])) {
                $element['settings']['typography_font_weight'] = $inline_styles['font-weight'];
            }
            break;

        default:
            // Convert any other element to text editor
            $element['widgetType'] = 'text-editor';
            $element['settings'] = array(
                'text' => $node->ownerDocument->saveHTML($node),
            );
            break;
    }

    // Process dynamic content if enabled
    if ($enable_dynamic && isset($element['settings']['text'])) {
        $element['settings']['text'] = hte_process_dynamic_content($element['settings']['text']);
    }

    // Add custom classes if present
    if (!empty($classes)) {
        $element['settings']['css_classes'] = $classes;
    }

    return $element;
}

function hte_process_dynamic_content($content) {
    // Process dynamic tags
    $content = preg_replace('/{{site_title}}/', '[elementor-tag id="site-title"]', $content);
    $content = preg_replace('/{{post_title}}/', '[elementor-tag id="post-title"]', $content);
    $content = preg_replace('/{{acf\.([^}]+)}}/', '[elementor-tag id="acf-$1"]', $content);
    
    return $content;
}

// Add this new function to handle background images
function hte_process_background_image($style) {
    if (preg_match('/background-image:\s*url\([\'"]?(.*?)[\'"]?\)/', $style, $matches)) {
        $bg_url = $matches[1];
        
        // Handle placeholder images
        if (empty($bg_url) || strpos($bg_url, 'placeholder') !== false) {
            return "https://placehold.co/1920x1080";
        }
        // Handle unsplash images
        elseif (strpos($bg_url, 'unsplash') !== false) {
            return $bg_url;
        }
        // Handle other image sources
        else {
            return "https://placehold.co/1920x1080";
        }
    }
    return '';
}

// Add AJAX handler for importing to Elementor
add_action('wp_ajax_hte_import_to_elementor', function() {
    check_ajax_referer('hte_convert_nonce', 'nonce');

    $elementor_data = isset($_POST['elementor_data']) ? $_POST['elementor_data'] : '';
    $import_type = isset($_POST['import_type']) ? sanitize_text_field($_POST['import_type']) : 'new';
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $template_type = isset($_POST['template_type']) ? sanitize_text_field($_POST['template_type']) : 'page';
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (empty($elementor_data)) {
        wp_send_json_error('No Elementor data provided.');
        return;
    }

    try {
        $result_id = null;

        switch ($import_type) {
            case 'new':
                $result_id = hte_create_elementor_post($elementor_data, $post_type);
                break;
            case 'existing':
                if ($post_id) {
                    $result_id = hte_update_elementor_post($post_id, $elementor_data);
                }
                break;
            case 'template':
                $result_id = hte_create_elementor_template($elementor_data, $template_type);
                break;
        }

        if (!$result_id) {
            throw new Exception('Failed to create or update content.');
        }

        wp_send_json_success(array(
            'post_id' => $result_id,
            'edit_url' => hte_get_elementor_editor_url($result_id)
        ));
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

// Add this new function to process CSS classes
function hte_process_css_classes($classes) {
    $processed_classes = array();
    foreach ($classes as $class) {
        // Process common utility classes
        if (strpos($class, 'text-') === 0) {
            $processed_classes['text_align'] = str_replace('text-', '', $class);
        }
        if (strpos($class, 'bg-') === 0) {
            $processed_classes['background_color'] = str_replace('bg-', '', $class);
        }
        if (strpos($class, 'p-') === 0) {
            $processed_classes['padding'] = str_replace('p-', '', $class);
        }
        if (strpos($class, 'm-') === 0) {
            $processed_classes['margin'] = str_replace('m-', '', $class);
        }
    }
    return $processed_classes;
}

// Add admin scripts and styles
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_html-to-elementor') {
        return;
    }

    // Enqueue admin styles
    wp_enqueue_style(
        'html-to-elementor-admin',
        plugins_url('admin/css/html-to-elementor-admin.css', __FILE__),
        array(),
        '1.0.0'
    );

    // Enqueue admin scripts
    wp_enqueue_script(
        'html-to-elementor-admin',
        plugins_url('admin/js/html-to-elementor-admin.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    // Add AJAX nonce and other data
    wp_localize_script('html-to-elementor-admin', 'hte_ajax', array(
        'nonce' => wp_create_nonce('hte_convert_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}); 