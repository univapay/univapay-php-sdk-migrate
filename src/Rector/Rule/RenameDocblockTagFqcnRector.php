<?php

declare(strict_types=1);

namespace Univapay\Migrate\Rector\Rule;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Renames FQCNs inside `@expectedException`, `@covers`, and `@uses` PHPDoc tags.
 *
 * These are generic, unstructured PHPUnit annotations -- Rector's built-in docblock-aware
 * renaming (used by `RenameClassRector` for `@var`/`@param`/`@return` etc., via its structured
 * PHPStan-docblock-parser integration) only understands actual PHP *type* tags, not arbitrary
 * text tags like these. `@expectedException` in particular is not just documentation: PHPUnit 8
 * still reads and *executes* it (asserts the given exception class is thrown), so a stale old-SDK
 * FQCN there is not a lint nit, it silently changes what the test actually asserts once the old
 * SDK's classes stop existing to throw.
 *
 * Implementation is deliberately a plain text substitution on the doc comment string (there is no
 * structured AST for these tags to safely refactor with node identity) using the same `classes`
 * map passed to `RenameClassRector`, restricted to a leading class-name-shaped token per matched
 * tag so `@covers ClassName::methodName` only rewrites the `ClassName` part.
 */
final class RenameDocblockTagFqcnRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var string[]
     */
    private const TAGS = ['@expectedException', '@covers', '@uses'];

    /**
     * @var array<string, string>
     */
    private array $classMap = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename FQCNs inside @expectedException/@covers/@uses PHPDoc tags.',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
/**
 * @expectedException \Univapay\Errors\UnivapayServerError
 */
public function testSomething(): void
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
/**
 * @expectedException \Univapay\Compat\Errors\UnivapayServerError
 */
public function testSomething(): void
{
}
CODE_SAMPLE,
                    ['Univapay\Errors\UnivapayServerError' => 'Univapay\Compat\Errors\UnivapayServerError']
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Class_::class];
    }

    /**
     * @param array<string, string> $configuration
     */
    public function configure(array $configuration): void
    {
        $this->classMap = $configuration;
    }

    /**
     * @param ClassMethod|Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return null;
        }

        $originalText = $docComment->getText();
        $newText = $this->renameTagFqcns($originalText);

        if ($newText === $originalText) {
            return null;
        }

        $node->setDocComment(new Doc($newText));

        return $node;
    }

    private function renameTagFqcns(string $docText): string
    {
        foreach (self::TAGS as $tag) {
            $replaced = preg_replace_callback(
                '/(' . preg_quote($tag, '/') . '\s+\\\\?)([A-Za-z_][A-Za-z0-9_\\\\]*)/',
                function (array $matches): string {
                    $fqcn = ltrim($matches[2], '\\');
                    if (!isset($this->classMap[$fqcn])) {
                        return $matches[0];
                    }

                    return $matches[1] . $this->classMap[$fqcn];
                },
                $docText
            );
            $docText = $replaced ?? $docText;
        }

        return $docText;
    }
}
