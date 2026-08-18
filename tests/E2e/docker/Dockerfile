# Cross-repo E2E execution. The whole point of this suite is proving migrated consumer code runs
# on the new SDK's actual PHP floor (^7.2 -- see univapay-php-sdk-compat's own composer.json), not
# merely that it Rector-rewrites cleanly under whatever PHP happens to be on the machine running
# the migration tool itself (that machine needs 7.4+ for Rector -- see NOTES.md -- but the
# MIGRATED APPLICATION code has no such floor).
#
# `composer` itself needs PHP >=7.2.5 to run (Composer 2.x's own floor) -- 7.2.34 (this image's
# patch level) clears that with room to spare, so composer install can run in THIS SAME
# container instead of needing a separate, newer-PHP composer stage whose vendor/ output would
# then need to be assumed portable across PHP versions.
#
# ext-curl is already compiled into the official php:7.2-cli image (verified: `php -m | grep
# curl`) -- apimatic/unirest-php (the generated client-sdk's transport) requires it and no
# `docker-php-ext-install` step is needed. ext-json is core.
FROM docker.io/library/php:7.2-cli

# unzip: composer needs it (or ext-zip, not compiled into this base image) to extract dist
# packages downloaded from Packagist (moneyphp/money and its own transitive deps -- the two path
# repositories, compat and client-sdk, never go through this at all). php:7.2-cli's Debian base
# (buster) is EOL -- its normal apt sources 404 -- so point apt at archive.debian.org first.
RUN sed -i 's|deb.debian.org/debian|archive.debian.org/debian|g; s|security.debian.org/debian-security|archive.debian.org/debian-security|g' /etc/apt/sources.list \
    && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until \
    && apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /workspace/consumer
