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

    'customer_verify_email' => [
        'subject' => 'Confirm your email address',
        'greeting' => 'Welcome, :name.',
        'intro' => 'Thank you for creating an account at :app. Confirm your email address to start using it.',
        'button' => 'Confirm email address',
        'expires' => 'This link works for :hours hours. If it has expired, log in and ask for a new one.',
        'ignore' => 'If you did not create this account, ignore this email and the account will be removed.',
    ],

    'customer_password_reset' => [
        'subject' => 'Reset your password',
        'greeting' => 'Hello, :name.',
        'intro' => 'We received a request to reset the password of your account. Use the button below to choose a new one.',
        'button' => 'Choose a new password',
        'expires' => 'This link works for :minutes minutes and can be used once.',
        'ignore' => 'If you did not ask for this, ignore this email. Your password stays as it is.',
    ],

    'customer_password_changed' => [
        'subject' => 'Your password was changed',
        'greeting' => 'Hello, :name.',
        'text' => 'The password of your account was just changed. You can now log in with the new one.',
        'not_you' => 'If this was not you, choose a new password now and write to :email so that we can help.',
        'button' => 'Reset your password',
    ],

    'customer_confirm_new_email' => [
        'subject' => 'Confirm your new email address',
        'greeting' => 'Hello, :name.',
        'intro' => 'You asked to use this address for your account at :app. Confirm it to finish the change.',
        'button' => 'Confirm new email address',
        'expires' => 'This link works for :hours hours. Until you confirm, your account keeps using its old address.',
        'ignore' => 'If you did not ask for this, ignore this email and nothing will change.',
    ],

    'customer_account_deleted' => [
        'subject' => 'Your account was deleted',
        'greeting' => 'Hello, :name.',
        'text' => 'Your account at :app was deleted. You can no longer log in with it.',
        'retention' => 'We keep your details and your orders in our records for :years years and then remove them.',
        'again' => 'You can create a new account with the same email address at any time.',
        'not_you' => 'If you did not delete this account, write to :email at once so that we can help.',
    ],

    'customer_email_changed' => [
        'subject' => 'The email address of your account was changed',
        'greeting' => 'Hello, :name.',
        'text' => 'The email address of your account was changed to :email. From now on we write to that address.',
        'not_you' => 'If this was not you, write to :email at once so that we can help.',
    ],

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
