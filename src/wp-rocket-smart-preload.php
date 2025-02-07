<?php

/**
 * Plugin Name: WP Rocket - Smart Preload
 * Description: Analyzes your site's traffic and generates a sitemap with the most visited pages to be used in WP Rocket's Preload feature.
 * Version: 0.0.0-dev
 * Plugin URI:  https://github.com/wp-media/wp-rocket-smart-preload
 * Author: WP Rocket Support Team
 * Author URI: https://wp-rocket.me/
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
// Defining constants (DO NOT EDIT UNLESS YOU KNOW WHAT YOU ARE DOING)
if (!defined('RSP_PLUGIN_VERSION')) {
    define('RSP_PLUGIN_VERSION', '0.0.0-dev');
}
if (!defined('RSP_PLUGIN_TABLE')) {
    global $wpdb;
    define('RSP_PLUGIN_TABLE', $wpdb->prefix . 'rsp_page_visits');
}
if (!defined('RSP_SITEMAP_NAME')) {
    define('RSP_SITEMAP_NAME', 'rocket_smart_preload_sitemap');
}
if (!defined('RSP_SITEMAP_FILENAME')) {
    define('RSP_SITEMAP_FILENAME', RSP_SITEMAP_NAME . '.xml');
}
if (!defined('RSP_SITEMAP_REGEX')) {
    define('RSP_SITEMAP_REGEX', '^' . RSP_SITEMAP_NAME . '\.xml$');
}
if (!defined('RSP_SITEMAP_LOCATION')) {
    define('RSP_SITEMAP_LOCATION', ABSPATH . RSP_SITEMAP_FILENAME);
}
require_once 'constants.php';
require_once 'settings-page.php';
require_once 'inc/bot_filter.php';

use function WP_Rocket_Smart_Preload\Utils\Bot_Filter\is_bot;

/**
 * SAFE TO EDIT FILTERS
 */
add_filter('rsp_pages_to_always_include', function ($urls) {
    // Edit this to add a list of URLs that should always be preloaded. (For example, the home page should always be preloaded in most cases)
    $urls[] = untrailingslashit(get_home_url()); // This is the Home page, if you remove this line, the home page will not be preloaded unless it's one of the most visited pages
    // $urls[] = 'https://example.com/page1';
    // $urls[] = 'https://example.com/page2';
    return $urls;
}, 0);
add_filter('rsp_sitemap_page_limit', function ($limit) {
    return $limit; // Edit this number to change the number of pages to be preloaded (Sites with thousands of pages normally have most of those pages with very few visits or no visits at all, so it's recommended to keep this number low. It doesn't make sense to preload pages that are not visited frequently or not visited at all)
}, 0);
add_filter('rsp_cleanup_batch_limit_size', function ($cleanup_limit) {
    return $cleanup_limit; // Edit this number to change the batch size for the cleanup task (Please note that too large batch sizes can cause performance issues in high traffic sites)
}, 0);
add_filter('rsp_cached_sitemap_urls_expiration_time', function ($expiration_time) {
    // Value in seconds
    return $expiration_time; // Edit this number to change the cache expiration time for the sitemap URLs, the smaller the number the more frequently the sitemap can be regenerated
}, 0);
add_filter('rsp_database_table_cleanup_frequency', function ($cleanup_frequency) {
    return $cleanup_frequency; // Edit this to change the frequency of the cleanup task. (Possible values: hourly, twicedaily, daily, weekly) Refer to: https://developer.wordpress.org/reference/functions/wp_get_schedules/
    // Please note that the cleanup task can be a heavy operation in high traffic sites, so it's not recommended to run it too frequently. Daily is a good balance for most sites.
}, 0);
add_filter('rsp_update_preload_table_frequency', function ($update_preload_table_frequency) {
    return $update_preload_table_frequency; // Edit this to change the frequency of the task that updates the preload table. (Possible values: hourly, twicedaily, daily, weekly) Refer to: https://developer.wordpress.org/reference/functions/wp_get_schedules/
}, 0);
add_filter('rsp_deactivate_ip_protection', function ($deactivate_ip_protection) {
    return $deactivate_ip_protection; // Edit this (return true) to deactivate the IP protection feature. (This feature prevents counting fake visits due to multiple page refreshes from the same IP address)
}, 0);
// STOP EDITING


