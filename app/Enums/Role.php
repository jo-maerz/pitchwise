<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case OrgAdmin = 'org_admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::OrgAdmin => 'Organization admin',
            self::User => 'User',
        };
    }
}
