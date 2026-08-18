<?php

namespace UnivapayConsumer\Retry;

use Univapay\Requests\Handlers\BasicRetryHandler;
use Univapay\UnivapayClient;

/**
 * E2E synthetic fixture: BasicRetryHandler constructed with a STRING class-name argument -- the
 * critical silent-retry case. BasicRetryHandler's internal matcher is
 * `instanceof $this->exceptionClass` against this string; if RenameStringRector does not rewrite
 * it, the handler silently stops matching (and therefore stops retrying) any real exception thrown
 * after migration, with no error of any kind -- not a compile break, a pure behavior regression.
 * Exercises both single-quoted and double-quoted (with escaped backslashes) literal forms, since
 * Rector's built-in RenameStringRector matches on parsed string *value*, not source-text
 * quoting/escaping.
 *
 * (Doc comment placed after the `use` imports, not before -- see NOTES.md's comment-deletion
 * caveat / synthetic/internal_api_usage.php's own note: a comment leading a `use` statement that
 * `importNames(true)`'s rebuild touches gets silently deleted, not just relocated.)
 */
class RetryHandlerUser
{
    public function addSingleQuoted(UnivapayClient $client): void
    {
        $handler = new BasicRetryHandler(
            'Univapay\Errors\UnivapayServerError',
            5,
            2
        );
        $client->addHandlers($handler);
    }

    public function addDoubleQuotedEscaped(UnivapayClient $client): void
    {
        $handler = new BasicRetryHandler(
            "Univapay\\Errors\\UnivapayRateLimitedError",
            3,
            1
        );
        $client->addHandlers($handler);
    }

    public function addWithFilterClosure(UnivapayClient $client): void
    {
        $handler = new BasicRetryHandler(
            'Univapay\Errors\UnivapayResourceConflictError',
            5,
            2,
            function ($error) {
                return $error->code === 'NON_UNIQUE_ACTIVE_TOKEN';
            }
        );
        $client->setHandlers($handler);
    }
}
