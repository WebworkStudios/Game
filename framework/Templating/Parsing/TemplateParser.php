<?php
namespace Framework\Templating\Parsing;

use Framework\Templating\Tokens\{TemplateToken, TokenFactory, ControlToken, TextToken, VariableToken};
/**
 * TemplateParser - Koordiniert das Parsing und erstellt ParsedTemplate
 */
class TemplateParser
{
    public function __construct(
        private readonly TemplateTokenizer $tokenizer,
        private readonly ControlFlowParser $controlFlowParser,
        private readonly TemplatePathResolver $pathResolver
    ) {}

    public function parse(string $content, string $templatePath): ParsedTemplate
    {
        // 1. Tokenize
        $tokens = $this->tokenizer->tokenize($content);

        // 2. Parse control flow
        $parsedTokens = $this->controlFlowParser->parseControlFlow($tokens);

        // 3. Extract inheritance info
        $inheritanceInfo = $this->extractInheritanceInfo($parsedTokens);

        // 4. Build dependencies
        $dependencies = $this->buildDependencies($parsedTokens, $templatePath);

        return new ParsedTemplate(
            tokens: $parsedTokens,
            templatePath: $templatePath,
            parentTemplate: $inheritanceInfo['parent'],
            blocks: $inheritanceInfo['blocks'],
            dependencies: $dependencies
        );
    }

    private function extractInheritanceInfo(array $tokens): array
    {
        $parent = null;
        $blocks = [];

        foreach ($tokens as $token) {
            if ($token instanceof ControlToken) {
                switch ($token->getCommand()) {
                    case 'extends':
                        $parent = $token->getMetadata()['parent_template'] ?? null;
                        break;
                    case 'block':
                        $blockName = $token->getMetadata()['block_name'] ?? '';
                        if ($blockName) {
                            $blocks[$blockName] = $token->getChildren();
                        }
                        break;
                }
            }
        }

        return ['parent' => $parent, 'blocks' => $blocks];
    }

    private function buildDependencies(array $tokens, string $templatePath): array
    {
        $dependencies = [$templatePath];

        foreach ($tokens as $token) {
            if ($token instanceof ControlToken) {
                switch ($token->getCommand()) {
                    case 'extends':
                        $parentTemplate = $token->getMetadata()['parent_template'] ?? null;
                        if ($parentTemplate) {
                            $dependencies[] = $this->pathResolver->resolve($parentTemplate);
                        }
                        break;
                    case 'include':
                        $includeTemplate = $token->getMetadata()['template'] ?? null;
                        if ($includeTemplate) {
                            $dependencies[] = $this->pathResolver->resolve($includeTemplate);
                        }
                        break;
                }
            }
        }

        return array_unique($dependencies);
    }
}