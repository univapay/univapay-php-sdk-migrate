<?php

declare(strict_types=1);

namespace Univapay\Migrate\Rector\Rule\Concern;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Shared machinery for the migrate package's "flag" Rector rules ({@see
 * \Univapay\Migrate\Rector\Rule\FlagUnsupportedFeatureRector}, {@see
 * \Univapay\Migrate\Rector\Rule\FlagInternalApiUsageRector}): given any node found deep inside
 * a statement, find the enclosing statement and attach an idempotent `//` marker comment above
 * it.
 *
 * Two things this trait works around, both verified empirically against the pinned
 * `rector/rector` 2.6.2 dist package (see NOTES.md for the version pin rationale), because
 * neither is documented and both are easy to get subtly wrong:
 *
 * 1. Rector 2.x does not annotate nodes with a parent-node attribute by default -- unlike older
 *    Rector versions, `Rector\NodeTypeResolver\Node\AttributeKey` in 2.6.2 has no `PARENT_NODE`
 *    constant (confirmed by inspecting the actual dist package; there is no reliable built-in
 *    "find the parent of this node" helper). To make `$node->getAttribute('parent')` walkable,
 *    this trait lazily runs php-parser's own (non-Rector) `NodeConnectingVisitor` over the
 *    current file's statement tree once per file (cached by file path), which mutates the same
 *    live node objects Rector's own traversal is using.
 * 2. A marker comment must be attached to a `Stmt` node, not to an arbitrary expression node
 *    (e.g. the `MethodCall`/`Name` node that actually triggered the flag). Verified empirically:
 *    setting the `comments` attribute directly on a non-statement `Expr` node is silently
 *    dropped by Rector's format-preserving printer (no diff, no file write) -- nikic's pretty
 *    printer only consults `getComments()` when printing statement-level nodes. Walking up to
 *    the nearest `Stmt` before attaching the comment is therefore required, not just tidier.
 * 3. A marker is never attached to a `Use_`/`GroupUse` statement. Verified empirically (against
 *    the real package, not a guess): when `importNames(true)` is active (required -- see
 *    config/rector-template.php's doc block -- for RenameClassRector to produce clean renamed
 *    `use` statements instead of dangling old imports), Rector runs a `NameImportingPostRector`
 *    AFTER all main rules that rebuilds the file's entire `use`-import block from scratch. That
 *    rebuild does not preserve which original `Use_` node a leading comment belonged to -- it
 *    reattaches by *position*, not node identity -- so a comment attached to one `use` statement
 *    can resurface above a *different*, unrelated `use` statement that happens to land in the
 *    same slot after the rebuild (reproduced with two adjacent `use` statements where only one
 *    was being renamed: the comment on the untouched one relocated onto the renamed one, and in
 *    some orderings the two statements were also transposed). The failure mode when the flagged
 *    class is itself the one being renamed is worse -- the comment can be dropped entirely, not
 *    just relocated. A real usage of the flagged class elsewhere in the file (a type hint, `new`,
 *    `instanceof`, `catch`) is NOT touched by this postprocessor and flags reliably; only a bare,
 *    otherwise-unused `use Some\Flagged\Class;` import with no other reference in the file would
 *    silently go unflagged under this guard -- judged an acceptable trade-off over an unreliable
 *    or misattributed marker on every file that imports anything Univapay-related, which is most
 *    of them.
 */
trait MarkerCommentTrait
{
    private ?string $markerDecoratedFilePath = null;

    /**
     * Finds the statement enclosing $node and attaches $commentText as a leading `//` comment,
     * unless a comment with the exact same text is already present on that statement (idempotent
     * re-run: running the rule twice over already-migrated code produces no further diff).
     *
     * @return Node|null the original $node if a comment was newly attached (signals "changed" to
     *     Rector), or null if nothing changed (marker already present, or -- defensively, should
     *     not normally happen -- no enclosing statement could be found).
     */
    private function insertMarkerComment(Node $node, string $commentText): ?Node
    {
        $this->decorateFileWithParentLinksOnce();

        $stmt = $node;
        while ($stmt !== null && !($stmt instanceof Stmt)) {
            $stmt = $stmt->getAttribute('parent');
        }
        if ($stmt === null) {
            return null;
        }

        // See point 3 on this trait's class doc block: never attach to a use-import statement,
        // it is not reliable once the auto-import postprocessor rebuilds the use-block.
        if ($stmt instanceof Use_ || $stmt instanceof GroupUse) {
            return null;
        }

        foreach ($stmt->getComments() as $existingComment) {
            if ($existingComment->getText() === $commentText) {
                return null;
            }
        }

        $comments = $stmt->getComments();
        $comments[] = new Comment($commentText);
        $stmt->setAttribute(AttributeKey::COMMENTS, $comments);

        return $node;
    }

    private function decorateFileWithParentLinksOnce(): void
    {
        $filePath = $this->getFile()->getFilePath();
        if ($this->markerDecoratedFilePath === $filePath) {
            return;
        }

        $traverser = new NodeTraverser(new NodeConnectingVisitor());
        $traverser->traverse($this->getFile()->getNewStmts());
        $this->markerDecoratedFilePath = $filePath;
    }

    /**
     * The precision gate for `(verify)` flags on an unresolvable receiver type: only emit a
     * warning-level flag in files that reference the `Univapay\` namespace at all, so a generic
     * method name (e.g. `getTransfer`) called on some unrelated, unresolvable-type object in a
     * file that has nothing to do with Univapay never gets flagged.
     */
    private function currentFileReferencesUnivapayNamespace(): bool
    {
        return strpos($this->getFile()->getOriginalFileContent(), 'Univapay\\') !== false;
    }
}
