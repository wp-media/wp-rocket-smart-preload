<?php

function wp_rocket_smart_preload_settings_page()
{
    $urls_to_always_include = get_option('rsp_pages_to_always_include', []);
    $urls_to_always_include = is_array($urls_to_always_include) ? $urls_to_always_include : [];
    $sitemap_page_limit = get_option('rsp_sitemap_page_limit', RSP_SITEMAP_PAGE_DEFAULT_LIMIT);
    $deactivate_ip_protection = get_option('rsp_deactivate_ip_protection', 0);
?>
    <div class="wrap">
        <h2>WP Rocket - Smart Preload Settings</h2>
        <form id="plugin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('save_settings', 'wp_rocket_nonce'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Always Preload These URLs</th>
                    <td>
                        <textarea id="preload-urls" name="preload-urls" rows="7" cols="80"><?php echo esc_textarea(implode("\n", $urls_to_always_include)); ?></textarea>
                        <p class="description">
                            Specify a list of URLs to always be preloaded. These URLs will be added to the sitemap in addition to the most visited ones. Add each URL on a separate line.
                            <br>
                            <strong>Note:</strong> Home page URL is always included.
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">URL Limit</th>
                    <td>
                        <input type="number" id="url-limit" name="url-limit" min="1" step="1" value="<?php echo esc_attr($sitemap_page_limit); ?>">
                        <p class="description">
                            Set the maximum number of URLs to be included in the sitemap. Only positive numbers are allowed. (Default is <?php echo esc_html(RSP_SITEMAP_PAGE_DEFAULT_LIMIT); ?>)
                            <br>
                            <strong>Note:</strong> In most large sites, only a small number of pages are really visited, so, it is recommended to keep this number low to avoid unnecessary processing.
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">IP Protection</th>
                    <td>
                        <label class="rsp-switch">
                            <input type="checkbox" id="ip-protection" name="ip-protection" <?php checked($deactivate_ip_protection, '0'); ?>>
                            <span class="rsp-slider"></span>
                        </label>
                        <p class="description">Enable to prevent counting fake visits due to multiple page refreshes from the same IP address.</p>
                    </td>
                </tr>
            </table>

            <input type="hidden" name="action" value="save_wp_rocket_smart_preload_settings">
            <button type="submit" class="button-primary">Save Settings</button>
        </form>
    </div>
<?php
}

function save_wp_rocket_smart_preload_settings()
{
    // Verify capability
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access', 'Forbidden', ['response' => 403]);
    }
    // Verify nonce
    if (!isset($_POST['wp_rocket_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_rocket_nonce'])), 'save_settings')) {
        wp_die('Security check failed', 'Forbidden', ['response' => 403]);
    }
    // Removing duplicates
    $raw_urls_input = isset($_POST['preload-urls']) ? sanitize_textarea_field(wp_unslash($_POST['preload-urls'])) : '';
    $raw_urls = array_unique(array_map('trim', explode("\n", $raw_urls_input)));
    // Validate and sanitize URLs
    $urls = array_map('untrailingslashit', array_filter(array_map('sanitize_text_field', $raw_urls), function ($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }));

    // Validate URL limit
    $url_limit = isset($_POST['url-limit']) ? intval(wp_unslash($_POST['url-limit'])) : 1;
    if ($url_limit <= 0) {
        $url_limit = 1; // default to 1 if invalid
    }

    // Sanitize IP Protection
    $ip_protection = isset($_POST['ip-protection']) ? '0' : '1';

    // Save settings
    update_option('rsp_pages_to_always_include', $urls);
    update_option('rsp_sitemap_page_limit', $url_limit);
    update_option('rsp_deactivate_ip_protection', $ip_protection);

    // Invalidate cached sitemap URLs so changes take effect immediately
    delete_transient('rsp_most_visited_pages');

    // Redirect back to the settings page
    $redirect_url = add_query_arg('page', 'wp-rocket-smart-preload', admin_url('options-general.php'));
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_save_wp_rocket_smart_preload_settings', 'save_wp_rocket_smart_preload_settings');

function rsp_admin_menu()
{
    $hook_suffix = add_options_page('WP Rocket - Smart Preload', 'WP Rocket - Smart Preload', 'manage_options', 'wp-rocket-smart-preload', 'wp_rocket_smart_preload_settings_page');
    if ($hook_suffix) {
        add_action('admin_enqueue_scripts', function ($hook) use ($hook_suffix) {
            if ($hook !== $hook_suffix) {
                return;
            }
            wp_enqueue_style('rsp-admin-css', plugin_dir_url(__FILE__) . 'assets/css/rsp-admin.css', [], RSP_PLUGIN_VERSION);
        });
    }
}
add_action('admin_menu', 'rsp_admin_menu');
