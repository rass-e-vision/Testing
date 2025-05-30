<?php
// Enqueue parent and child theme styles
add_action( 'wp_enqueue_scripts', function() {
    // Enqueue parent style
    wp_enqueue_style( 'astra-parent-style', get_template_directory_uri() . '/style.css' );
    // Enqueue child style
    wp_enqueue_style( 'astra-child-style', get_stylesheet_uri(), array('astra-parent-style'), wp_get_theme()->get('Version') );
    // Enqueue custom script.js from child theme
    wp_enqueue_script( 'astra-child-script', get_stylesheet_directory_uri() . '/script.js', array(), wp_get_theme()->get('Version'), true );
});

// Register Custom Post Type: Projects
add_action('init', function() {
    // Set up labels for the Projects post type
    $labels = array(
        'name'               => 'Projects',
        'singular_name'      => 'Project',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Project',
        'edit_item'          => 'Edit Project',
        'new_item'           => 'New Project',
        'view_item'          => 'View Project',
        'search_items'       => 'Search Projects',
        'not_found'          => 'No projects found',
        'not_found_in_trash' => 'No projects found in Trash',
        'all_items'          => 'All Projects',
        'menu_name'          => 'Projects',
        'name_admin_bar'     => 'Project',
    );

    // Register the Projects post type
    register_post_type('projects', array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'projects'),
        'menu_icon' => 'dashicons-portfolio', // WordPress dashicon
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'show_in_rest' => true, // Enable Gutenberg/REST API
    ));
}); 