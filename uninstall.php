<?php
/**
 * Fired during plugin uninstallation.
 *
 * @package Kindlinks_Glossary
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete the custom table
$table_name = $wpdb->prefix . 'kindlinks_glossary';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete the plugin options
delete_option('kindlinks_glossary_db_version');
delete_option('kindlinks_glossary_max_limit');
delete_option('kindlinks_glossary_content_selectors');
delete_option('kindlinks_glossary_underline_color');
delete_option('kindlinks_glossary_hover_bg_color');
delete_option('kindlinks_glossary_tooltip_keyword_color');
delete_option('kindlinks_glossary_enabled_post_types');
delete_option('kindlinks_glossary_enabled_categories');
delete_option('kindlinks_glossary_api_key');

// Delete the cached transient
delete_transient('kindlinks_glossary_terms');

// Delete all post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_kindlinks_glossary_disabled'");

// If multisite, delete options for all sites
if (is_multisite()) {
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    $original_blog_id = get_current_blog_id();

    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        
        $table_name = $wpdb->prefix . 'kindlinks_glossary';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        
        delete_option('kindlinks_glossary_db_version');
        delete_option('kindlinks_glossary_max_limit');
        delete_option('kindlinks_glossary_content_selectors');
        delete_option('kindlinks_glossary_underline_color');
        delete_option('kindlinks_glossary_hover_bg_color');
        delete_option('kindlinks_glossary_tooltip_keyword_color');
        delete_option('kindlinks_glossary_enabled_post_types');
        delete_option('kindlinks_glossary_enabled_categories');
        delete_option('kindlinks_glossary_api_key');
        delete_transient('kindlinks_glossary_terms');
        
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_kindlinks_glossary_disabled'");
    }

    switch_to_blog($original_blog_id);
}




