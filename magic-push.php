<?php
/*
Plugin Name: MagicPush
Description: Push WordPress posts to different external APIs based on the environment.
Version: 1.3
Author: Rakesh Mandal
*/

function rk_add_custom_buttons()
{
    add_meta_box(
        'rk_custom_buttons_meta_box',
        'Push to External API',
        'rk_display_custom_buttons',
        'post',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'rk_add_custom_buttons');

function rk_display_custom_buttons($post)
{
    ?>
    <input type="button" id="rk_push_to_staging" class="button-outline" value="Push to Staging">
    <input type="button" id="rk_push_to_production" class="button-accent" value="Push to Production">
    <!-- <script>
        document.getElementById('rk_push_to_staging').onclick = function () {
            rk_pushPostData('staging');
        };
        document.getElementById('rk_push_to_production').onclick = function () {
            rk_pushPostData('production');
        };

        function rk_pushPostData(environment) {
            var data = {
                'action': 'rk_push_post_data',
                'post_id': <?php echo $post->ID; ?>,
                'environment': environment
            };
            console.log('Data being sent:', data);  // Log the data being sent
            jQuery.post(ajaxurl, data, function (response) {
                console.log('Response from WordPress:', response);
                alert(response);
            });
        }
    </script> -->

    <script>
        document.getElementById('rk_push_to_staging').onclick = function () {
            rk_confirmAndPushPostData('staging');
        };
        document.getElementById('rk_push_to_production').onclick = function () {
            rk_confirmAndPushPostData('production');
        };

        function rk_confirmAndPushPostData(environment) {
            if (confirm("Are you sure you want to push this post to the " + environment + " environment?")) {
                rk_pushPostData(environment);
            }
        }

        function rk_pushPostData(environment) {
            var data = {
                'action': 'rk_push_post_data',
                'post_id': <?php echo $post->ID; ?>,
                'environment': environment
            };
            console.log('Data being sent:', data);  // Log the data being sent
            jQuery.post(ajaxurl, data, function (response) {
                console.log('Response from WordPress:', response);
                alert(response);
            });
        }
    </script>
    <?php
}

// Enqueue the CSS file
function rk_enqueue_custom_admin_styles()
{
    wp_enqueue_style('magic-push-css', plugin_dir_url(__FILE__) . 'assets/css/magic-push.css');
}
add_action('admin_enqueue_scripts', 'rk_enqueue_custom_admin_styles');

function rk_handle_push_post_data()
{
    $post_id = intval($_POST['post_id']);
    $environment = sanitize_text_field($_POST['environment']);

    // Fetch post data from the WordPress REST API
    $response = wp_remote_get(get_rest_url(null, "wp/v2/posts/{$post_id}?_embed"));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("Failed to fetch post data: $error_message");
        echo json_encode(array('error' => 'Failed to fetch post data', 'details' => $error_message));
        wp_die(); // Terminate immediately and return a proper response
    }

    // Retrieve the post data
    $post_data = wp_remote_retrieve_body($response);

    // Log the fetched post data
    error_log('Fetched WordPress Post Data: ' . $post_data);

    // Get the endpoints from the options table
    $endpoints = array(
        'staging' => get_option('rk_staging_endpoint'),
        'production' => get_option('rk_production_endpoint')
    );

    // Determine the target URL based on the environment
    if (array_key_exists($environment, $endpoints) && !empty($endpoints[$environment])) {
        $target_url = $endpoints[$environment];
    } else {
        error_log('Invalid environment or endpoint not set');
        echo json_encode(array('error' => 'Invalid environment or endpoint not set'));
        wp_die(); // Terminate immediately and return a proper response
    }

    // Log the payload being sent to the external API
    error_log('Payload being sent to the external API: ' . $post_data);

    // Send data to the external API with an increased timeout
    $response = wp_remote_post(
        $target_url,
        array(
            'method' => 'POST',
            'body' => $post_data,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30 // Increase timeout to 30 seconds
        )
    );

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("Failed to push data to external API: $error_message");
        echo json_encode(array('error' => 'Failed to push data to external API', 'details' => $error_message));
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log("Response code: $response_code");
        error_log("Response body: $response_body");
        echo json_encode(array('success' => 'Data pushed successfully', 'response_code' => $response_code, 'response_body' => $response_body));
    }

    wp_die(); // Terminate immediately and return a proper response
}



add_action('wp_ajax_rk_push_post_data', 'rk_handle_push_post_data');

// Settings Page
function rk_register_settings()
{
    add_option('rk_staging_endpoint', '');
    add_option('rk_production_endpoint', '');
    register_setting('rk_options_group', 'rk_staging_endpoint');
    register_setting('rk_options_group', 'rk_production_endpoint');
}
add_action('admin_init', 'rk_register_settings');

function rk_register_options_page()
{
    add_options_page('MagicPush Settings', 'MagicPush', 'manage_options', 'rk', 'rk_options_page');
}
add_action('admin_menu', 'rk_register_options_page');

function rk_options_page()
{
    ?>
    <div>
        <h2>MagicPush Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('rk_options_group'); ?>
            <table>
                <tr valign="top">
                    <th scope="row"><label for="rk_staging_endpoint">Staging Endpoint</label></th>
                    <td><input type="text" id="rk_staging_endpoint" name="rk_staging_endpoint"
                            value="<?php echo get_option('rk_staging_endpoint'); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="rk_production_endpoint">Production Endpoint</label></th>
                    <td><input type="text" id="rk_production_endpoint" name="rk_production_endpoint"
                            value="<?php echo get_option('rk_production_endpoint'); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
?>