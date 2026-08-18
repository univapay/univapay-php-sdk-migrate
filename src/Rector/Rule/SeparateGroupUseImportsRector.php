<?php

declare(strict_types=1);

namespace Univapay\Migrate\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Splits bracket-style group use imports (`use Univapay\Enums\{ChargeStatus, RefundStatus};`)
 * into one plain `use` statement per class, mirroring the built-in `SeparateMultiUseImportsRector`
 * -- which is registered in the same set as a pre-pass before the rename -- but for a node shape
 * that rule does not handle.
 *
 * Verified empirically (not assumed) that this gap is real: `SeparateMultiUseImportsRector` only
 * inspects `Stmt\Use_` nodes (the comma-form `use A, B;`), never `Stmt\GroupUse` (the brace form).
 * Left unhandled, `RenameClassRector` does not rename anything inside a `GroupUse` node at all --
 * the group import line is left dangling, pointing at classes that no longer exist post-migration,
 * while every *usage* inside the file gets printed fully-qualified against the new namespace
 * instead of reusing a (non-existent) short import. This rule must run before the rename, exactly
 * like `SeparateMultiUseImportsRector`.
 *
 * Only `FileNode`/`Namespace_` are targeted (not `Class_`, unlike `SeparateMultiUseImportsRector`
 * which also handles multi-trait `use` inside a class body): `GroupUse` can only ever appear at
 * the top of a file or a namespace block, never inside a class.
 */
final class SeparateGroupUseImportsRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Split group use imports (use Foo\{A, B};) into standalone use statements.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Univapay\Enums\{ChargeStatus, RefundStatus};
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Univapay\Enums\ChargeStatus;
use Univapay\Enums\RefundStatus;
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
        return [FileNode::class, Namespace_::class];
    }

    /**
     * @param FileNode|Namespace_ $node
     * @return FileNode|Namespace_|null
     */
    public function refactor(Node $node)
    {
        if ($node instanceof FileNode && $node->isNamespaced()) {
            // handled in Namespace_, exactly like SeparateMultiUseImportsRector does.
            return null;
        }

        // Collect every GroupUse's key first, THEN splice in reverse (highest key first).
        //
        // Bug found empirically via the E2E golden corpus (a fixture with TWO group-use
        // statements in one file -- tests/Rector/Fixture/group_use.php.inc only ever exercised a
        // single occurrence, so this never surfaced in the earlier fixture suite): splicing
        // directly inside the `foreach` in ascending order is unsafe. `foreach` iterates a
        // snapshot taken at loop start, so `$key` for the SECOND (and later) GroupUse is its
        // position in the ORIGINAL array -- but each earlier `array_splice` call already changed
        // how many elements sit before that position in the LIVE `$node->stmts` array (one
        // GroupUse is replaced by N separate `Use_` statements, N almost always != 1). The next
        // splice then lands at a now-stale offset: it can insert into the middle of the
        // previous replacement's own output instead of at the second GroupUse's real (shifted)
        // position, leaving that second GroupUse completely unsplit further down the list.
        // Reverse order sidesteps this entirely: splicing at the highest key first never
        // changes the position of any node at a lower key, so every subsequent (lower-key)
        // splice offset is still valid.
        $groupUseKeys = [];
        foreach ($node->stmts as $key => $stmt) {
            if ($stmt instanceof GroupUse) {
                $groupUseKeys[] = $key;
            }
        }

        if ($groupUseKeys === []) {
            return null;
        }

        foreach (array_reverse($groupUseKeys) as $key) {
            /** @var GroupUse $stmt */
            $stmt = $node->stmts[$key];
            $splitUses = $this->splitGroupUse($stmt);
            array_splice($node->stmts, $key, 1, $splitUses);
        }

        return $node;
    }

    /**
     * @return Use_[]
     */
    private function splitGroupUse(GroupUse $groupUse): array
    {
        $uses = [];

        foreach ($groupUse->uses as $item) {
            // Mixed group uses (`use Foo\{function bar, Baz};`) carry a per-item type override;
            // TYPE_UNKNOWN means "inherit the group's own type" (the common case).
            $effectiveType = $item->type !== Use_::TYPE_UNKNOWN ? $item->type : $groupUse->type;

            $combinedName = Name::concat($groupUse->prefix, $item->name);
            $newItem = new UseItem($combinedName, $item->alias, Use_::TYPE_UNKNOWN);

            $uses[] = new Use_([$newItem], $effectiveType);
        }

        return $uses;
    }
}
