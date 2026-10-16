<?php

namespace App\Support;

/** The forms the Form Generator can make from the SOB. A form is added here when the owner supplies its document. */
final class GeneratedForms
{
    /** @return array<string, array{key: string, title: string, description: string}> */
    public static function all(): array
    {
        return [
            'pow' => ['key' => 'pow', 'title' => 'Program of Works', 'description' => 'The materials and labor of a project, made from the SOB items of a program and activity.'],
        ];
    }
}
