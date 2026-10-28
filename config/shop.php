<?php

declare(strict_types=1);

return [

    /*
     * VAT, in whole percent, added to every product's price excl. VAT (which
     * the catalog import supplies and products store) to give its price incl.
     * VAT. A change of rate is a change here and never a change of data.
     */
    'vat_rate' => (int) env('SHOP_VAT_RATE', 21),

];