/**
 * Sets a custom sitemap URL for the WP Rocket Smart - Preload plugin.
 *
 * This function allows you to specify a custom URL for the sitemap that the
 * WP Rocket - Smart Preload plugin will use.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_set_custom_sitemap_url()
{
    add_rewrite_rule(RSP_SITEMAP_REGEX, 'index.php?' . RSP_SITEMAP_NAME . '=1', 'top');
}
add_action('init', 'rsp_set_custom_sitemap_url');

/**
 * Adds custom query variables for the sitemap.
 *
 * This function hooks into the 'query_vars' filter to add custom query variables
 * that can be used for the sitemap functionality.
 *
 * @param array $query_vars An array of the current query variables.
 * @return array The modified array of query variables.
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_custom_sitemap_query_vars($query_vars)
{
    $query_vars[] = RSP_SITEMAP_NAME;
    return $query_vars;
}
add_filter('query_vars', 'rsp_custom_sitemap_query_vars');

add_action('template_redirect', 'rsp_custom_sitemap_template_redirect', 0);
/**
 * Handles the custom sitemap template redirect.
 *
 * This function is responsible for redirecting to a custom sitemap template.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_custom_sitemap_template_redirect()
{
    if (get_query_var(RSP_SITEMAP_NAME)) {
        header('Content-Type: application/xml; charset=utf-8');
        $sitemap_page_limit = apply_filters('rsp_sitemap_page_limit', get_option('rsp_sitemap_page_limit', RSP_SITEMAP_PAGE_DEFAULT_LIMIT));
        $sitemap_page_limit = validate_positive_integer($sitemap_page_limit, RSP_SITEMAP_PAGE_DEFAULT_LIMIT);
        echo rsp_get_generated_sitemap($sitemap_page_limit);
        exit;
    }
}

/**
 * Prepares preload tasks for a custom sitemap. (Trucates the table and disabled/enables preload)
 *
 * @param bool $clear_cache Optional. Whether to clear the cache. Default false.
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_prepare_preload_things_for_custom_sitemap($clear_cache = false)
{
    // 1 - truncate cache table upon plugin activation
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpr_rocket_cache';
    $wpdb->query("TRUNCATE TABLE $table_name");

    // 2 clear the cache if needed
    if ($clear_cache && function_exists('rocket_clean_domain')) {
        // Prevent "Undefined function" warning in VSCode with PHP Intelephense extension
        /** @disregard P1010 Undefined function */
        rocket_clean_domain();
    }

    // 3 - Disable and reenable preload, so the sitemap change kicks in immediately

    $options = get_option('wp_rocket_settings', []);

    // disable preload
    $options['manual_preload'] = 0;
    update_option('wp_rocket_settings', $options);

    // enable preload
    $options['manual_preload'] = 1;
    update_option('wp_rocket_settings', $options);
}

