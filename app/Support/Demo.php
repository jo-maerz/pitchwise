<?php

namespace App\Support;

use App\Enums\Role;
use RuntimeException;

/**
 * The demo accounts and their Demo Music School, seeded by DemoSeeder. Visitors share their password, so the accounts
 * cannot be changed or deleted (gate `change-account`) and practice:reset-demo puts everything back every night.
 */
final class Demo
{
    public const SCHOOL = 'Demo Music School';

    /** Visitors know the demo emails (they are in the README), so on a public site the password is all that protects them. */
    private const MIN_PUBLIC_PASSWORD = 12;

    private const ADMIN = 'admin@example.com';

    /** @var array<string, array{name: string, role?: Role, school?: true, annotation_instruments?: list<string>}> */
    private const ACCOUNTS = [
        self::ADMIN => ['name' => 'Demo Admin', 'role' => Role::Admin],
        'teacher@example.com' => ['name' => 'Demo Teacher', 'role' => Role::OrgAdmin, 'school' => true],
        'demo@example.com' => ['name' => 'Demo Violinist', 'school' => true, 'annotation_instruments' => ['violin', 'viola', 'cello']],
        'private@example.com' => ['name' => 'Demo Private User'],
    ];

    /** @return list<string> */
    public static function emails(): array
    {
        return array_keys(self::ACCOUNTS);
    }

    /**
     * The accounts to seed. A public site has no demo admin: with the shared password anyone could take the role away from the real admins.
     *
     * @return array<string, array{name: string, role?: Role, school?: true, annotation_instruments?: list<string>}>
     */
    public static function accounts(): array
    {
        return self::onPublicSite() ? array_diff_key(self::ACCOUNTS, [self::ADMIN => true]) : self::ACCOUNTS;
    }

    /** DEMO_PASSWORD, or "password" on this machine. Refuses on a public site unless DEMO_PASSWORD is long enough. */
    public static function password(): string
    {
        $password = (string) config('practice.demo_password');
        if (! self::onPublicSite()) {
            return $password !== '' ? $password : 'password';
        }
        if (mb_strlen($password) < self::MIN_PUBLIC_PASSWORD) {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST);
            throw new RuntimeException('Demo accounts are seeded on '.$host.' only with a DEMO_PASSWORD of at least '.self::MIN_PUBLIC_PASSWORD.' characters: everyone knows their email addresses. Seed CatalogueSeeder alone for the shared library without them.');
        }

        return $password;
    }

    public static function onPublicSite(): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return app()->isProduction() && ! in_array($host, ['localhost', '127.0.0.1'], true);
    }
}
