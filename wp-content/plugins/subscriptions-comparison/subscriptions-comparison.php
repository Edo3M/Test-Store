<?php
/*
Plugin Name: Subscription Comparison
Description: A plugin to display a comparison table of subscription variations.
Version: 1.0
Author: Edgardo Martinez
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Subscription_Comparison {
    private $api_url;
    private $consumer_key;
    private $consumer_secret;

    public function __construct() {
        $this->api_url = get_site_url(null, '/wp-json/wc/v3');
        $this->consumer_key = 'ck_1102f6a6e27edfbe5c37b911e1072bb7c98ec7b5';
        $this->consumer_secret = 'cs_fda1180ec154143cab42ed9f2505477de52952c5';

        add_shortcode('subscriptions_comparison', array($this, 'render_comparison_table'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function enqueue_styles() {
        wp_enqueue_style('subscription-comparison-style', plugins_url('/assets/css/style.css', __FILE__));
    }

    private function fetch_variations($product_id) {
        $endpoint = "/products/{$product_id}/variations";
        $url = $this->api_url . $endpoint;

        // OAuth 1.0a headers
        $oauth_params = array(
            'oauth_consumer_key' => $this->consumer_key,
            'oauth_nonce' => wp_generate_password(12, false),
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0'
        );

        $base_info = $this->build_base_string($url, 'GET', $oauth_params);
        $composite_key = rawurlencode($this->consumer_secret) . '&';
        $oauth_params['oauth_signature'] = base64_encode(hash_hmac('sha256', $base_info, $composite_key, true));
        
        $auth_header = 'OAuth ' . $this->build_authorization_header($oauth_params);

        $args = array(
            'headers' => array(
                'Authorization' => $auth_header
            ),
            'sslverify' => false // Disable SSL verification for local development
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    private function build_base_string($baseURI, $method, $params) {
        $r = array();
        ksort($params);
        foreach($params as $key=>$value){
            $r[] = "$key=" . rawurlencode($value);
        }
        return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
    }

    private function build_authorization_header($oauth_params) {
        $r = 'Authorization: OAuth ';
        $values = array();
        foreach($oauth_params as $key => $value) {
            $values[] = "$key=\"" . rawurlencode($value) . "\"";
        }
        $r .= implode(', ', $values);
        return $r;
    }

    public function render_comparison_table($atts) {
        $atts = shortcode_atts(array('id' => ''), $atts, 'subscriptions_comparison');
        $product_id = $atts['id'];

        if (!$product_id) {
            return 'Product ID is required.';
        }

        $variations = $this->fetch_variations($product_id);

        if (empty($variations)) {
            return 'No variations found for this product.';
        }

        ob_start();
        ?>
        <div class="subscription_comparison_table">
            <?php foreach ($variations as $variation): ?>
                <div class="subscription_comparison_card">
                    <div class="subscription_comparison_card-header">
                        <h3><?php echo esc_html($variation['attributes'][0]['option']); ?></h3>
                    </div>
                    <div class="subscription_comparison_card-body">
                        <div class="subscription_comparison_card-price">
                            <?php
                                $price = esc_html($variation['price']);
                                $period = '';

                                foreach ($variation['meta_data'] as $meta) {
                                    if ($meta['key'] == '_subscription_period') {
                                        if ($meta['value'] == 'month') {
                                            $period = '<small>/month</small>';
                                        } elseif ($meta['value'] == 'year') {
                                            $period = '<small>/year</small>';
                                        }
                                        break;
                                    }
                                }
                            ?>
                            $<?php echo $price . $period; ?></div>
                        <div class="subscription_comparison_card-list"><?php echo $variation['description']; ?></div>
                    </div>
                    <div class="subscription_comparison_card-button">
                        <div class="subscription_comparison_card-button"><a href="<?php echo esc_url($variation['permalink']); ?>" class="button">Subscribe</a></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Subscription_Comparison();
