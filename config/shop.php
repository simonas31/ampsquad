<?php

declare(strict_types=1);

return [

    /*
     * VAT, in whole percent, added to every product's price excl. VAT (which
     * the catalog import supplies and products store) to give its price incl.
     * VAT. A change of rate is a change here and never a change of data.
     */
    'vat_rate' => (int) env('SHOP_VAT_RATE', 21),

    /*
     * Which payment provider takes the money: `opay` (see config/services.php
     * for its settings) or `fake`, which only exists for local work and tests
     * and refuses to run in production.
     */
    'payment_gateway' => env('PAYMENT_GATEWAY', 'opay'),

];
