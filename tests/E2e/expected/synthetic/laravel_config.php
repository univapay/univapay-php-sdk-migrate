<?php

use Univapay\Compat\Requests\Handlers\BasicRetryHandler;
use Univapay\Compat\Requests\Handlers\RateLimitHandler;

/**
 * E2E synthetic fixture: a Laravel-ish config file returning a plain array of FQCN strings, in
 * the style of a `config/services.php` binding table. The `::class` constant fetch and the
 * literal string value below are both real AST/string nodes Rector's rename rules DO touch (a
 * Name node for `::class`, a String_ node for the literal), so both come out clean, fully
 * migrated, with no manual follow-up needed.
 *
 * The trailing comment is deliberately NOT touched by Rector -- there is no AST node
 * representing a comment's text content for RenameStringRector or RenameClassRector to rewrite,
 * only the enclosing statement's leading-comment attachment point that MarkerCommentTrait uses
 * for its own idempotent markers (a different mechanism entirely; this is a *pre-existing*
 * human-authored comment, not one of ours). This is exactly the class of case the post-scan's
 * repo-wide residual scan (section (b)) exists to catch: a raw old-namespace substring left
 * behind in an otherwise fully-migrated file, in a place Rector cannot safely touch (rewriting
 * inside a comment could corrupt unrelated prose it has no way to distinguish from a real
 * reference).
 */
return [
    'retry_handler' => BasicRetryHandler::class,
    'server_error_class' => 'Univapay\Compat\Errors\UnivapayServerError',
    'rate_limit_handler' => RateLimitHandler::class,

    // legacy note: this binding used to point at Univapay\RequestsHandlers\RateLimitHandler
    // before we fixed the namespace typo in our own app code; kept here for the changelog.
    'notes' => 'see CHANGELOG for the RateLimitHandler binding fix',
];
