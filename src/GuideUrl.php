<?php

declare(strict_types=1);

namespace Univapay\Migrate;

/**
 * Single source of truth for the portal migration guide URL referenced by every
 * `@univapay-migrate:*` marker comment and by `bin/univapay-migrate`'s "next steps" output.
 *
 * The guide page (`src/content/guides/php-sdk-migration.md` in the docs repo, +
 * `src-ja/content/guides/php-sdk-migration.md`) pins `slug: php-sdk-migration` identically in
 * both `toc.yml` files, nested under "Onboarding Guides" (`onboarding-guides`) > "Guides"
 * (`guides`) -- giving the in-portal path `onboarding-guides/guides/php-sdk-migration` below,
 * matching the `#/http/<group>/<group>/<slug>` hash-routing convention every other cross-guide
 * link in that portal uses (see e.g. `src/content/overview.md`).
 */
final class GuideUrl
{
    public const MIGRATION_GUIDE = 'https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration';
}
