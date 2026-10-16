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
}
