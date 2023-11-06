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
                $this->method_description = 'Korapay provides a payment platform that enables local and global businesses accept and disburse payments quickly and seamlessly while saving time and money using either bank transfers or credit card payments, Sign up on korapay.com to get your API keys.';

                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables.
                $this->title = $this->get_option('title');
                $this->live_public_key = $this->get_option('live_public_key');
                $this->live_secret_key = $this->get_option('live_secret_key');

                $this->enabled      = $this->get_option( 'enabled' );
                $this->livemode     = 'yes' === $this->get_option( 'livemode' );
                $this->secret_key  = $this->livemode ? $this->get_option( 'live_secret_key' ) : $this->get_option( 'test_secret_key' );


                // Save settings.
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
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
                    'description' => array(
                        'title'       => __('Description', 'digiwoo_korapay'),
                        'type'        => 'textarea',
                        'description' => __('This controls the payment method description which the user sees during checkout.', 'digiwoo_korapay'),
                        'default'     => __('This controls the payment method description which the user sees during checkout.', 'digiwoo_korapay'),
                        'desc_tip'    => true,
                    ),
                    'livemode' => array(
                        'title'   => __('Enable/Disable Live Version', 'digiwoo_korapay'),
                        'type'    => 'checkbox',
                        'label'   => __('Enable Live Version', 'digiwoo_korapay'),
                        'default' => 'no'
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
                    'test_public_key' => array(
                        'title'       => __('Test Mode Public Key', 'digiwoo_korapay'),
                        'type'        => 'text',
                        'description' => __('This is the Test Mode public key provided by Korapay', 'digiwoo_korapay'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'test_secret_key' => array(
                        'title'       => __('Test Mode Secret Key', 'digiwoo_korapay'),
                        'type'        => 'text',
                        'description' => __('This is the Test Mode secret key provided by Korapay', 'digiwoo_korapay'),
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

            public function process_payment( $order_id ) {
                $log_data = korapay_get_logger();               
                $order = wc_get_order( $order_id );

                // Get the billing information
                $billing_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $billing_email = $order->get_billing_email();
                $billing_country = $order->get_billing_country();
                $billing_state = $order->get_billing_state();
                $billing_city = $order->get_billing_city();
                $billing_post_code = $order->get_billing_postcode();

                // Prepare the payload
                $payload = array(
                    "amount" => $order->get_total(),
                    "redirect_url" => $this->get_return_url( $order ),
                    "currency" => get_woocommerce_currency(),
                    "reference" => $order->get_order_number(),
                    "narration" => "Payment for Order #" . $order->get_order_number(),
                    "channels" => array(
                        "card",
                        "bank_transfer"
                    ),
                    "default_channel" => "card",
                    "customer" => array(
                        "name" => $billing_name,
                        "email" => $billing_email,
                    ),
                    "notification_url" => add_query_arg( 'wc-api', 'digiwoo_korapay_ipn', home_url( '/' ) ),
                    "metadata" => array(
                        "order_id" => $order->get_id(),
                        "billing_country" => $billing_country,
                        "billing_state" => $billing_state,
                        "billing_city" => $billing_city,
                        "billing_post_code" => $billing_post_code,
                    ),
                );

                // Use wp_remote_post to call the Korapay API
                $response = wp_remote_post( 'https://api.korapay.com/merchant/api/v1/charges/initialize', array(
                    'method'    => 'POST',
                    'headers'   => array(
                        'Authorization' => 'Bearer ' . $this->secret_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'      => json_encode( $payload ),
                    'timeout'   => 90,
                    'sslverify' => false,
                ));

                if ( is_wp_error( $response ) ) {
                    wc_add_notice( 'Connection error: ' . $response->get_error_message(), 'error' );
                    return;
                }

                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                $log_data['logger']->info('response API : '.wp_json_encode($body),  $log_data['context']);

                // Check the response from Korapay
                if ( isset( $body['status'] ) && $body['status'] && isset( $body['data']['checkout_url'] ) ) {
                    // Mark as on-hold (we're awaiting the payment)
                    $order->update_status( 'on-hold', __( 'Awaiting payment', 'woocommerce' ) );

                    // Reduce stock levels
                    wc_reduce_stock_levels( $order_id );

                    // Remove cart
                    WC()->cart->empty_cart();

                    // Return the redirect
                    return array(
                        'result'   => 'success',
                        'redirect' => $body['data']['checkout_url'],
                    );
                } else {
                    if ( isset( $body['message'] ) ) {
                        wc_add_notice( $body['message'], 'error' );
                    } else {
                        wc_add_notice( __( 'An error occurred while processing the payment.', 'woocommerce' ), 'error' );
                    }
                    return;
                }
            } // End Of public function process_payment

           public function korapay_check_for_ipn_response() {
                $log_data = korapay_get_logger(); 
                // Get the global WP object to access HTTP POST data
                $json_post_data = file_get_contents('php://input');
                
                // Decode the JSON formatted payload
                $response = json_decode( $json_post_data, true );
                $log_data['logger']->info('response ipn : '.wp_json_encode($response),  $log_data['context']);

                // Check for the 'event' and 'data' in the response
                if ( isset( $response['event'] ) && $response['event'] === 'charge.success' && isset( $response['data'] ) ) {
                    $transaction_reference = $response['data']['reference']; // the unique transaction reference

                    // Retrieve the order by the transaction reference. Here you may need to match this with the order ID/metadata you saved earlier.
                    $order_id = $this->get_order_id_by_transaction_reference( $transaction_reference );
                    $order = wc_get_order( $order_id );

                    if ( $order && $this->validate_webhook_response( $response, $order ) ) {
                        // Check the payment status and update the order accordingly
                        if ( $response['data']['status'] === 'success' ) {
                            // Mark the order as completed
                            $order->payment_complete();
                            $order->add_order_note( 'Korapay payment successful. Reference: ' . $transaction_reference );
                            // You may want to add additional meta or perform other actions based on the payment method, etc.
                        } else {
                            // Handle payment failure
                            $order->update_status('failed', __( 'Payment failed or was declined', 'woocommerce' ));
                        }
                    } else {
                        // Log for invalid order or failed validation
                    }
                } else {
                    // Log for invalid event type or missing data
                }

                // Whatever the response, you need to return 200 OK to Korapay to acknowledge receipt of the notification
                wp_send_json_success();
            }

            private function validate_webhook_response( $response, $order ) {
                // Validation logic here
                // You should validate that the amount and currency match the order
                // If Korapay provides a signature, you should validate it as well
                // Return true if valid, false otherwise

                // Example validation
                if ( $order->get_total() == $response['data']['amount'] / 100 && $order->get_currency() == $response['data']['currency'] ) {
                    return true;
                }

                return false; // Placeholder, should be actual validation logic
            }

            private function get_order_id_by_transaction_reference( $transaction_reference ) {
                // You need to implement this method to retrieve the order ID based on the transaction reference
                // This typically involves querying the post_meta table where you stored the transaction reference upon initializing the payment
                // Placeholder: return the order ID that corresponds to the provided transaction reference
                return $order_id;
            }



        } //End of class WC_KORAPAY_PAYMENT

        // Add the gateway to WooCommerce
        add_filter('woocommerce_payment_gateways', 'add_digiwoo_korapay_gateway');

        function add_digiwoo_korapay_gateway($methods) {
            $methods[] = 'WC_KORAPAY_PAYMENT';
            return $methods;
        }

    } // End of function digiwoo_korapay_init

    function korapay_get_logger() {
        $logger = wc_get_logger();
        $context = array('source' => 'digiwoo_korapay');
        return array('logger' => $logger, 'context' => $context);
    } // End of function korapay_get_logger

    function add_digiwoo_korapay_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=digiwoo_korapay') . '">' . __('Settings', 'digiwoo_korapay') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }// End of add_digiwoo_korapay_settings_link
    add_filter('plugin_action_links_digiwoo-korapay/digiwoo-korapay.php', 'add_digiwoo_korapay_settings_link', 10, 1 );
} // End of apply_filters