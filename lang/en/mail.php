<?php

declare(strict_types=1);

return [
    'order_confirmation' => [
        'subject' => 'Your order :number is confirmed',
        'greeting' => 'Thank you for your order, :name.',
        'intro' => 'We have received your payment of :total for order :number.',
        'product' => 'Product',
        'quantity' => 'Qty',
        'price' => 'Price incl. VAT',
        'subtotal' => 'Subtotal excl. VAT',
        'shipping' => 'Delivery: :method',
        'vat' => 'VAT :rate%',
        'total' => 'Total',
        'delivery' => 'Delivery',
        'address' => 'Address: :address',
        'view_order' => 'View your order',
        'questions' => 'Questions about your order? Write to :email and quote the order number.',
        'thanks' => 'Thank you,',
    ],

    'thanks' => 'Thanks,',

    'contact_request' => [
        'subject' => 'New contact request from :name',
        'title' => 'New contact request',
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'message' => 'Message',
        'attachments' => 'Attachments (:count)',
        'view' => 'View in admin',
    ],

    'order_paid' => [
        'subject' => 'New paid order :number (:total)',
        'title' => 'New paid order :number',
        'intro' => 'Paid and waiting to be looked at.',
        'customer' => 'Customer',
        'email' => 'Email',
        'phone' => 'Phone',
        'product' => 'Product',
        'sku' => 'SKU',
        'quantity' => 'Qty',
        'total_incl_vat' => 'Total incl. VAT',
        'delivery' => 'Delivery',
        'delivery_line' => ':method (:price incl. VAT)',
        'address' => 'Address',
        'total' => 'Total',
        'total_line' => ':total (VAT :rate%: :vat)',
        'note' => 'Customer\'s note',
        'open' => 'Open the order',
    ],

    'payment_problem' => [
        'subject' => 'Payment needs attention: order :number',
        'title' => 'Payment needs attention: order :number',
        'order' => 'Order',
        'customer' => 'Customer',
        'order_total' => 'Order total',
        'open' => 'Open the order',
        'explanations' => [
            'paid_after_expiry' => 'The customer paid after the order had expired, and the goods are no longer in stock. The order was left expired. Refund the payment in the OPAY portal, or put the goods back in stock and ask a developer to reinstate the order.',
            'paid_after_cancel' => 'The customer paid an order that was cancelled. The order was left cancelled. Refund the payment in the OPAY portal.',
            'double_payment' => 'The customer paid this order more than once. The order is paid, and nothing was done for the extra payment. Refund the extra payment in the OPAY portal.',
            'amount_mismatch' => 'The provider says the customer paid an amount, or in a currency, that is not the order\'s. The order was left unpaid. Check the payment in the OPAY portal: refund it, or contact the customer.',
            'default' => 'This payment needs a person to look at it.',
        ],
    ],
];
