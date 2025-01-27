<?php
/*
Plugin Name: WP Rocket - Smart Preload
Description: Smart Preload for WP Rocket Plugin
Version: 1.0
Author: Your Name
*/

function wp_rocket_settings_page()
{
    $urls_to_always_include = get_option('rsp_pages_to_always_include', []);
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
                        <textarea id="preload-urls" name="preload-urls" rows="7" cols="80"><?php echo implode("\n", $urls_to_always_include); ?></textarea>
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
                            Set the maximum number of URLs to be included in the sitemap. Only positive numbers are allowed. (Default is <?php echo RSP_SITEMAP_PAGE_DEFAULT_LIMIT ?>)
                            <br>
                            <strong>Note:</strong> In most large sites, only a small number of pages are really visited, so, it is recommended to keep this number low to avoid unnecessary processing.
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">IP Protection</th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="ip-protection" name="ip-protection" <?php checked($deactivate_ip_protection, '0'); ?>>
                            <span class="slider round"></span>
                        </label>
                        <p class="description">Enable to prevent counting fake visits due to multiple page refreshes from the same IP address.</p>
                    </td>
                </tr>
            </table>

            <input type="hidden" name="action" value="save_wp_rocket_smart_preload_settings">
            <button type="submit" class="button-primary">Save Settings</button>
        </form>
    </div>
    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 34px;
            height: 20px;
        }

        .switch input {
            display: none;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #2196F3;
        }

        input:checked+.slider:before {
            transform: translateX(14px);
        }
    </style>
<?php
}

function save_wp_rocket_smart_preload_settings()
{
    // Verify nonce
    if (!isset($_POST['wp_rocket_nonce']) || !wp_verify_nonce($_POST['wp_rocket_nonce'], 'save_settings')) {
        return;
    }
    // Removing duplicates
    $raw_urls = array_unique(array_map('trim', explode("\n", $_POST['preload-urls'])));
    // Validate and sanitize URLs
    $urls = array_map('untrailingslashit', array_filter(array_map('sanitize_text_field', $raw_urls), function ($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }));

    // Validate URL limit
    $url_limit = intval($_POST['url-limit']);
    if ($url_limit <= 0) {
        $url_limit = 1; // default to 1 if invalid
    }

    // Sanitize IP Protection
    $ip_protection = isset($_POST['ip-protection']) ? '1' : '0';

    // Save settings
    update_option('rsp_pages_to_always_include', $urls);
    update_option('rsp_sitemap_page_limit', $url_limit);
    update_option('rsp_deactivate_ip_protection', $ip_protection);

    // Redirect back to the settings page
    $redirect_url = add_query_arg('page', 'wp-rocket-smart-preload', admin_url('options-general.php'));
    wp_redirect($redirect_url);
}
add_action('admin_post_save_wp_rocket_smart_preload_settings', 'save_wp_rocket_smart_preload_settings');

function rsp_admin_menu()
{
    add_options_page('WP Rocket - Smart Preload', 'WP Rocket - Smart Preload', 'manage_options', 'wp-rocket-smart-preload', 'wp_rocket_settings_page');
}
add_action('admin_menu', 'rsp_admin_menu');
?>