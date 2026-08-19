<?php

declare(strict_types=1);

namespace Univapay\Migrate\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Univapay\Migrate\GuideUrl;
use Univapay\Migrate\NativeClassMap;
use Univapay\Migrate\Rector\Rule\Concern\MarkerCommentTrait;

/**
 * The one flag rule behind `UnivapaySetList::COMPAT_TO_NATIVE`: every non-mechanical construct a
 * consumer moving off `univapay/univapay-sdk-compat` onto the native `univapay/client-sdk` needs
 * to review by hand gets an idempotent `// @univapay-migrate:phase2-manual` marker comment above
 * the enclosing statement, naming the category and the native equivalent to migrate to (see
 * {@see NativeClassMap::FLAG_GUIDANCE}). Never rewrites code -- unlike the phase-1 set's flag
 * rules (which flag USES of a class that Rector separately renames to a still-functional stub),
 * phase 2 has nothing to rename most of these TO at all (see NativeClassMap's own doc-block
 * audit), so every flagged construct is left completely untouched other than the marker.
 *
 * Three node shapes, three independent gates, sharing one category -> guidance lookup:
 *
 * 1. **`Name` nodes** (`use`, `new`, `instanceof`, type hints, catch types, `::class`) -- resolved
 *    FQCN checked first against {@see NativeClassMap::FLAG_EXACT_CLASSES} (exact match), then
 *    {@see NativeClassMap::FLAG_NAMESPACE_PREFIXES} (longest-prefix-first, so e.g.
 *    `Resources\Authentication\AppJWT` hits the more specific `client-construction` category
 *    rather than the general `Resources\` `public-property` fallback). Always a definite flag --
 *    referencing a class name is unambiguous regardless of receiver typing, same reasoning as
 *    FlagUnsupportedFeatureRector's Name-node branch.
 * 2. **`MethodCall`/`NullsafeMethodCall` nodes** -- method name checked against
 *    {@see NativeClassMap::FLAG_METHODS}, receiver-type gated exactly like
 *    FlagUnsupportedFeatureRector: resolves to a `Univapay\Compat\*` class -> definite flag;
 *    unresolvable -> `(verify)` flag, but only in a file that references `Univapay\Compat\`
 *    somewhere at all (precision gate); resolves to any OTHER concrete class -> skipped entirely.
 *    `native()` is deliberately never in `FLAG_METHODS` -- calling it is the recommended
 *    mixed-mode escape hatch, not a thing to flag.
 * 3. **`PropertyFetch`/`NullsafePropertyFetch` nodes** -- same receiver-type gate as (2), but keyed
 *    on {@see NativeClassMap::PUBLIC_PROPERTY_PREFIX} instead of an exact class list: every class
 *    under `Univapay\Compat\Resources\` uses public properties (see NativeClassMap's audit), so
 *    gating by namespace prefix instead of enumerating every resource class individually is both
 *    simpler and robust to compat adding new resource classes later.
 *
 * Dynamic member access (`$obj->{$expr}()`, `$obj->{$expr}`) is never matched in any of the three
 * shapes: the method/property name must be a plain `Identifier`, which a dynamic access's `name`
 * never is -- same expected-miss contract as the phase-1 flag rules.
 */
final class FlagCompatManualMigrationRector extends AbstractRector
{
    use MarkerCommentTrait;

    /**
     * Kept in sync by hand with `bin/univapay-migrate`'s `MARKER_PHASE2_MANUAL` constant -- the
     * two live in different packages/processes and cannot literally share a PHP constant (same
     * reasoning as the phase-1 rules' MARKER constants).
     */
    public const MARKER = '@univapay-migrate:phase2-manual';

    public const VERIFY_SUFFIX = '(verify)';

    private const COMPAT_FILE_NEEDLE = 'Univapay\\Compat\\';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Flag every non-mechanical univapay/univapay-sdk-compat construct that needs a human '
                . 'decision when migrating to the native univapay/client-sdk, with an idempotent '
                . 'explanatory marker comment naming the category and native equivalent.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
if ($charge->status === ChargeStatus::SUCCESSFUL()) {
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// @univapay-migrate:phase2-manual ChargeStatus [typed-enum] -- TypedEnum singleton usage ... See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
if ($charge->status === ChargeStatus::SUCCESSFUL()) {
}
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
        return [
            Name::class,
            MethodCall::class,
            NullsafeMethodCall::class,
            PropertyFetch::class,
            NullsafePropertyFetch::class,
        ];
    }

