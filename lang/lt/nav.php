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
    'contact' => 'Kontaktai',
];