/**
 * Display a custom admin notice when WP Rocket is inactive.
 *
 * This function outputs a custom admin notice in the WordPress admin area
 * to inform the user that the WP Rocket plugin is inactive.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_wp_rocket_inactive_custom_admin_notice()
{

    echo '<div class="notice notice-info is-dismissible">
        <p><strong>WP Rocket - Smart Preload: </strong>WP Rocket plugin seems to be inactive. Smart Preload will continue collecting visits to generate the sitemap, so, next time you activate WP Rocket and the preload feature, you will have a ready to use sitemap.</p>
    </div>';
}
add_action('plugins_loaded', function () {
    if (!defined('WP_ROCKET_VERSION') && get_transient('rsp_wp_rocket_deactivated_notice')) {
        add_action('admin_notices', 'rsp_wp_rocket_inactive_custom_admin_notice');
    }
});
// Activation hook to create database table
register_activation_hook(__FILE__, 'rsp_fire_activation_hook_tasks');
/**
 * Function to handle tasks that need to be executed when the plugin is activated.
 *
 * This function is hooked to the activation hook of the plugin and performs
 * necessary setup tasks required for the plugin to function correctly.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_fire_activation_hook_tasks()
{
    global $wpdb;
    $table_name = RSP_PLUGIN_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        page_url TEXT NOT NULL,
        user_ip VARCHAR(45) NOT NULL,
        last_visit TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        visit_count BIGINT UNSIGNED DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY unique_visit (page_url(255), user_ip)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (!wp_next_scheduled('rsp_daily_cleanup_task')) {
        $frequency = apply_filters('rsp_database_table_cleanup_frequency', RSP_DATABASE_TABLE_DEFAULT_CLEANUP_FREQUENCY);
        $frequency = vaidate_accepted_frequencies($frequency);
        wp_schedule_event(time(), $frequency, 'rsp_daily_cleanup_task');
    }
    if (!wp_next_scheduled('rsp_update_preload_table_task')) {
        $frequency = apply_filters('rsp_update_preload_table_frequency', RSP_UPDATE_PRELOAD_TABLE_FREQUENCY);
        $frequency = vaidate_accepted_frequencies($frequency);
        wp_schedule_event(time(), $frequency, 'rsp_update_preload_table_task');
    }
    // Set sitemap URL and flush rewrite rules
    rsp_set_custom_sitemap_url();
    rsp_prepare_preload_things_for_custom_sitemap(true);
    set_transient('rsp_wp_rocket_deactivated_notice', true, 10);
    flush_rewrite_rules();
}
add_action('rsp_update_preload_table_task', 'rsp_prepare_preload_things_for_custom_sitemap');
// Uninstall hook to clean up database table
register_uninstall_hook(__FILE__, 'rsp_uninstall_plugin');
/**
 * Function hooked to the uninstall hook to run clean ups.
 *
 * This function is called when the plugin is uninstalled. It performs necessary
 * cleanup tasks such as removing scheduled tasks, custom tables, and other data related
 * to the plugin.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_uninstall_plugin()
{
    global $wpdb;
    $table_name = RSP_PLUGIN_TABLE;
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    rsp_remove_scheduled_tasks();
    rsp_prepare_preload_things_for_custom_sitemap(true);
    rsp_remove_database_options();
    rsp_remove_transients();
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'rsp_deactivate_plugin');

function rsp_remove_database_options()
{
    delete_option('rsp_pages_to_always_include');
    delete_option('rsp_sitemap_page_limit');
    delete_option('rsp_deactivate_ip_protection');
}
/**
 * Removes transients related to WP Rocket - Smart Preload.
 *
 * This function is responsible for clearing any transients that are used
 * by the WP Rocket - Smart Preload plugin.
 *
 * @return void
 * @since 1.0.2
 * @author Sandy Figueroa
 */
