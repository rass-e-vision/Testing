<?php

class HTML_To_Elementor {
    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load any additional files here
    }

    /**
     * Register all of the hooks related to the admin area
     */
    private function define_admin_hooks() {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add menu items to the admin area
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'HTML to Elementor',
            'HTML to Elementor',
            'manage_options',
            'html-to-elementor',
            array($this, 'display_plugin_admin_page'),
            'dashicons-editor-code',
            100
        );
    }

    /**
     * Register the stylesheets for the admin area
     */
    public function enqueue_admin_styles() {
        wp_enqueue_style(
            'html-to-elementor-admin',
            HTML_TO_ELEMENTOR_PLUGIN_URL . 'admin/css/html-to-elementor-admin.css',
            array(),
            HTML_TO_ELEMENTOR_VERSION,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_script(
            'html-to-elementor-admin',
            HTML_TO_ELEMENTOR_PLUGIN_URL . 'admin/js/html-to-elementor-admin.js',
            array('jquery'),
            HTML_TO_ELEMENTOR_VERSION,
            false
        );
    }

    /**
     * Render the admin page
     */
    public function display_plugin_admin_page() {
        include_once HTML_TO_ELEMENTOR_PLUGIN_DIR . 'admin/partials/html-to-elementor-admin-display.php';
    }

    /**
     * Run the plugin
     */
    public function run() {
        // Plugin initialization code
    }
} 