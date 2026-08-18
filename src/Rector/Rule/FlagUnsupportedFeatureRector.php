<?php

declare(strict_types=1);

namespace Univapay\Migrate\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Univapay\Migrate\GuideUrl;
use Univapay\Migrate\Rector\Rule\Concern\MarkerCommentTrait;

/**
 * Flags references to classes and methods that `univapay/univapay-sdk-compat` does not
 * implement (see {@see \Univapay\Migrate\ClassMap::UNSUPPORTED_CLASSES} and {@see
 * \Univapay\Migrate\ClassMap::UNSUPPORTED_METHODS}) with an idempotent `//
 * @univapay-migrate:unsupported` marker comment above the enclosing statement. Never rewrites
 * code -- the compat package's rename target for these classes still exists (they are stub
 * implementations that throw `UnivapayUnsupportedFeatureError` at runtime), so migrated code
 * always compiles; this rule only adds a comment explaining why the runtime behavior changed.
 *
 * Two node shapes are targeted:
 * - `Name` nodes (`use`, `new`, `instanceof`, type hints, catch types, `::class`) whose resolved
 *   FQCN (old or already-renamed compat form) is in the configured `classes` list -- always a
 *   definite flag, since merely referencing the class name is unambiguous regardless of receiver
 *   typing.
 * - `MethodCall`/`NullsafeMethodCall` nodes whose method name is in the configured `methods`
 *   list, gated by the receiver's resolved PHPStan type:
 *     - resolves to a concrete class in the `Univapay\` (or `Univapay\Compat\`) namespace ->
 *       definite flag, regardless of which specific Univapay class it is (the configured method
 *       names are not otherwise reused by any *supported* method in the old SDK's surface, so
 *       this is not meaningfully broader than enumerating the exact receiver classes from the
 *       plan -- UnivapayClient/Store/Charge/Subscription/Transfer/ScheduledPayment -- and is
 *       robust to that list ever being incomplete).
 *     - resolves to a concrete class OUTSIDE the `Univapay\` namespace -> skipped entirely (a
 *       same-named method on an unrelated type, e.g. an ORM entity's own `getTransfer()`, must
 *       never be flagged).
 *     - unresolvable (e.g. `mixed`, untyped parameter) -> flagged with a `(verify)` suffix, but
 *       ONLY in files that reference `Univapay\` somewhere at all (the precision gate in {@see
 *       MarkerCommentTrait::currentFileReferencesUnivapayNamespace()}), so a generic method name
 *       called in a file with nothing to do with Univapay is never flagged.
 *
 * Dynamic method calls (`$obj->{$expr}()`, `$obj->{'listTransfers'}()`) are never matched: the
 * method name must be a plain `Identifier`, which a dynamic call's `name` never is.
 */
final class FlagUnsupportedFeatureRector extends AbstractRector implements ConfigurableRectorInterface
{
    use MarkerCommentTrait;

    /**
     * Kept in sync by hand with `bin/univapay-migrate`'s `MARKER_UNSUPPORTED` constant -- the two
     * live in different packages/processes (this is a Rector rule, that is a dependency-free CLI
     * script) and cannot literally share a PHP constant, but the post-scan's marker detection is
     * a plain substring match against this exact text.
     */
    public const MARKER = '@univapay-migrate:unsupported';

    /**
     * Kept in sync by hand with `bin/univapay-migrate`'s `VERIFY_SUFFIX` constant, for the same
     * reason as {@see MARKER}.
     */
    public const VERIFY_SUFFIX = '(verify)';

    /**
     * @var string[]
     */
    private array $classes = [];

    /**
     * @var string[]
     */
    private array $methods = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Flag references to univapay/php-sdk classes/methods that univapay/univapay-sdk-compat '
                . 'does not implement, with an idempotent explanatory marker comment.',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
$transfer->listLedgers();
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// @univapay-migrate:unsupported listLedgers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
$transfer->listLedgers();
CODE_SAMPLE,
                    [
                        'classes' => ['Univapay\Resources\Transfer'],
                        'methods' => ['listLedgers'],
                    ]
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class, Name::class];
    }

    /**
     * @param array{classes?: string[], methods?: string[]} $configuration
     */
    public function configure(array $configuration): void
    {
        $this->classes = $configuration['classes'] ?? [];
        $this->methods = $configuration['methods'] ?? [];
    }

    /**
     * @param MethodCall|NullsafeMethodCall|Name $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Name) {
            return $this->refactorName($node);
        }

        return $this->refactorMethodCall($node);
    }

    private function refactorName(Name $name): ?Node
    {
        $resolved = $this->getName($name);
        if ($resolved === null || !in_array($resolved, $this->classes, true)) {
            return null;
        }

        return $this->insertMarkerComment($name, $this->buildComment($this->shortName($resolved), false));
    }

    /**
     * @param MethodCall|NullsafeMethodCall $node
     */
    private function refactorMethodCall(Node $node): ?Node
    {
        // Dynamic method names ($obj->{$expr}()) are never a plain Identifier -- deliberately
        // not matched (expected-miss case: `->{'listTransfers'}()` must never be flagged).
        if (!$node->name instanceof Identifier) {
            return null;
        }

        $methodName = $node->name->toString();
        if (!in_array($methodName, $this->methods, true)) {
            return null;
        }

        $classNames = $this->getType($node->var)->getObjectClassNames();

        if ($classNames === []) {
            // Unresolvable/mixed receiver type: only a warning, and only in files that reference
            // Univapay\ at all (precision gate).
            if (!$this->currentFileReferencesUnivapayNamespace()) {
                return null;
            }

            return $this->insertMarkerComment($node, $this->buildComment($methodName, true));
        }

        $receiverIsUnivapay = false;
        foreach ($classNames as $className) {
            if (strncmp(ltrim($className, '\\'), 'Univapay\\', 9) === 0) {
                $receiverIsUnivapay = true;
                break;
            }
        }

        if (!$receiverIsUnivapay) {
            // Known non-Univapay type (e.g. an unrelated class with a same-named method) ->
            // skip entirely, never flag.
            return null;
        }

        return $this->insertMarkerComment($node, $this->buildComment($methodName, false));
    }

    private function buildComment(string $feature, bool $verify): string
    {
        if ($verify) {
            return sprintf(
                '// %s %s %s — could not statically confirm the receiver type; if this is a '
                    . 'Univapay object, it throws UnivapayUnsupportedFeatureError at runtime. '
                    . 'See %s#unsupported-features',
                self::MARKER,
                $feature,
                self::VERIFY_SUFFIX,
                GuideUrl::MIGRATION_GUIDE
            );
        }

        return sprintf(
            '// %s %s — this throws UnivapayUnsupportedFeatureError at runtime. See %s#unsupported-features',
            self::MARKER,
            $feature,
            GuideUrl::MIGRATION_GUIDE
        );
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
