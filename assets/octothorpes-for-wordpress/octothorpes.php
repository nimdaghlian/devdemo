<?php
/*
Plugin Name: Octothorpes for Wordpress
Description: Injects custom <script> and <link> tags into the head of all pages from multiple servers and adds custom elements based on tags.
Version: 1.0.0
Author: The Idea Store - ideastore.dev
*/

// Function to inject custom code into the head
function octothorpes_head_injection() {
    // Retrieve server addresses from plugin settings
    $server_addresses_string = get_option('octothorpes_server_addresses');
    $server_addresses = explode(',', $server_addresses_string);

    // Ensure each server address ends with a "/"
    foreach ($server_addresses as &$server_address) {
        if (substr($server_address, -1) !== '/') {
            $server_address .= '/';
        }
    }
    unset($server_address); // Break the reference with the last element
    echo '<link rel="stylesheet" href="https://octothorp.es/tag.css">';
    // Output custom <script> tag with the whole comma-separated string of server addresses as data-register
    echo '<script async defer type="module" data-register="' . esc_attr(implode(',', $server_addresses)) . '" src="https://octothorp.es/tag.js"></script>';

    // Output custom <link> tag for each server
    foreach ($server_addresses as $server_address) {
        echo '<link rel="preload" as="fetch" href="' . esc_url($server_address) . '?uri=' . get_page_link().'">';
    }

    // Output custom <link> tags for each tag on the page
    if (is_single()) {
        global $post;
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {

                        echo '<link rel="octo:octothorpes" href="'.$tag->name.'">';
                }
            }
        }
    }
add_action('wp_head', 'octothorpes_head_injection');

// Function to add settings page to the admin menu
function octothorpes_settings_menu() {
    add_options_page('Octothorpes Settings', 'Octothorpes', 'manage_options', 'octothorpes_settings', 'octothorpes_settings_page');
}
add_action('admin_menu', 'octothorpes_settings_menu');

// Function to generate the settings page
function octothorpes_settings_page() {
    ?>
    <div class="wrap">
        <h1>Octothorpes Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('octothorpes_settings_group'); ?>
            <?php do_settings_sections('octothorpes_settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Function to register plugin settings
function octothorpes_register_settings() {
    register_setting('octothorpes_settings_group', 'octothorpes_server_addresses');
    add_settings_section('octothorpes_settings_section', 'Server Settings', 'octothorpes_settings_section_callback', 'octothorpes_settings');
    add_settings_field('octothorpes_server_addresses', 'Server Addresses', 'octothorpes_server_addresses_callback', 'octothorpes_settings', 'octothorpes_settings_section');
}
add_action('admin_init', 'octothorpes_register_settings');

// Callback function for the settings section
function octothorpes_settings_section_callback() {
    echo '<p>Enter the addresses of your servers where the custom scripts and stylesheets are hosted. Separate multiple addresses with commas.</p>';
}

// Callback function for the server addresses field
function octothorpes_server_addresses_callback() {
    $server_addresses_string = get_option('octothorpes_server_addresses');
    echo '<input type="text" name="octothorpes_server_addresses" value="' . esc_attr($server_addresses_string) . '" />';
}

// Function to output tag data and inject JavaScript in the footer
function octothorpes_output_tag_data() {
    if (is_single()) {
        global $post;
        $tags = get_the_tags($post->ID);
        if ($tags) {
            $tag_names = array();
            foreach ($tags as $tag) {
                $tag_names[] = $tag->name;
            }
            $tag_names_json = json_encode($tag_names);
            echo "<script>
                var octothorpesTags = $tag_names_json;

                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof octothorpesTags !== 'undefined') {
                        var tagContainers = document.querySelectorAll('div.taxonomy-post_tag');
                        
                        tagContainers.forEach(function(tagContainer) {
                            var octoThorpesContainer = document.createElement('div');
                            octoThorpesContainer.classList.add('octothorpes');
                            
                            var headline = document.createElement('h3');
                            headline.textContent = 'Octothorpes on the Web';
                            octoThorpesContainer.appendChild(headline);
                            
                            octothorpesTags.forEach(function(tagName) {
                                var octoThorpeElement = document.createElement('octo-thorpe');
                                octoThorpeElement.textContent = tagName;
                                octoThorpesContainer.appendChild(octoThorpeElement);
                            });

                            tagContainer.appendChild(document.createElement('hr'));
                            tagContainer.appendChild(octoThorpesContainer);
                        });
                    }
                });
            </script>";
        }
    }
}
add_action('wp_footer', 'octothorpes_output_tag_data');
