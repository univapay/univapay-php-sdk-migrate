# Notes

Operational facts about how this package's Rector configuration behaves, kept here because they
affect how the generated diffs look and are easy to "fix" back to something worse without this
context.

## Rector version pin

`composer.json` pins `rector/rector` to an exact version (no `^`/`~`): the E2E golden-fixture
tests assert byte-identical Rector output, and a version bump can change the printer's output even
with an unchanged ruleset. Bump the pin deliberately, together with regenerating
`tests/E2e/expected/`.

## `importNames`

`config/rector-template.php` sets `$rectorConfig->importNames(true)`. With `false`,
`RenameClassRector` only rewrites already-fully-qualified `Name` nodes: an already-imported
short-form reference (`use Univapay\UnivapayClient; ... new UnivapayClient()`) never gets its
`use` statement updated — instead every touched reference in the file is printed
fully-qualified, and the old `use Univapay\UnivapayClient;` line is left dangling against a class
that no longer exists once `composer remove univapay/php-sdk` runs. `importNames(true)` fixes
this, but Rector's `NameImportingPostRector` (which it triggers) also opportunistically shortens
other, unrelated, pre-existing fully-qualified references in any file it touches at all. Accepted
trade-off.

## Comment-deletion caveat

`NameImportingPostRector` rebuilds a file's entire `use`-import block from scratch, matching by
*position*, not original node identity. A pre-existing, human-authored comment that leads a `use`
statement participating in that rebuild is silently **deleted**, not relocated — this only happens
when that specific `use` statement's target is renamed (i.e. gets re-imported under
`Univapay\Compat\...`); imports that aren't touched keep their leading comments.
`MarkerCommentTrait::insertMarkerComment()` refuses to attach this package's own flag-rule marker
comments to `Use_`/`GroupUse` statements for the same reason — a flagged class still gets flagged
reliably at any real usage site (a type hint, `new`, `instanceof`, `catch`) instead. Consumer
impact: an explanatory comment directly above an old-SDK `use` statement that gets renamed will be
lost silently.

## GroupUse handling

Rector has no built-in rule for bracket-style `use Univapay\{A, B};` imports
(`SeparateMultiUseImportsRector` only handles the comma form, `use A, B;` — a different AST node,
`Stmt\Use_` vs `Stmt\GroupUse`). `src/Rector/Rule/SeparateGroupUseImportsRector.php` is a custom
pre-pass that splits each `GroupUse` into individual `Use_` statements, registered ahead of the
rename rule. When a file has more than one `GroupUse` statement, the splices must be applied in
**reverse key order** (highest array key first): splicing at a higher key never shifts the
position of anything at a lower key, so every subsequent splice offset stays valid. Splicing in
forward order corrupts every `GroupUse` after the first.
