<?php

namespace App\Support;

/** The caption above a signature on a planning document: the Role typed for it, or the document's standard caption when none was typed. */
class SignatoryCaption
{
    public static function of(?string $typedRole, string $default): string
    {
        $role = trim((string) $typedRole);

        return $role === '' ? $default : rtrim($role, ": \t").':';
    }
}
