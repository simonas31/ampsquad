<?php

declare(strict_types=1);

// Backend-rendered labels (breadcrumbs, SEO, emails) — kept in sync by hand
// with resources/js/i18n/lt.json's "nav" keys, which cover the same terms
// for client-side UI chrome. Two small dictionaries, not one shared
// source, because the frontend (useT hook) and backend (Laravel
// translations) i18n mechanisms are different systems with little else to
// unify.
return [
    'home' => 'Pradžia',
    'projects' => 'Projektai',
    'about' => 'Apie mus',
    'price_calculator' => 'Kainos skaičiuoklė',
    'shop' => 'Parduotuvė',
    'cart' => 'Krepšelis',
    'checkout' => 'Užsakymo pateikimas',
    'order' => 'Užsakymas',
    'contact' => 'Kontaktai',
    'login' => 'Prisijungti',
    'register' => 'Sukurti paskyrą',
    'account' => 'Mano paskyra',
    'verify_email' => 'Patvirtinkite el. paštą',
    'forgot_password' => 'Pamiršote slaptažodį?',
    'reset_password' => 'Pasirinkite naują slaptažodį',
    'security' => 'El. paštas ir slaptažodis',
    'profile' => 'Profilis',
    'addresses' => 'Adresai',
    'new_address' => 'Naujas adresas',
    'edit_address' => 'Redaguoti adresą',
];
