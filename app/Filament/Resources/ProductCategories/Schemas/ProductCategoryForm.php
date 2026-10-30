<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Filament\Schemas\BilingualFields;
use App\Models\ProductCategory;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label(__('admin.fields.parent'))
                    ->helperText(__('admin.product_categories.parent_helper'))
                    ->placeholder(__('admin.product_categories.top_level'))
                    ->options(fn (?ProductCategory $record): array => self::parentOptions($record))
                    ->rule(fn (?ProductCategory $record): Closure => self::mustNotBeInOwnBranch($record))
                    ->searchable()
                    ->columnSpanFull(),
                // Both languages are required, unlike on most content: the
                // shop shows every category in its menu and filter links in
                // either language, and a category without a name or slug in
                // one of them would show up blank there.
                BilingualFields::make(
                    TextInput::class,
                    'name',
                    __('admin.fields.name'),
                    fn (TextInput $field, string $locale) => $field
                        ->required()
                        ->maxLength(255)
                        ->helperText($locale === BilingualFields::locales()[0] ? __('admin.product_categories.name_helper') : null)
                        ->rule(fn (Get $get, ?ProductCategory $record): Closure => self::mustBeUniqueAmongSiblings($locale, $get, $record)),
                ),
                BilingualFields::slug(),
            ]);
    }

    /**
     * Every category with its whole path, minus the category being edited
     * and everything below it: moving a category under itself would cut the
     * branch off from the tree and make it loop.
     *
     * @return array<int, string>
     */
    private static function parentOptions(?ProductCategory $record): array
    {
        $paths = ProductCategory::pathLabels();

        return $record === null
            ? $paths
            : array_diff_key($paths, array_flip(ProductCategory::idsWithDescendants($record->id)));
    }

    /**
     * The same rule again on the server, for a value that did not come from
     * the list of options.
     */
    private static function mustNotBeInOwnBranch(?ProductCategory $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if ($record !== null && filled($value) && in_array((int) $value, ProductCategory::idsWithDescendants($record->id), true)) {
                $fail(__('admin.product_categories.parent_in_own_branch'));
            }
        };
    }

    /**
     * A catalog import finds a category by the Lithuanian names along its
     * path, so two categories under one parent may not share a Lithuanian
     * name (ignoring case and accents): the import could not tell them
     * apart. The other languages are free, since nothing looks them up.
     */
    private static function mustBeUniqueAmongSiblings(string $locale, Get $get, ?ProductCategory $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($locale, $get, $record): void {
            if ($locale !== BilingualFields::locales()[0] || blank($value)) {
                return;
            }

            $isTaken = ProductCategory::query()
                ->where('parent_id', filled($get('parent_id')) ? (int) $get('parent_id') : null)
                ->when($record !== null, fn (Builder $siblings) => $siblings->whereKeyNot($record->id))
                ->whereRaw('json_unquote(json_extract(`name`, ?)) collate utf8mb4_unicode_ci = ?', ['$."'.$locale.'"', $value])
                ->exists();

            if ($isTaken) {
                $fail(__('admin.product_categories.name_taken'));
            }
        };
    }
}
