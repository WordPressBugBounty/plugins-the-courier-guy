<?php

/**
 * @author The Courier Guy
 * @package tcg/model
 */
$customPostType = new CustomPostType('product');
$customPostType->addMetaBox(
    'The Courier Guy Settings',
    [
        'form_fields' => [
            'product_free_shipping' => [
                'display_name'  => 'Free Shipping',
                'property_type' => 'checkbox',
                'description'   => __('Enable free shipping for baskets including this product', 'the-courier-guy'),
                'placeholder'   => '',
                'default'       => '0',
            ],
            'product_single_parcel' => [
                'display_name'  => 'Always pack as single parcel',
                'property_type' => 'checkbox',
                'description'   => __('Enable to ensure this item is always packaged alone', 'the-courier-guy'),
                'placeholder'   => '',
                'default'       => '0',
            ],
            'product_prohibit_tcg'  => [
                'display_name'  => 'Prohibit The Courier Guy',
                'property_type' => 'checkbox',
                'description'   => __(
                    'Enable to prohibit The Courier Guy shipping if cart contains this product',
                    'the-courier-guy'
                ),
                'placeholder'   => '',
                'default'       => '0',
            ],
        ]
    ]
);
