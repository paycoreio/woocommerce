<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_paycore_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-paycore' ),
			'label'       => __( 'Enable PayCore.io', 'woocommerce-gateway-paycore' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-gateway-paycore' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-paycore' ),
			'default'     => __( 'Cashless payments (PayCore.io)', 'woocommerce-gateway-paycore' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-gateway-paycore' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-paycore' ),
			'default'     => __( 'Pay cashless via PayCore.io.', 'woocommerce-gateway-paycore' ),
			'desc_tip'    => true,
		),
		'testmode' => array(
			'title'       => __( 'Test mode', 'woocommerce-gateway-paycore' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-paycore' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-paycore' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'test_publishable_key' => array(
			'title'       => __( 'Test Publishable Key', 'woocommerce-gateway-paycore' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your PayCore.io account.', 'woocommerce-gateway-paycore' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_secret_key' => array(
			'title'       => __( 'Test Secret Key', 'woocommerce-gateway-paycore' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your PayCore.io account.', 'woocommerce-gateway-paycore' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'publishable_key' => array(
			'title'       => __( 'Live Publishable Key', 'woocommerce-gateway-paycore' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your PayCore.io account.', 'woocommerce-gateway-paycore' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'secret_key' => array(
			'title'       => __( 'Live Secret Key', 'woocommerce-gateway-paycore' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your PayCore.io account.', 'woocommerce-gateway-paycore' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'payment_methods' => array(
            'title'    => esc_html__( 'Available payment methods', 'woocommerce-plugin-framework' ),
            'type'     => 'payment_methods',
            'desc_tip' => esc_html__( 'Select available payment methods.', 'woocommerce-plugin-framework' ),
        ),
		'statement_descriptor' => array(
			'title'       => __( 'Statement Descriptor', 'woocommerce-gateway-paycore' ),
			'type'        => 'text',
			'description' => __( 'Extra information about a charge.', 'woocommerce-gateway-paycore' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'logging' => array(
			'title'       => __( 'Logging', 'woocommerce-gateway-paycore' ),
			'label'       => __( 'Log debug messages', 'woocommerce-gateway-paycore' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-paycore' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
