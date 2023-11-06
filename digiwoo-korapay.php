<?php
/**
 * Plugin Name:       DigiWoo Korapay for WooCommerce
 * Plugin URI:        https://fundscap.com/
 * Description:       Adds a Korapay payment method to WooCommerce.
 * Version:           1.0.1
 * Author:            Ardi JM Consulting
 * Author URI:        https://fundscap.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       digiwoo_korapay
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('plugins_loaded', 'digiwoo_korapay_init', 0);

    function digiwoo_korapay_init() {
        if (!class_exists('WC_Payment_Gateway')) {
            return; // Exit if WooCommerce is not loaded
        }

        // Main gateway class
        class WC_KORAPAY_PAYMENT extends WC_Payment_Gateway {
            public function __construct() {
                $this->id = 'digiwoo_korapay';
                $this->icon = ''; // URL to an icon for this method.
                $this->has_fields = false;
                $this->method_title = 'Digiwoo Korapay';
                $this->method_description = 'Accept payments via Korapay API.';

                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables.
                $this->title = $this->get_option('title');
                $this->live_public_key = $this->get_option('live_public_key');
                $this->live_secret_key = $this->get_option('live_secret_key');

                // Save settings.
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'korapay_process_admin_options'));
                add_action( 'woocommerce_api_digiwoo_korapay_ipn', array( $this, 'korapay_check_for_ipn_response' ) );
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __('Enable/Disable', 'digiwoo_korapay'),
                        'type'    => 'checkbox',
                        'label'   => __('Enable Korapay Payment', 'digiwoo_korapay'),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title'       => __('Title', 'digiwoo_korapay'),
                        'type'        => 'text',
                        'description' => __('This controls the title the user sees during checkout.', 'digiwoo_korapay'),
                        'default'     => __('Korapay', 'digiwoo_korapay'),
                        'desc_tip'    => true,
                    ),                    
                    'live_public_key' => array(
                        'title'       => __('Live Public Key', 'digiwoo_korapay'),
                        'type'        => 'text',
                        'description' => __('This is the live public key provided by Korapay', 'digiwoo_korapay'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'live_secret_key' => array(
                        'title'       => __('Live Secret Key', 'digiwoo_korapay'),
                        'type'        => 'text',
                        'description' => __('This is the live secret key provided by Korapay', 'digiwoo_korapay'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'webhook_url' => array(
                        'title'       => __('Korapay Webhook URL', 'digiwoo_korapay'),
                        'type'        => 'text',
                        'description' => __('URL to receive webhooks from the service provider.', 'digiwoo_korapay'),
                        'default'     => home_url('/?wc-api=digiwoo_korapay_ipn'),
                        'desc_tip'    => true,
                        'custom_attributes' => array(
                            'readonly' => 'readonly'
                        )
                    ),

                ); // End Of form_fields
                
            } // End Of public function init_form_fields

        } //End of class WC_KORAPAY_PAYMENT 

    } // End of function digiwoo_korapay_init
} // End of apply_filters