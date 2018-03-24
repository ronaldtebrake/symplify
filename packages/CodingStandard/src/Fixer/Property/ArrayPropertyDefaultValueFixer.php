<?php declare(strict_types=1);

namespace Symplify\CodingStandard\Fixer\Property;

use PhpCsFixer\Fixer\DefinedFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Symplify\CodingStandard\Fixer\TokenBuilder;
use Symplify\TokenRunner\Analyzer\FixerAnalyzer\DocBlockFinder;
use Symplify\TokenRunner\Wrapper\FixerWrapper\ClassWrapperFactory;
use Symplify\TokenRunner\Wrapper\FixerWrapper\DocBlockWrapperFactory;

final class ArrayPropertyDefaultValueFixer implements DefinedFixerInterface
{
    /**
     * @var ClassWrapperFactory
     */
    private $classWrapperFactory;

    /**
     * @var DocBlockWrapperFactory
     */
    private $docBlockWrapperFactory;

    /**
     * @var DocBlockFinder
     */
    private $docBlockFinder;

    /**
     * @var TokenBuilder
     */
    private $tokenBuilder;

    public function __construct(
        ClassWrapperFactory $classWrapperFactory,
        DocBlockWrapperFactory $docBlockWrapperFactory,
        DocBlockFinder $docBlockFinder,
        TokenBuilder $tokenBuilder
    ) {
        $this->classWrapperFactory = $classWrapperFactory;
        $this->docBlockWrapperFactory = $docBlockWrapperFactory;
        $this->docBlockFinder = $docBlockFinder;
        $this->tokenBuilder = $tokenBuilder;
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Array property should have default value, to prevent undefined array issues.',
            [
                new CodeSample(
                    '<?php
/**
 * @var string[]
 */
public $property;'
                ),
            ]
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([T_CLASS, T_TRAIT])
            && $tokens->isAllTokenKindsFound([T_DOC_COMMENT, T_VARIABLE])
            && $tokens->isAnyTokenKindsFound([T_PUBLIC, T_PROTECTED, T_PRIVATE]);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = count($tokens) - 1; $index > 1; --$index) {
            $token = $tokens[$index];
            if (! $token->isClassy()) {
                continue;
            }

            $classTokensAnalyzer = $this->classWrapperFactory->createFromTokensArrayStartPosition($tokens, $index);

            $this->fixProperties($tokens, $classTokensAnalyzer->getProperties());
        }
    }

    public function getName(): string
    {
        return self::class;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function supports(SplFileInfo $file): bool
    {
        return true;
    }

    /**
     * @param mixed[]|Token[] $properties
     */
    private function fixProperties(Tokens $tokens, array $properties): void
    {
        $properties = array_reverse($properties, true);

        foreach ($properties as $index => ['token' => $propertyToken]) {
            $docBlockTokenPosition = $this->docBlockFinder->findPreviousPosition($tokens, $index);
            if ($docBlockTokenPosition === null) {
                continue;
            }

            $docBlockWrapper = $this->docBlockWrapperFactory->create(
                $tokens,
                $docBlockTokenPosition,
                $tokens[$docBlockTokenPosition]->getContent()
            );

            if (! $docBlockWrapper->isArrayProperty()) {
                continue;
            }

            $equalTokenPosition = $tokens->getNextTokenOfKind($index, ['=']);
            $semicolonTokenPosition = (int) $tokens->getNextTokenOfKind($index, [';']);

            if ($this->isDefaultDefinitionSet($equalTokenPosition, $semicolonTokenPosition)) {
                continue;
            }

            $tokens->insertAt($semicolonTokenPosition, $this->tokenBuilder->createDefaultArrayTokens());
        }
    }

    private function isDefaultDefinitionSet(?int $equalTokenPosition, int $semicolonTokenPosition): bool
    {
        return is_numeric($equalTokenPosition) && $equalTokenPosition < $semicolonTokenPosition;
    }
}
