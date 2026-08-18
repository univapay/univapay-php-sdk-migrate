<?php

declare(strict_types=1);

namespace Univapay\Migrate\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Univapay\Migrate\GuideUrl;
use Univapay\Migrate\Rector\Rule\Concern\MarkerCommentTrait;

/**
 * Flags two categories of `Name` node reference that {@see
 * \Univapay\Migrate\ClassMap::SUPPORTED} deliberately does NOT rename, because renaming them
 * would be actively misleading:
 *
 * 1. **Internal-API usage** -- `Univapay\Requests\Requester`, `Univapay\Requests\HttpRequester`,
 *    `Univapay\Requests\RequestContext`, `Univapay\Utility\HttpUtils`, and
 *    `Univapay\Utility\RequesterUtils`. These five purely-internal transport(-coupled) classes
 *    have no compat replacement at all (see the exclusion note on `ClassMap::SUPPORTED`'s doc
 *    block -- the last two are included because both take a `RequestContext`/
 *    `WpOrg\Requests\Response` argument on every method, so there is nothing to port verbatim;
 *    the compat package reimplements their behavior in `Support\ApiCaller` /
 *    `Support\ExceptionMapper` instead). Referencing any of the five is a hard compile error once
 *    `univapay/php-sdk` is removed, so the marker explains that instead of silently leaving
 *    broken code. Matched by exact FQCN equality (all five are leaf classes with no
 *    sub-namespace beneath them in the real SDK tree, so an exact match is more precise than a
 *    literal string-prefix match and avoids any hypothetical false positive on a same-prefixed
 *    but unrelated class name).
 * 2. **Network-exception usage** -- any `WpOrg\Requests\*` reference (a true namespace-prefix
 *    match: `rmccue/requests` is not a compat dependency at all, and there are many classes
 *    under this namespace a consumer might reference, e.g. `WpOrg\Requests\Exception` in a
 *    custom `RequestHandler` subclass's catch type). The new transport throws
 *    `UnivapayNetworkError` on connection failure, never a `WpOrg\Requests\Exception`, so any
 *    consumer code matching on the old exception type silently stops retrying/handling network
 *    errors after migration.
 *
 * Distinct marker text per category (`@univapay-migrate:internal-api` /
 * `@univapay-migrate:network-exception`) so `bin/univapay-migrate`'s post-scan can report them
 * separately.
 */
final class FlagInternalApiUsageRector extends AbstractRector
{
    use MarkerCommentTrait;

    /**
     * Kept in sync by hand with `bin/univapay-migrate`'s `MARKER_INTERNAL_API` constant (see the
     * note on {@see \Univapay\Migrate\Rector\Rule\FlagUnsupportedFeatureRector::MARKER}).
     */
    public const MARKER_INTERNAL_API = '@univapay-migrate:internal-api';

    /**
     * Marker for the WpOrg\Requests\ fold-in. `bin/univapay-migrate`'s post-scan recognizes and
     * reports it as its own counted category.
     */
    public const MARKER_NETWORK_EXCEPTION = '@univapay-migrate:network-exception';

    /**
     * Exact-match only -- see class doc block for why prefix matching would be imprecise here.
     *
     * @var string[]
     */
    private const INTERNAL_API_CLASSES = [
        'Univapay\Requests\Requester',
        'Univapay\Requests\HttpRequester',
        'Univapay\Requests\RequestContext',
        'Univapay\Utility\HttpUtils',
        'Univapay\Utility\RequesterUtils',
    ];

    private const NETWORK_EXCEPTION_PREFIX = 'WpOrg\\Requests\\';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Flag internal-API (Univapay\Requests\{Requester,HttpRequester,RequestContext}, '
                . 'Univapay\Utility\{HttpUtils,RequesterUtils}) and WpOrg\Requests\* references '
                . 'with an idempotent explanatory marker comment.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Univapay\Requests\HttpRequester;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// @univapay-migrate:internal-api Requests\HttpRequester — no compat replacement exists; this is a hard compile error once univapay/php-sdk is removed. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#internal-api-usage
use Univapay\Requests\HttpRequester;
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Name::class];
    }

    public function refactor(Node $node): ?Node
    {
        $resolved = $this->getName($node);
        if ($resolved === null) {
            return null;
        }

        if (in_array($resolved, self::INTERNAL_API_CLASSES, true)) {
            $comment = sprintf(
                '// %s %s — no compat replacement exists; this is a hard compile error once '
                    . 'univapay/php-sdk is removed. See %s#internal-api-usage',
                self::MARKER_INTERNAL_API,
                $this->shortName($resolved),
                GuideUrl::MIGRATION_GUIDE
            );

            return $this->insertMarkerComment($node, $comment);
        }

        if (strncmp($resolved, self::NETWORK_EXCEPTION_PREFIX, strlen(self::NETWORK_EXCEPTION_PREFIX)) === 0) {
            $comment = sprintf(
                '// %s %s — this exception type is never thrown by the new transport; network '
                    . 'failures now throw UnivapayNetworkError instead. See %s#network-exceptions',
                self::MARKER_NETWORK_EXCEPTION,
                $resolved,
                GuideUrl::MIGRATION_GUIDE
            );

            return $this->insertMarkerComment($node, $comment);
        }

        return null;
    }

    private function shortName(string $fqcn): string
    {
        if (strncmp($fqcn, 'Univapay\\', 9) === 0) {
            return substr($fqcn, 9);
        }

        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