    /**
     * @param Name|MethodCall|NullsafeMethodCall|PropertyFetch|NullsafePropertyFetch $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Name) {
            return $this->refactorName($node);
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            return $this->refactorMethodCall($node);
        }

        return $this->refactorPropertyFetch($node);
    }

    private function refactorName(Name $name): ?Node
    {
        $resolved = $this->getName($name);
        if ($resolved === null) {
            return null;
        }

        $category = $this->categoryForClass($resolved);
        if ($category === null) {
            return null;
        }

        return $this->insertMarkerComment(
            $name,
            $this->buildComment($this->shortName($resolved), $category, false)
        );
    }

    /**
     * @param MethodCall|NullsafeMethodCall $node
     */
    private function refactorMethodCall(Node $node): ?Node
    {
        if (!$node->name instanceof Identifier) {
            return null;
        }

        $methodName = $node->name->toString();
        $category = NativeClassMap::FLAG_METHODS[$methodName] ?? null;
        if ($category === null) {
            return null;
        }

        $verify = $this->isUnresolvedCompatReceiver($node->var);
        if ($verify === null) {
            // Resolved to a concrete, non-compat class -- skip entirely (e.g. an unrelated
            // object's own getNext()/getPrevious() method must never be flagged).
            return null;
        }

        return $this->insertMarkerComment($node, $this->buildComment($methodName, $category, $verify));
    }

    /**
     * @param PropertyFetch|NullsafePropertyFetch $node
     */
    private function refactorPropertyFetch(Node $node): ?Node
    {
        if (!$node->name instanceof Identifier) {
            return null;
        }

        $propertyName = $node->name->toString();
        $verify = $this->isUnresolvedCompatResourceReceiver($node->var);
        if ($verify === null) {
            return null;
        }

        return $this->insertMarkerComment(
            $node,
            $this->buildComment($propertyName, 'public-property', $verify)
        );
    }

    /**
     * @return bool|null true = unresolved (verify), false = resolved to a Univapay\Compat\* class
     *     (definite), null = resolved to some other concrete class or no receiver types at all
     *     with no file-level precision-gate match (skip entirely)
     */
    private function isUnresolvedCompatReceiver(Node\Expr $receiver): ?bool
    {
        $classNames = $this->getType($receiver)->getObjectClassNames();

        if ($classNames === []) {
            return $this->currentFileReferencesCompatNamespace() ? true : null;
        }

        foreach ($classNames as $className) {
            if (strncmp(ltrim($className, '\\'), self::COMPAT_FILE_NEEDLE, strlen(self::COMPAT_FILE_NEEDLE)) === 0) {
                return false;
            }
        }

        return null;
    }

    /**
     * Same shape as {@see isUnresolvedCompatReceiver()} but gated on
     * {@see NativeClassMap::PUBLIC_PROPERTY_PREFIX} specifically (a receiver could resolve to a
     * `Univapay\Compat\Errors\*`/`Univapay\Compat\Enums\*` class too, which is a real compat type
     * but not a "public-property resource" -- gating the property-fetch flag on the narrower
     * Resources\ prefix avoids double-flagging the same access under two categories).
     */
    private function isUnresolvedCompatResourceReceiver(Node\Expr $receiver): ?bool
    {
        $classNames = $this->getType($receiver)->getObjectClassNames();

        if ($classNames === []) {
            return $this->currentFileReferencesCompatNamespace() ? true : null;
        }

        foreach ($classNames as $className) {
            $normalized = ltrim($className, '\\');
            if (strncmp($normalized, NativeClassMap::PUBLIC_PROPERTY_PREFIX, strlen(NativeClassMap::PUBLIC_PROPERTY_PREFIX)) === 0) {
                return false;
            }
        }

        return null;
    }

    private function currentFileReferencesCompatNamespace(): bool
    {
        return strpos($this->getFile()->getOriginalFileContent(), self::COMPAT_FILE_NEEDLE) !== false;
    }

    /**
     * Exact-class match first, then longest-prefix-first namespace match.
     */
    private function categoryForClass(string $resolvedFqcn): ?string
    {
        if (isset(NativeClassMap::FLAG_EXACT_CLASSES[$resolvedFqcn])) {
            return NativeClassMap::FLAG_EXACT_CLASSES[$resolvedFqcn];
        }

        $bestPrefixLength = -1;
        $bestCategory = null;
        foreach (NativeClassMap::FLAG_NAMESPACE_PREFIXES as $prefix => $category) {
            if (strncmp($resolvedFqcn, $prefix, strlen($prefix)) === 0 && strlen($prefix) > $bestPrefixLength) {
                $bestPrefixLength = strlen($prefix);
                $bestCategory = $category;
            }
        }

        return $bestCategory;
    }

    private function buildComment(string $feature, string $category, bool $verify): string
    {
        $guidance = NativeClassMap::FLAG_GUIDANCE[$category] ?? 'Review this construct by hand.';

        if ($verify) {
            return sprintf(
                '// %s %s [%s] %s — could not statically confirm the receiver type; if this is '
                    . 'a compat object: %s See %s#phase-2-manual-review',
                self::MARKER,
                $feature,
                $category,
                self::VERIFY_SUFFIX,
                $guidance,
                GuideUrl::MIGRATION_GUIDE
            );
        }

        return sprintf(
            '// %s %s [%s] — %s See %s#phase-2-manual-review',
            self::MARKER,
            $feature,
            $category,
            $guidance,
            GuideUrl::MIGRATION_GUIDE
        );
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
