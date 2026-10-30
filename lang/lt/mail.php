<?php

declare(strict_types=1);

return [
    'order_confirmation' => [
        'subject' => 'Jūsų užsakymas :number patvirtintas',
        'greeting' => 'Dėkojame už užsakymą, :name.',
        'intro' => 'Gavome jūsų :total apmokėjimą už užsakymą :number.',
        'product' => 'Prekė',
        'quantity' => 'Kiekis',
        'price' => 'Kaina su PVM',
        'subtotal' => 'Tarpinė suma be PVM',
        'shipping' => 'Pristatymas: :method',
        'vat' => 'PVM :rate%',
        'total' => 'Iš viso',
        'delivery' => 'Pristatymas',
        'address' => 'Adresas: :address',
        'view_order' => 'Peržiūrėti užsakymą',
        'questions' => 'Klausimų dėl užsakymo? Rašykite adresu :email ir nurodykite užsakymo numerį.',
        'thanks' => 'Ačiū,',
    ],

    'thanks' => 'Ačiū,',

    'customer_verify_email' => [
        'subject' => 'Patvirtinkite savo el. pašto adresą',
        'greeting' => 'Sveiki, :name.',
        'intro' => 'Ačiū, kad susikūrėte paskyrą :app. Patvirtinkite savo el. pašto adresą, kad galėtumėte ją naudoti.',
        'button' => 'Patvirtinti el. pašto adresą',
        'expires' => 'Ši nuoroda galioja :hours val. Jei ji nebegalioja, prisijunkite ir paprašykite naujos.',
        'ignore' => 'Jei šios paskyros nekūrėte, ignoruokite šį laišką. Paskyra bus pašalinta.',
    ],

    'contact_request' => [
        'subject' => 'Nauja užklausa nuo :name',
        'title' => 'Nauja užklausa',
        'name' => 'Vardas',
        'email' => 'El. paštas',
        'phone' => 'Telefonas',
        'message' => 'Žinutė',
        'attachments' => 'Priedai (:count)',
        'view' => 'Peržiūrėti administravime',
    ],

    'order_paid' => [
        'subject' => 'Naujas apmokėtas užsakymas :number (:total)',
        'title' => 'Naujas apmokėtas užsakymas :number',
        'intro' => 'Apmokėtas ir laukia peržiūros.',
        'customer' => 'Pirkėjas',
        'email' => 'El. paštas',
        'phone' => 'Telefonas',
        'product' => 'Prekė',
        'sku' => 'SKU',
        'quantity' => 'Kiekis',
        'total_incl_vat' => 'Suma su PVM',
        'delivery' => 'Pristatymas',
        'delivery_line' => ':method (:price su PVM)',
        'address' => 'Adresas',
        'total' => 'Iš viso',
        'total_line' => ':total (PVM :rate%: :vat)',
        'note' => 'Pirkėjo pastaba',
        'open' => 'Atidaryti užsakymą',
    ],

    'payment_problem' => [
        'subject' => 'Mokėjimą reikia peržiūrėti: užsakymas :number',
        'title' => 'Mokėjimą reikia peržiūrėti: užsakymas :number',
        'order' => 'Užsakymas',
        'customer' => 'Pirkėjas',
        'order_total' => 'Užsakymo suma',
        'open' => 'Atidaryti užsakymą',
        'explanations' => [
            'paid_after_expiry' => 'Pirkėjas sumokėjo po to, kai užsakymas jau buvo pasibaigęs, o prekių sandėlyje nebėra. Užsakymas paliktas pasibaigusiu. Grąžinkite pinigus OPAY portale arba grąžinkite prekes į sandėlį ir paprašykite programuotojo atkurti užsakymą.',
            'paid_after_cancel' => 'Pirkėjas sumokėjo už atšauktą užsakymą. Užsakymas paliktas atšauktu. Grąžinkite pinigus OPAY portale.',
            'double_payment' => 'Pirkėjas už šį užsakymą sumokėjo daugiau nei vieną kartą. Užsakymas apmokėtas, dėl papildomo mokėjimo nieko nepadaryta. Grąžinkite papildomą mokėjimą OPAY portale.',
            'amount_mismatch' => 'Mokėjimų teikėjas nurodo, kad pirkėjas sumokėjo kitokią sumą arba kita valiuta nei užsakymo. Užsakymas paliktas neapmokėtu. Patikrinkite mokėjimą OPAY portale: grąžinkite pinigus arba susisiekite su pirkėju.',
            'default' => 'Šį mokėjimą reikia peržiūrėti.',
        ],
    ],
];