function rsp_remove_transients()
{
    delete_transient('rsp_wp_rocket_deactivated_notice');
    delete_transient('rsp_most_visited_pages');
}
/**
 * Function hooked to the deactivation hook to run necessary clean up tasks.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_deactivate_plugin()
{
    rsp_remove_scheduled_tasks();
    rsp_prepare_preload_things_for_custom_sitemap();
    flush_rewrite_rules();
}
/**
 * Removes scheduled tasks related to the plugin.
 *
 * This function is responsible for unscheduling any tasks that were previously
 * scheduled by the plugin. It ensures that no unnecessary
 * tasks are left running when they are no longer needed.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_remove_scheduled_tasks()
{
    wp_clear_scheduled_hook('rsp_daily_cleanup_task');
    wp_clear_scheduled_hook('rsp_batch_cleanup_task');
    wp_clear_scheduled_hook('rsp_update_preload_table_task');
}
add_action('wp_enqueue_scripts', 'rsp_enqueue_scripts');
/**
 * Enqueues the necessary scripts for the plugin.
 *
 * This function is responsible for adding the required JavaScript
 * to the WordPress site to ensure the plugin functions correctly.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_enqueue_scripts()
{
    // prevent loading on admin, ajax and 404 pages
    if (is_admin() || wp_doing_ajax() || is_404()) {
        return;
    }
    wp_enqueue_script('rsp-tracker', plugin_dir_url(__FILE__) . 'assets/js/rsp-tracker.js', [], RSP_PLUGIN_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
    wp_localize_script('rsp-tracker', 'rsp_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rsp_record_visit_nonce')
    ]);
}

// AJAX handler for recording visits
add_action('wp_ajax_nopriv_rsp_record_visit', 'rsp_record_visit');
add_action('wp_ajax_rsp_record_visit', 'rsp_record_visit');
/**
 * Records a visit to the site.
 *
 * This function is responsible for recording a visit to the site. It may be used
 * to track user activity or for analytics purposes.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_record_visit()
{
    global $wpdb;
    // Bail if it's a bot
    if (is_bot($_SERVER['HTTP_USER_AGENT'])) {
        wp_send_json_error('Skipping bot visit');
    }
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rsp_record_visit_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    if (!isset($_POST['page_url']) || empty($_POST['page_url'])) {
        wp_send_json_error('Invalid page URL');
    }

    $page_url = rsp_sanitize_url($_POST['page_url']); // Sanitize the URL
    if ($page_url === null) {
        wp_send_json_error('Invalid page URL');
    }
    $user_ip = rsp_get_user_ip();
    $table_name = RSP_PLUGIN_TABLE;

    // Check if a record exists
    $existing_visit = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE page_url = %s AND user_ip = %s", $page_url, $user_ip)
    );

    if ($existing_visit) {
        $last_visit_time = strtotime($existing_visit->last_visit);
        $current_time = current_time('timestamp');
        $deactivate_ip_protection = (bool) intval(apply_filters('rsp_deactivate_ip_protection', get_option('rsp_deactivate_ip_protection', 0)));
        // Update last visit timestamp and increment visit count if over an hour since last visit (or if IP protection is deactivated)
        if ($deactivate_ip_protection || (($current_time - $last_visit_time) > RSP_IP_PROTECTION_TIME_THRESHOLD)) {
            $wpdb->update(
                $table_name,
                [
                    'last_visit' => current_time('mysql'),
                    'visit_count' => $existing_visit->visit_count + 1
                ],
                ['id' => $existing_visit->id]
            );
        } else {
            wp_send_json_error('Visit not recorded. Too early to record a new visit from the same IP.');
        }
    } else {
        // Insert new visit record
        $wpdb->insert(
            $table_name,
            [
                'page_url' => $page_url,
                'user_ip' => $user_ip,
                'last_visit' => current_time('mysql'),
                'visit_count' => 1
            ]
        );
    }

    wp_send_json_success('Visit recorded');
}

/**
 * Retrieves the most visited URLs.
 *
 * @param int  $number    The number of URLs to retrieve. Default is RSP_SITEMAP_PAGE_DEFAULT_LIMIT.
 * @param bool $urls_only Whether to return only the URLs or additional data. Default is false.
 *
 * @return array The list of most visited URLs or additional data based on $urls_only parameter.
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_get_most_visited($number = RSP_SITEMAP_PAGE_DEFAULT_LIMIT, $urls_only = false)
{
    if (!is_numeric($number) || $number <= 0) {
        return [];
    }

    global $wpdb;
    $table_name = RSP_PLUGIN_TABLE;

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT page_url, SUM(visit_count) AS total_visits FROM $table_name GROUP BY page_url ORDER BY total_visits DESC LIMIT %d",
            intval($number)
        ),
        ARRAY_A
    );

    if ($urls_only) {
        $results = array_map(function ($page) {
            return (untrailingslashit($page['page_url']));
        }, $results);
    } else {
        $results = array_map(function ($page) {
            return [
                ...$page,
                'page_url' => untrailingslashit($page['page_url']),
            ];
        }, $results);
    }

    return $results;
}
/**
 * Retrieves a list of URLs to preload (to print in the sitemap).
 *
 * @param int $number The number of URLs to retrieve. Defaults to RSP_SITEMAP_PAGE_DEFAULT_LIMIT.
 * @return array The list of URLs to preload.
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_get_urls_to_preload($number = RSP_SITEMAP_PAGE_DEFAULT_LIMIT)
{
    $urls_to_always_include = apply_filters('rsp_pages_to_always_include', get_option('rsp_pages_to_always_include', []));
    $urls_to_always_include = is_array($urls_to_always_include) ? $urls_to_always_include : [];
    $urls_to_always_include = array_filter(array_map('rsp_sanitize_url', $urls_to_always_include), function ($url) {
        return !is_null($url);
    });
    $most_visited = [];
    if (false !== get_transient('rsp_most_visited_pages')) {
        $most_visited = get_transient('rsp_most_visited_pages');
    } else {
        $most_visited = rsp_get_most_visited($number, true);
        $expiration_time = apply_filters('rsp_cached_sitemap_urls_expiration_time', RSP_CACHED_SITEMAP_URLS_DEFAULT_EXPIRATION_TIME);
        $expiration_time = validate_positive_integer($expiration_time, RSP_CACHED_SITEMAP_URLS_DEFAULT_EXPIRATION_TIME);
        if (!empty($most_visited)) set_transient('rsp_most_visited_pages', $most_visited, $expiration_time);
    }
    return array_unique(array_merge($urls_to_always_include, $most_visited));
}
/**
 * Generates a sitemap.
 *
 * This function generates a sitemap with a specified number of entries.
 *
 * @param int $number The number of entries to include in the sitemap. Default is RSP_SITEMAP_PAGE_DEFAULT_LIMIT.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_get_generated_sitemap($number = RSP_SITEMAP_PAGE_DEFAULT_LIMIT)
{
    $urls_to_preload = rsp_get_urls_to_preload($number);

    $sitemap = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $sitemap .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

    foreach ($urls_to_preload as $page_url) {
        $url = untrailingslashit(esc_url($page_url));
        $sitemap .= "<url><loc>{$url}</loc></url>\n";
    }

    $sitemap .= "</urlset>";

    return $sitemap;
}


// Daily cleanup task: Initialize the batch process
add_action('rsp_daily_cleanup_task', 'rsp_initialize_cleanup');
/**
 * Initializes the cleanup process for WP Rocket - Smart Preload.
 *
 * This function sets up the necessary schduled task to ensure
 * that the cleanup process is properly initialized.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function rsp_initialize_cleanup()
{
    if (!wp_next_scheduled('rsp_batch_cleanup_task')) {
        wp_schedule_single_event(time() + 60, 'rsp_batch_cleanup_task');
    }
}

// Batch cleanup task: Process records in batches
add_action('rsp_batch_cleanup_task', 'rsp_process_cleanup_batch');
/**
 * Processes a batch of cleanup tasks for WP Rocket - Smart Preload.
 *
 * This function handles the cleanup of database records in batches to ensure
 * that the system resources are not overwhelmed. It is typically called
 * during scheduled maintenance or when specific conditions are met.
 *
 * @return void
 */
