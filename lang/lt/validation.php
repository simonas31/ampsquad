<?php

declare(strict_types=1);

/**
 * Hand-authored rather than pulling in a full third-party translation
 * package — covers the validation rules actually used across the app
 * (public Contact form + Filament resource forms), not Laravel's entire
 * rule set. Add a key here the first time a new rule actually needs it.
 */
return [
    'accepted' => ':attribute laukas turi būti pažymėtas.',
    'active_url' => ':attribute nėra galiojantis URL.',
    'after' => ':attribute turi būti data po :date.',
    'after_or_equal' => ':attribute turi būti data lygi arba vėlesnė nei :date.',
    'alpha' => ':attribute gali turėti tik raides.',
    'alpha_dash' => ':attribute gali turėti tik raides, skaičius, brūkšnelius ir pabraukimus.',
    'alpha_num' => ':attribute gali turėti tik raides ir skaičius.',
    'array' => ':attribute turi būti sąrašas.',
    'before' => ':attribute turi būti data prieš :date.',
    'before_or_equal' => ':attribute turi būti data lygi arba ankstesnė nei :date.',
    'between' => [
        'array' => ':attribute turi turėti nuo :min iki :max elementų.',
        'file' => ':attribute turi būti nuo :min iki :max kilobaitų.',
        'numeric' => ':attribute turi būti nuo :min iki :max.',
        'string' => ':attribute turi būti nuo :min iki :max simbolių.',
    ],
    'boolean' => ':attribute laukas turi būti taip arba ne.',
    'confirmed' => ':attribute patvirtinimas nesutampa.',
    'date' => ':attribute nėra galiojanti data.',
    'date_format' => ':attribute neatitinka formato :format.',
    'different' => ':attribute ir :other turi skirtis.',
    'digits' => ':attribute turi būti :digits skaitmenų.',
    'email' => ':attribute turi būti galiojantis el. pašto adresas.',
    'exists' => 'Pasirinktas :attribute yra neteisingas.',
    'file' => ':attribute turi būti failas.',
    'image' => ':attribute turi būti nuotrauka.',
    'in' => 'Pasirinktas :attribute yra neteisingas.',
    'integer' => ':attribute turi būti sveikasis skaičius.',
    'ip' => ':attribute turi būti galiojantis IP adresas.',
    'max' => [
        'array' => ':attribute negali turėti daugiau nei :max elementų.',
        'file' => ':attribute negali būti didesnis nei :max kilobaitų.',
        'numeric' => ':attribute negali būti didesnis nei :max.',
        'string' => ':attribute negali būti ilgesnis nei :max simbolių.',
    ],
    'mimes' => ':attribute turi būti failas tokio tipo: :values.',
    'min' => [
        'array' => ':attribute turi turėti bent :min elementų.',
        'file' => ':attribute turi būti bent :min kilobaitų.',
        'numeric' => ':attribute turi būti bent :min.',
        'string' => ':attribute turi būti bent :min simbolių.',
    ],
    'numeric' => ':attribute turi būti skaičius.',
    'prohibited' => ':attribute laukas yra draudžiamas.',
    'regex' => ':attribute formatas yra neteisingas.',
    'required' => ':attribute laukas yra privalomas.',
    'required_if' => ':attribute laukas yra privalomas, kai :other yra :value.',
    'string' => ':attribute turi būti tekstas.',
    'unique' => 'Toks :attribute jau egzistuoja.',
    'url' => ':attribute formatas yra neteisingas.',

    'attributes' => [
        'name' => 'vardas',
        'email' => 'el. paštas',
        'phone' => 'telefonas',
        'message' => 'žinutė',
        'title' => 'pavadinimas',
        'slug' => 'nuoroda',
        'category_id' => 'kategorija',
        'status' => 'būsena',
        'order' => 'eilė',
    ],
];
