<?php

namespace App\Support;

/** The categories an SOB particulars (and so an APP line) is filed under, in the order the APP shows and prints them. */
class AppCategories
{
    public const ALL = ['Common Office Supply', 'Drugs and Medicine', 'Construction Components & Supplies', 'Paper and Products', 'Common ICT Equipments', 'Other categories'];

    public const DEFAULT = 'Other categories';

    /** A stored category, or the default for a line that has none (for example one generated before categories existed). */
    public static function of(?string $category): string
    {
        return in_array($category, self::ALL, true) ? $category : self::DEFAULT;
    }
}
