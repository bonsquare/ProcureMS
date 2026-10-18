<?php

namespace App\Support;

/**
 * The categories an SOB particulars (and so an APP line) is filed under, in the order the APP shows and prints them. An SOB line with no category
 * (the "-" choice, such as a travel budget) is not goods: it is not put in the APP.
 */
class AppCategories
{
    public const ALL = ['Common Office Supplies', 'Drugs and Medicines', 'Construction Components and Supplies', 'Paper Materials and Products', 'Common ICT Equipment', 'Consumables', 'Other Categories'];

    public const DEFAULT = 'Other Categories';

    /** A stored category, or the default for a line that has none (for example one generated before categories existed). */
    public static function of(?string $category): string
    {
        return in_array($category, self::ALL, true) ? $category : self::DEFAULT;
    }

    /** A category as a SOB line shows it: a Part II category by its name, a Part I (Procurement Service) category as it is, "-" when none. */
    public static function label(?string $category): string
    {
        return blank($category) ? '-' : (in_array($category, self::ALL, true) ? $category : (string) $category);
    }

    /** The Procurement Service categories offered for a Part I line, in the order of the latest catalog (read once per request). */
    public static function partOne(): array
    {
        return request()->attributes->get('app_part_one') ?? tap(self::readPartOne(), fn (array $categories) => request()->attributes->set('app_part_one', $categories));
    }

    private static function readPartOne(): array
    {
        $year = \App\Models\CseCatalogItem::max('catalog_year');

        return $year === null ? [] : \App\Models\CseCatalogItem::whereNull('organization_id')->where('catalog_year', $year)
            ->selectRaw('category, min(sort_order) as first_order')->groupBy('category')->orderBy('first_order')->pluck('category')->all();
    }

    /** Every category a SOB line may carry: the Part II categories first, then the Part I ones. */
    public static function allowed(): array
    {
        return array_values(array_unique(array_merge(self::ALL, self::partOne())));
    }
}
