<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Edits a record's translatable attributes in every admin language at once,
 * so nothing has to be switched to write the English text: each attribute is
 * a row of one input per language (state `title.lt`, `title.en`), the first
 * language is required when the field is, and the others are optional.
 *
 * A language that a record already has can not be emptied again from its
 * title or name: that would leave a live URL without a page behind it. The
 * other languages stay optional until they are first written.
 *
 * Replaces the LaraZeus locale switcher on create and edit pages. The pages
 * wire it up through CreatesBilingualRecord and EditsBilingualRecord.
 */
class BilingualFields
{
    /**
     * A row with one input per language.
     *
     * @param  class-string<Field>  $component
     * @param  Closure(Field, string): mixed|null  $configure  Applied to every language's input, given the language code too.
     * @param  list<string>  $requiredWith  Sibling attributes that need this one in the same language, so a language never ends up with a description but no name.
     */
    public static function make(
        string $component,
        string $name,
        string $label,
        ?Closure $configure = null,
        bool $required = false,
        array $requiredWith = [],
    ): Grid {
        $fields = [];

        foreach (self::locales() as $position => $locale) {
            $isFirst = $position === 0;

            $field = $component::make("{$name}.{$locale}")
                ->label($label.' ('.strtoupper($locale).')');

            if ($configure !== null) {
                $configure($field, $locale);
            }

            if ($required && $isFirst) {
                $field->required();
            }

            if ($required && ! $isFirst) {
                $field
                    ->placeholder(__('admin.bilingual.optional_placeholder'))
                    ->required(fn (?Model $record): bool => $record !== null
                        && filled($record->getTranslation($name, $locale, false)));
            }

            if ($requiredWith !== [] && ! $isFirst) {
                $field->requiredWith(array_map(fn (string $sibling): string => "{$sibling}.{$locale}", $requiredWith));
            }

            $fields[] = $field;
        }

        return Grid::make(2)
            ->schema($fields)
            ->columnSpanFull();
    }

    /**
     * The read-only slug of every language, shown on edit pages only.
     */
    public static function slug(): Grid
    {
        return self::make(
            TextInput::class,
            'slug',
            __('admin.fields.slug'),
            fn (TextInput $field, string $locale) => $field
                ->helperText($locale === self::locales()[0] ? __('admin.slug.helper') : null)
                ->disabled()
                ->saved(false),
        )->visibleOn('edit');
    }

    /**
     * The admin languages, the one the fields are required in first.
     *
     * @return list<string>
     */
    public static function locales(): array
    {
        return array_values(filament('spatie-translatable')->getDefaultLocales());
    }

    /**
     * Form state for an edit page: every translatable attribute carries all
     * of the record's languages, empty for the ones it lacks.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stateFor(Model $record, array $data): array
    {
        foreach ($record->getTranslatableAttributes() as $attribute) {
            $translations = $record->getTranslations($attribute);

            $data[$attribute] = collect(self::locales())
                ->mapWithKeys(fn (string $locale): array => [$locale => $translations[$locale] ?? null])
                ->all();
        }

        return $data;
    }

    /**
     * Writes submitted form data onto the record without saving it. A blank
     * language value is removed rather than stored as null. A slug is never
     * submitted (it is derived from the title on save).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fillRecord(Model $record, array $data): void
    {
        $translatable = $record->getTranslatableAttributes();

        foreach ($translatable as $attribute) {
            foreach (self::locales() as $locale) {
                if (! Arr::has($data, "{$attribute}.{$locale}")) {
                    continue;
                }

                $text = Arr::get($data, "{$attribute}.{$locale}");

                if (blank($text)) {
                    $record->forgetTranslation($attribute, $locale);

                    continue;
                }

                $record->setTranslation($attribute, $locale, $text);
            }
        }

        $record->fill(Arr::except($data, $translatable));
    }
}
