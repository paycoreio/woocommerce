<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_Paycore class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Paycore extends WC_Payment_Gateway {

    const PAYCORE_CHECKOUT_URL = 'https://checkout.paycore.io/api/%s/payment-methods';

    private static $secret_key;
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                   = 'paycore';
        $this->method_title         = __( 'PayCore.io', 'woocommerce-gateway-paycore' );
        $this->method_description   = sprintf( __( 'PayCore.io works by adding chosen payment methods on the checkout and then proceed payment with PayCore.io checkout. <a href="%1$s" target="_blank">Sign up</a> for a PayCore.io account, and <a href="%2$s" target="_blank">get your PayCore.io account keys</a>.', 'woocommerce-gateway-paycore' ), 'https://dashboard.paycore.io', 'https://dashboard.paycore.io/checkout/payment-pages' );
        $this->has_fields           = true;
        $this->view_transaction_url = 'https://dashboard.paycore.io/operations/payments/%s';
        $this->supports             = array(
            'subscriptions',
            'products',
            'refunds',
            'subscription_cancellation',
            'subscription_reactivation',
            'subscription_suspension',
            'subscription_amount_changes',
            'subscription_payment_method_change', // Subs 1.n compatibility
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_date_changes',
            'multiple_subscriptions',
            'pre-orders',
        );

        // Load the form fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values.
        $this->title                  = $this->get_option( 'title' );
        $this->description            = $this->get_option( 'description' );
        $this->enabled                = $this->get_option( 'enabled' );
        $this->testmode               = 'yes' === $this->get_option( 'testmode' );

        self::$secret_key             = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
        $this->publishable_key        = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
        $this->payment_methods        = $this->get_option('payment_methods');
        $this->logging                = 'yes' === $this->get_option( 'logging' );

        if ( $this->paycore_checkout ) {
            $this->order_button_text = __( 'Continue to payment', 'woocommerce-gateway-paycore' );
        }

        if ( $this->testmode ) {
            $this->description .= ' ' . sprintf( __( 'TEST MODE ENABLED.', 'woocommerce-gateway-paycore' ), 'https://paycore.com/docs/testing' );
            $this->description  = trim( $this->description );
        }

        self::set_secret_key( $this->secret_key );

        // Hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_wc_gateway_paycore', array($this, 'process_response'));
    }

    /**
     * Load admin scripts.
     */
    public function admin_scripts() {
        if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
            return;
        }

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        $url = plugins_url( 'assets/js/paycore-admin' . $suffix . '.js', WC_PAYCORE_MAIN_FILE);
        wp_enqueue_script( 'woocommerce_paycore_admin', $url, array('jquery', 'jquery-ui-core'), WC_PAYCORE_VERSION, true );

        $paycore_admin_params = array(
            'localized_messages' => array(
                'not_valid_live_key_msg' => __( 'This is not a valid live key. Live keys start with "sk_live_" and "pk_live_".', 'woocommerce-gateway-paycore' ),
                'not_valid_test_key_msg' => __( 'This is not a valid test key. Test keys start with "sk_test_" and "pk_test_".', 'woocommerce-gateway-paycore' ),
                're_verify_button_text'  => __( 'Re-verify Domain', 'woocommerce-gateway-paycore' ),
                'missing_secret_key'     => __( 'Missing Secret Key. Please set the secret key field above and re-try.', 'woocommerce-gateway-paycore' ),
            ),
            'ajaxurl'            => admin_url( 'admin-ajax.php' ),
            'nonce'              => [],
        );

        wp_localize_script( 'woocommerce_paycore_admin', 'wc_paycore_admin_params', apply_filters( 'wc_paycore_admin_params', $paycore_admin_params ) );
    }

    /**
     * Generate html for payment methods on checkout page
     *
     * @return string
     */
    public function get_payment_methods_html()
    {
        $topMethods = $this->payment_methods;

        $html = $this->description;
        $checked = 'checked';
        foreach ($topMethods as $paymentMethod => $icon) {
            $html .= "<label for=\"" . $paymentMethod ."\"><div class=\"paycore-payment-method\"><input class=\"paycore-checkout-payment-method input-radio\" type=\"radio\" id=\"" . $paymentMethod . "\" " . $checked . " name=\"paycore_payment_method\" value=\"" . $paymentMethod . "\"><div class=\"img-wrap\" style=\"background-image: url(" . $icon . ")\"></div><span style='vertical-align: middle'>" . ucfirst($paymentMethod) . "</span></div></label>";
            $checked = '';
        }
        if (count($topMethods) > 0) {
            $html .= '<label for="others"><div class="paycore-payment-method"><input class="paycore-checkout-payment-method input-radio" type="radio" id="others" name="paycore_payment_method" value=""><span style=\'vertical-align: middle\'>' . __('Others', 'woocommerce-gateway-paycore') . '</span></div></label><style>.img-wrap {display:inline-block;height:24px;width:24px;vertical-align:middle;background:no-repeat center center;background-size: contain;margin-right:10px;}.paycore-payment-method{padding:10px;}.paycore-payment-method:hover{background-color:rgba(136,136,136,0.1)}.paycore-payment-method>input{margin-right:10px; vertical-align: middle}</style>';
        }

        return $html;
    }

    public function get_icon()
    {
        $icon  = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/paycore.png', WC_PAYCORE_MAIN_FILE )) . '" alt="PayCore.io"/>';

        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
    }

    /**
     * Get Paycore amount to pay
     *
     * @param float  $total Amount due.
     * @param string $currency Accepted currency.
     *
     * @return float|int
     */
    public function get_paycore_amount( $total, $currency = '' ) {
        if ( ! $currency ) {
            $currency = get_woocommerce_currency();
        }
        switch ( strtoupper( $currency ) ) {
            // Zero decimal currencies
            case 'BIF' :
            case 'CLP' :
            case 'DJF' :
            case 'GNF' :
            case 'JPY' :
            case 'KMF' :
            case 'KRW' :
            case 'MGA' :
            case 'PYG' :
            case 'RWF' :
            case 'VND' :
            case 'VUV' :
            case 'XAF' :
            case 'XOF' :
            case 'XPF' :
                $total = absint( $total );
                break;
            default :
                $total = round( $total, 2 ) * 100; // In cents
                break;
        }
        return $total;
    }

    /**
     * Check if SSL is enabled and notify the user
     */
    public function admin_notices() {
        if ( $this->enabled == 'no' ) {
            return;
        }

        // Check required fields
        if ( ! self::get_secret_key() ) {
            echo '<div class="error"><p>' . sprintf( __( 'PayCore.io error: Please enter your secret key <a href="%s">here</a>', 'woocommerce-gateway-paycore' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_paycore') ) . '</p></div>';
            return;

        } elseif ( ! $this->publishable_key ) {
            echo '<div class="error"><p>' . sprintf( __( 'PayCore.io error: Please enter your publishable key <a href="%s">here</a>', 'woocommerce-gateway-paycore' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_paycore') ) . '</p></div>';
            return;
        }

        // Simple check for duplicate keys
        if ( $this->secret_key == $this->publishable_key ) {
            echo '<div class="error"><p>' . sprintf( __( 'PayCore.io error: Your secret and publishable keys match. Please check and re-enter.', 'woocommerce-gateway-paycore' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_paycore') ) . '</p></div>';
            return;
        }
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available() {
        if ( 'yes' === $this->enabled ) {
            if ( ! $this->testmode && is_checkout() && ! is_ssl() ) {
                return false;
            }
            if ( ! self::get_secret_key() || ! $this->publishable_key ) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = include( untrailingslashit( plugin_dir_path( WC_PAYCORE_MAIN_FILE ) ) . '/includes/settings-paycore.php' );
    }

    /**
     * @param $key
     * @return array
     */
    public function validate_payment_methods_field($key)
    {
        $key = $this->get_field_key($key);
        $data = is_array($_POST[$key]) ? $_POST[$key] : [];

        return $data;
    }

    /**
     * @return string
     */
    public function generate_payment_methods_html()
    {
        ob_start();
        $topMethods = $this->get_option('payment_methods');
        $otherMethods = $availableMethods = $this->get_available_payment_methods();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php _e( 'Payment methods', 'woocommerce-gateway-paycore' ); ?>:</th>
            <td class="forminp" id="paycore_payment_methods">
                <div style="display: inline-block;">
                    <div id="inputs" style="display: none"></div>
                    <p><?php _e('Favourites', 'woocommerce-gateway-paycore'); ?>:</p>
                    <ul id="sortable1" class="connectedSortable" style="border: 1px solid rgba(91,189,97,0.5);">
                        <?php
                        foreach ($topMethods as $paymentMethod => $icon) {
                            if (array_key_exists($paymentMethod, $availableMethods)) {
                                echo "<li class=\"ui-state-default\" data-icon=\"" . $availableMethods[$paymentMethod]['icon'] . "\" data-payment-method=\"" . $paymentMethod . "\"><div class=\"img-wrap\" style=\"background-image: url(" . $availableMethods[$paymentMethod]['icon'] . ")\"></div>" . $paymentMethod . "</li>";
                                unset($otherMethods[$paymentMethod]);
                            }
                        }
                        ?>
                    </ul>

                </div>
                <div style="display: inline-block;margin-left: 30px">
                    <p><?php _e('Others', 'woocommerce-gateway-paycore'); ?>:</p>
                    <ul id="sortable2" class="connectedSortable" style="border: 1px solid rgba(255,153,0,0.5);">
                        <?php
                        foreach ($otherMethods as $paymentMethod => $description) {
                            echo "<li class=\"ui-state-default\" data-icon=\"" . $description['icon'] . "\" data-payment-method=\"" . $paymentMethod . "\"><div class=\"img-wrap\" style=\"background-image: url(" . $availableMethods[$paymentMethod]['icon'] . ")\"></div>" . $paymentMethod . "</li>";
                        }
                        ?>
                    </ul>

                </div>
                <style>
                    #sortable1, #sortable2 {
                        width: 192px;
                        min-height: 40px;
                        list-style-type: none;
                        margin: 0;
                        padding: 15px 0 10px 0;
                        float: left;
                        margin-right: 10px;
                    }
                    #sortable1 li, #sortable2 li {
                        margin: 0 5px 5px 5px;
                        padding: 5px;
                        font-size: 1em;
                        width: 170px;
                    }
                    .img-wrap {
                        display: inline-block;
                        height: 32px;
                        width: 32px;
                        vertical-align: middle;
                        background: no-repeat center center;
                        background-size: contain;
                        margin-right: 10px;
                    }
                </style>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @return array
     */
    public function get_available_payment_methods()
    {
        $url = self::PAYCORE_CHECKOUT_URL;
        $result = wp_remote_get(sprintf(self::PAYCORE_CHECKOUT_URL, $this->publishable_key));
        $this->log( sprintf( __( 'Get payment methods from PayCore.io: %s', 'woocommerce-gateway-paycore' ), var_export($result, true) ) );
        $paymentMethodsRaw = json_decode(wp_remote_retrieve_body($result), true);


        $paymentMethods = [];

        if (!empty($paymentMethodsRaw['data'])) {
            foreach ($paymentMethodsRaw['data'] as $paymentMethodRaw) {
                $paymentMethods[$paymentMethodRaw['code']] = $paymentMethodRaw;
            }
        }

        return $paymentMethods;
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        $user                 = wp_get_current_user();
        $total                = WC()->cart->total;

        // If paying from order, we need to get total from order not cart.
        if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
            $order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
            $total = $order->get_total();
        }

        if ( $user->ID ) {
            $user_email = get_user_meta( $user->ID, 'billing_email', true );
            $user_email = $user_email ? $user_email : $user->user_email;
        } else {
            $user_email = '';
        }

        echo '<div
			id="paycore-payment-data"
			data-email="' . esc_attr( $user_email ) . '"
			data-amount="' . esc_attr( $this->get_paycore_amount( $total ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '"
			data-order-id"'. esc_attr($order).'"
			data-public-key="' . esc_attr( $this->publishable_key ) . '">';

        echo $this->get_payment_methods_html();

        echo '</div>';
    }

    /**
     * payment_scripts function.
     *
     * Outputs scripts used for paycore payment
     *
     * @access public
     */
    public function payment_scripts() {
        if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
            return;
        }

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_script( 'woocommerce_paycore', plugins_url( 'assets/js/paycore' . $suffix . '.js', WC_PAYCORE_MAIN_FILE ), array( 'jquery-payment'), WC_PAYCORE_VERSION);

        $paycore_params = array(
            'key'                  => $this->publishable_key,
            'i18n_terms'           => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-paycore' ),
            'i18n_required_fields' => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-paycore' ),
            'legacy'               => true,
        );

        // If we're on the pay page we need to pass paycore.js the address of the order.
        if ( is_checkout_pay_page() && isset( $_GET['order'] ) && isset( $_GET['order_id'] ) ) {
            $order_key = urldecode( $_GET['order'] );
            $order_id  = absint( $_GET['order_id'] );
            $order     = wc_get_order( $order_id );

            if ( $order->id === $order_id && $order->order_key === $order_key ) {
                $paycore_params['billing_first_name'] = $order->billing_first_name;
                $paycore_params['billing_last_name']  = $order->billing_last_name;
                $paycore_params['billing_address_1']  = $order->billing_address_1;
                $paycore_params['billing_address_2']  = $order->billing_address_2;
                $paycore_params['billing_state']      = $order->billing_state;
                $paycore_params['billing_city']       = $order->billing_city;
                $paycore_params['billing_postcode']   = $order->billing_postcode;
                $paycore_params['billing_country']    = $order->billing_country;
            }
        }

        wp_localize_script( 'woocommerce_paycore', 'wc_paycore_params', apply_filters( 'wc_paycore_params', $paycore_params ) );
    }

    /**
     * Get the return url (thank you page).
     *
     * @param WC_Order $order
     * @return string
     */
    public function get_return_url( $order = null ) {
        if ( $order ) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
        }

        if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
            $return_url = str_replace( 'http:', 'https:', $return_url );
        }

        return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
    }

    /**
     * Process the payment
     *
     * @param int  $order_id Reference.
     * @param bool $retry Should we retry on fail.
     * @param bool $force_customer Force user creation.
     *
     * @throws Exception If payment will not be accepted.
     *
     * @return array
     */
    public function process_payment( $order_id, $retry = true, $force_customer = false ) {
        global $woocommerce;

        try {
            $this->log( sprintf( __( 'Start process payment for order: %s', 'woocommerce-gateway-paycore' ), $order_id ) );

            $order  = wc_get_order( $order_id );
            $ipnUrl = add_query_arg('wc-api', 'wc_gateway_paycore', home_url('/'));
            $returnUrl = $this->get_return_url($order);
            $woocommerce->cart->empty_cart();

            $data = [
                'amount' => $this->get_paycore_amount($order->get_total()),
                'currency' => strtoupper( get_woocommerce_currency() ),
                'public_key' => $this->publishable_key,
                'payment_method' => $_POST['payment_method'],
                'reference' => strval($order->id),
                'return_url' => $returnUrl,
            ];

            if (!empty($ipnUrl)) {
                $data['ipn_url'] = $ipnUrl;
            }

            $dataString = self::encodeData($data);
            $signature = $this->generateDataSignature($data);

            return array(
                'result'   => 'success',
                'data' => $dataString,
                'signature' => $signature,
            );
        } catch ( Exception $e ) {
            wc_add_notice( $e->getMessage(), 'error' );
            $this->log( sprintf( __( 'Error: %s', 'woocommerce-gateway-paycore' ), $e->getMessage() ) );

            if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
                $this->send_failed_order_email( $order_id );
            }

            do_action( 'wc_gateway_paycore_process_payment_error', $e, $order );

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * Store extra meta data for an order from a Paycore Response.
     */
    public function process_response( $response ) {
        $body = json_decode(file_get_contents('php://input', 'rb'), true);

        if (!(isset($body['data']) && isset($body['signature']))) {
            $this->log( sprintf( __( 'Error: In the response from PayCore.io server there are no POST parameters "data" and "signature"', 'woocommerce-gateway-paycore' )) );

            return null;
        }

        $data = $body['data'];
        $receivedSignature = $body['signature'];


        // DON'T delete this block, be careful of fraud!!!
        if (!$this->securityOrderCheck($data, $receivedSignature)) {
            $this->log( sprintf( __( 'Error: Signature check failed. WARNING be careful of fraud', 'woocommerce-gateway-paycore' )) );

            return null;
        }

        $decodedData = $this->getDecodedData($data);
        $status = $decodedData['state'];
        $orderId = $decodedData['reference'];

        try {
            $this->log( sprintf( __( 'Retrieve IPN for order: %s. Payment status: %s', 'woocommerce-gateway-paycore' ), $orderId, $body['state'] ) );
            $order  = wc_get_order( $orderId );

            if(!$order->needs_payment() && $status !== 'success') {
                return $response;
            }
            switch ($status) {
                case 'success':
                    $order->payment_complete();
                    $order->add_order_note(__('Payment is successfully processed by PayCore.io', 'woocommerce-gateway-paycore'));
                    break;
                case 'pending':
                    $order->update_status('pending');
                    break;
                case 'cancelled':
                    $order->update_status('cancelled');
                    break;
                case 'failure':
                case 'expired':
                    $order->update_status('failed');
                    $this->send_failed_order_email($orderId);
                    break;
            }

            exit();
        } catch (Exception $e) {
            $this->log( sprintf( __( 'Error: %s', 'woocommerce-gateway-paycore' ), $e->getMessage() ) );
        }

        return $response;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function getDecodedData($data)
    {
        return json_decode(base64_decode($data), true, 1024);
    }

    /**
     * @param $data
     * @return string
     */
    public static function encodeData($data)
    {
        return base64_encode(json_encode($data));
    }

    /**
     * @param $data
     * @param $receivedSignature
     * @return bool
     */
    public function securityOrderCheck($data, $receivedSignature)
    {
        $secretKey = self::get_secret_key();
        $generatedSignature = base64_encode(sha1( $secretKey . $data . $secretKey, 1));

        return $receivedSignature === $generatedSignature;
    }

    /**
     * @param $data array
     * @return string
     */
    public function generateDataSignature($data)
    {
        $secretKey = self::get_secret_key();
        $encodedData = self::encodeData($data);
        $signature = self::generateStringSignature($secretKey . $encodedData . $secretKey);

        return $signature;
    }

    /**
     * @param $string
     * @return string
     */
    public static function generateStringSignature($string)
    {
        return base64_encode(sha1($string, 1));
    }

    /**
     * Sends the failed order email to admin
     *
     * @version 3.1.0
     * @since 3.1.0
     * @param int $order_id
     * @return null
     */
    public function send_failed_order_email( $order_id ) {
        $emails = WC()->mailer()->get_emails();
        if ( ! empty( $emails ) && ! empty( $order_id ) ) {
            $emails['WC_Email_Failed_Order']->trigger( $order_id );
        }
    }

    /**
     * Logs
     *
     * @since 3.1.0
     * @version 3.1.0
     *
     * @param string $message
     */
    public function log( $message ) {
        if ( $this->logging ) {
            WC_Paycore::log( $message );
        }
    }

    /**
     * Get secret key.
     * @return string
     */
    public static function get_secret_key() {
        if ( ! self::$secret_key ) {
            $options = get_option( 'woocommerce_paycore_settings' );

            if ( isset( $options['testmode'], $options['secret_key'], $options['test_secret_key'] ) ) {
                self::set_secret_key( 'yes' === $options['testmode'] ? $options['test_secret_key'] : $options['secret_key'] );
            }
        }
        return self::$secret_key;
    }

    /**
     * @param $secret_key
     */
    public static function set_secret_key( $secret_key ) {
        self::$secret_key = $secret_key;
    }


    /**
     * Get the plugin URL
     */
    function get_plugin_url(){
        if(isset($this->plugin_url)){
            return $this->plugin_url;
        }

        if(is_ssl()){
            return $this->plugin_url = str_replace('http://', 'https://', WP_PLUGIN_URL) . "/" . plugin_basename(dirname(dirname(__FILE__)));
        } else {
            return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename(dirname(dirname(__FILE__)));
        }
    }
}
