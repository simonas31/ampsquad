<?php

declare(strict_types=1);

return [
    'product_unavailable' => 'This product is no longer available.',
    'max_quantity' => 'You can order at most :max of each product.',
    'cart_full' => 'Your cart is full (:max different products at most). Remove something to add more.',
    'out_of_stock' => 'This product is out of stock.',
    'not_enough_stock' => 'Only :count in stock.',
    'not_enough_stock_with_cart' => 'Only :count in stock, and you already have :held in your cart.',
    'all_in_cart' => 'You already have all :count that are in stock in your cart.',
    'checkout' => [
        'refused' => [
            'empty_cart' => 'Your cart is empty. If you have just ordered, check your email for the confirmation.',
            'lines_unavailable' => 'Some products in your cart are no longer available in the quantity you chose. Review your cart and try again.',
            'stale_cart' => 'Prices, quantities or the delivery price changed since you last looked. Review your cart and the new total before you pay.',
            'stock_short' => 'Someone else just bought the last units of a product in your cart. Review your cart and try again.',
            'shipping_unavailable' => 'That delivery method is no longer available. Choose another.',
            'payment_unavailable' => 'We could not start the payment. Nothing was charged. Please try again in a few minutes.',
        ],
    ],
];
