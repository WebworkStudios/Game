<?php
declare(strict_types=1);

namespace Framework\Templating\Rendering;

use Framework\Templating\FilterManager;
use Framework\Templating\Parsing\{ParsedTemplate, TemplateParser, TemplatePathResolver};
use Framework\Templating\Tokens\{ControlToken, TemplateToken, TokenType, VariableToken};

/**
 * TemplateRenderer - Konvertiert ParsedTemplate zu String
 *
 * UPDATED: Nutzt TokenType Enum für type-safe Token-Handling
 */
class TemplateRenderer
{
    public function __construct(
        private readonly TemplateVariableResolver $variableResolver,
        private readonly FilterManager            $filterManager,
        private readonly TemplatePathResolver     $pathResolver
    ) {
    }

    public function render(ParsedTemplate $template, array $data): string
    {
        $this->variableResolver->setData($data);

        // Handle inheritance
        if ($template->hasParent()) {
            return $this->renderWithInheritance($template, $data);
        }

        return $this->renderTokens($template->getTokens());
    }

    private function renderWithInheritance(ParsedTemplate $template, array $data): string
    {
        $parentTemplateName = $template->getParentTemplate();
        if (!$parentTemplateName) {
            return $this->renderTokens($template->getTokens());
        }

        // Load parent template
        $parentPath = $this->pathResolver->resolve($parentTemplateName);
        $parentContent = file_get_contents($parentPath);

        $tokenizer = new \Framework\Templating\Parsing\TemplateTokenizer();
        $controlFlowParser = new \Framework\Templating\Parsing\ControlFlowParser();
        $parser = new TemplateParser($tokenizer, $controlFlowParser, $this->pathResolver);

        $parentTemplate = $parser->parse($parentContent, $parentPath);

        // Merge child blocks into parent
        $mergedBlocks = array_merge($parentTemplate->getBlocks(), $template->getBlocks());

        // Replace block tokens in parent with child blocks
        $parentTokens = $this->replaceBlockTokens($parentTemplate->getTokens(), $mergedBlocks);

        return $this->renderTokens($parentTokens);
    }

    private function renderTokens(array $tokens): string
    {
        $output = '';

        foreach ($tokens as $token) {
            $output .= $this->renderToken($token);
        }

        return $output;
    }

    /**
     * UPDATED: Nutzt TokenType Enum für type-safe Token-Rendering
     */
    private function renderToken(TemplateToken $token): string
    {
        return match ($token->getTokenType()) {
            TokenType::TEXT => $token->getContent(),
            TokenType::VARIABLE => $this->renderVariable($token instanceof VariableToken ? $token : throw new \LogicException('Expected VariableToken')),
            TokenType::CONTROL => $this->renderControl($token instanceof ControlToken ? $token : throw new \LogicException('Expected ControlToken')),
        };
    }

    private function renderVariable(VariableToken $token): string
    {
        $value = $this->variableResolver->resolve($token->getVariable());

        // Apply filters
        if (!empty($token->getFilters())) {
            $value = $this->filterManager->applyPipeline($value, $token->getFilters());
        }

        // Auto-escape unless raw filter was applied
        if ($token->shouldEscape()) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }

        return (string)$value;
    }

    private function renderControl(ControlToken $token): string
    {
        return match ($token->getCommand()) {
            'if' => $this->renderIf($token),
            'for' => $this->renderFor($token),
            'block' => $this->renderBlock($token),
            'include' => $this->renderInclude($token),
            'extends' => '', // Handled by inheritance system
            default => ''
        };
    }

    private function renderIf(ControlToken $token): string
    {
        $condition = $token->getMetadata()['condition'] ?? '';
        $result = $this->variableResolver->evaluateCondition($condition);

        if ($result) {
            return $this->renderTokens($token->getChildren());
        } elseif ($token->hasElse()) {
            return $this->renderTokens($token->getElseChildren());
        }

        return '';
    }

    private function renderFor(ControlToken $token): string
    {
        $variable = $token->getMetadata()['variable'] ?? '';
        $iterable = $token->getMetadata()['iterable'] ?? '';

        $items = $this->variableResolver->resolve($iterable);

        if (!is_iterable($items) || empty($items)) {
            return $token->hasElse() ? $this->renderTokens($token->getElseChildren()) : '';
        }

        // Check if variable contains comma (Key-Value syntax)
        if (str_contains($variable, ',')) {
            return $this->renderKeyValueForLoop($token, $variable, $items);
        } else {
            return $this->renderValueOnlyForLoop($token, $variable, $items);
        }
    }

    /**
     * Rendert Key-Value For-Loops
     * Für: {% for position, players in players_by_position %}
     */
    private function renderKeyValueForLoop(ControlToken $token, string $variable, iterable $items): string
    {
        // Parse Key-Value variable names
        $variableParts = array_map('trim', explode(',', $variable));
        if (count($variableParts) !== 2) {
            throw new \RuntimeException("Invalid key-value for-loop syntax: '{$variable}'");
        }

        [$keyVariable, $valueVariable] = $variableParts;
        $output = '';

        foreach ($items as $key => $value) {
            // Push BOTH key and value to loop context
            $this->variableResolver->pushLoopContext($keyVariable, $key);
            $this->variableResolver->pushLoopContext($valueVariable, $value);

            // Render child tokens
            $output .= $this->renderTokens($token->getChildren());

            // Pop BOTH variables (in reverse order)
            $this->variableResolver->popLoopContext(); // Pop value
            $this->variableResolver->popLoopContext(); // Pop key
        }

        return $output;
    }

    /**
     * Rendert Value-only For-Loops
     * Für: {% for player in players %}
     */
    private function renderValueOnlyForLoop(ControlToken $token, string $variable, iterable $items): string
    {
        $output = '';

        foreach ($items as $item) {
            $this->variableResolver->pushLoopContext($variable, $item);
            $output .= $this->renderTokens($token->getChildren());
            $this->variableResolver->popLoopContext();
        }

        return $output;
    }

    private function renderBlock(ControlToken $token): string
    {
        return $this->renderTokens($token->getChildren());
    }

    private function renderInclude(ControlToken $token): string
    {
        $templateName = $token->getMetadata()['template'] ?? '';

        if (!$templateName) {
            return '';
        }

        try {
            // Create new renderer for included template
            $templatePath = $this->pathResolver->resolve($templateName);
            $content = file_get_contents($templatePath);

            $tokenizer = new \Framework\Templating\Parsing\TemplateTokenizer();
            $controlFlowParser = new \Framework\Templating\Parsing\ControlFlowParser();
            $parser = new TemplateParser($tokenizer, $controlFlowParser, $this->pathResolver);

            $parsedTemplate = $parser->parse($content, $templatePath);

            return $this->render($parsedTemplate, $this->variableResolver->getData());
        } catch (\Throwable $e) {
            return "<!-- Include error: {$templateName} - {$e->getMessage()} -->";
        }
    }

    private function replaceBlockTokens(array $tokens, array $blocks): array
    {
        $result = [];

        foreach ($tokens as $token) {
            if ($token instanceof ControlToken && $token->getCommand() === 'block') {
                $blockName = $token->getMetadata()['block_name'] ?? '';
                if ($blockName && isset($blocks[$blockName])) {
                    $result = array_merge($result, $blocks[$blockName]);
                } else {
                    $result[] = $token;
                }
            } else {
                $result[] = $token;
            }
        }

        return $result;
    }
}