function rsp_process_cleanup_batch()
{
    global $wpdb;
    $wp_mysql_time_format = 'Y-m-d H:i:s';
    $date = new DateTime('2025-01-25 09:30:34');
    $date->modify(RSP_CLEANUP_THREASHOLD_TIME);
    $threshold_date = $date->format($wp_mysql_time_format);
    $table_name = $wpdb->prefix . 'rsp_page_visits';
    // $threshold_date = date('Y-m-d H:i:s', strtotime(RSP_CLEANUP_THREASHOLD_TIME));
    $batch_size = apply_filters('rsp_cleanup_batch_limit_size', RSP_CLEANUP_BATCH_DEFAULT_LIMIT);
    $batch_size = validate_positive_integer($batch_size, RSP_CLEANUP_BATCH_DEFAULT_LIMIT);

    // Delete a batch of records
    $affected_rows = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name WHERE last_visit < %s LIMIT %d",
            $threshold_date,
            $batch_size
        )
    );
    // error_log('Deleted records: ' . $affected_rows . "\n\n", 3, ABSPATH . 'rsp_database_cleanup.log');
    echo 'Deleted records: ' . $affected_rows . "\n\n";

    // If there are more records to delete, schedule the next batch, until $affected_rows is 0
    if ($affected_rows > 0) {
        // wp_schedule_single_event(time() + 60, 'rsp_batch_cleanup_task');
    }
}

