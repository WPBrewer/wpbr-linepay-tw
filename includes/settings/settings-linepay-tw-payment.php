<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings for LINE Pay Taiwan Payment Gateway
 */
return array(

    'enabled' => array(
        'title'       => __( 'Enable/Disable', 'woo-linepay-tw' ),
        'type'        => 'checkbox',
        'label'       => __( 'Enable', 'woo-linepay-tw' ),
        'default'     => 'no'
    ),
    'title' => array(
        'title'       => __( 'Title', 'woo-linepay-tw' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woo-linepay-tw'),
        'default'     => __( 'LINE Pay Taiwan Payment Gateway', 'woo-linepay-tw' ),
        'desc_tip'    => true,
    ),
    'description' => array(
        'title'       => __( 'Description', 'woo-linepay-tw' ),
        'type'        => 'textarea',
        'description' => __('This controls the description which the user sees during checkout.', 'woo-linepay-tw'),
        'desc_tip'    => true,
    ),


);