/**
 * Sanitize URL to remove query parameters, anchors and trailing slashes.
 *
 * @since 1.0.0
 * @author Sandy Figueroa
 * @param string $url The raw URL.
 * @return string The sanitized URL.
 */
function rsp_sanitize_url($url)
{
    $parsed_url = wp_parse_url($url);
    if (!$parsed_url || !isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
        return null;
    }
    if ($parsed_url['scheme'] !== 'http' && $parsed_url['scheme'] !== 'https') {
        return null;
    }
    $scheme = $parsed_url['scheme'] . '://';
    $host = $parsed_url['host'];
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

    return untrailingslashit($scheme . $host . $path);
}

/**
 * Get the real IP address of the user.
 *
 * @since 1.0.0
 * @author Sandy Figueroa
 * @return string The user's IP address.
 */
function rsp_get_user_ip()
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return sanitize_text_field(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }
    return '0.0.0.0';
}

/**
 * Add the Smart Preload sitemap to WP Rocket Preload.
 *
 * This function is responsible for adding the sitemap to WP Rocket, so preload use it to work.
 * It ensures that only the Smart Preload sitemap is used.
 *
 * @return void
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function wprocket_preload_only_sitemap()
{
    return [home_url('/' . RSP_SITEMAP_FILENAME)];
}
add_filter('rocket_sitemap_preload_list', 'wprocket_preload_only_sitemap', PHP_INT_MAX);

// Exclude other URLs from being added to the preload table.
// these will still be cached after a visit, but not preloaded 
add_filter('rocket_preload_exclude_urls', function ($regexes, $url) {
    $url = untrailingslashit($url);
    $sitemap_page_limit = apply_filters('rsp_sitemap_page_limit', get_option('rsp_sitemap_page_limit', RSP_SITEMAP_PAGE_DEFAULT_LIMIT));
    $sitemap_page_limit = validate_positive_integer($sitemap_page_limit, RSP_SITEMAP_PAGE_DEFAULT_LIMIT);
    $urls_to_preload = rsp_get_urls_to_preload($sitemap_page_limit);
    if (! in_array($url, $urls_to_preload, true)) {
        $regexes[] = $url;
    }
    return $regexes;
}, PHP_INT_MAX, 2);

/**
 * Validates if the given value is a positive integer.
 *
 * @param mixed $value The value to be validated.
 * @param int $default_value The default value to return if the validation fails.
 * @return int The validated positive integer or the default value if validation fails.
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function validate_positive_integer($value, $default_value)
{
    return is_numeric($value) && $value > 0 ? intval($value) : $default_value;
}
/**
 * Validates the accepted frequencies.
 *
 * @param mixed $value The value to be validated.
 * @return string The validated frequency. 'daily' is returned if validation fails. Accepted values: 'hourly', 'twicedaily', 'daily', 'weekly'
 * @since 1.0.0
 * @author Sandy Figueroa
 */
function vaidate_accepted_frequencies($value)
{
    $accepted_values = ['hourly', 'twicedaily', 'daily', 'weekly'];
    return in_array($value, $accepted_values, true) ? $value : 'daily';
}
/**
 * TODO
 * - Implement:
 *  -
 * - Document Requirements:
 *  - A working cron in the site
 * 
 */
