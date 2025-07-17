<?php

namespace Framework\Templating\Rendering;

use Framework\Templating\FilterManager;
use Framework\Templating\Parsing\{ParsedTemplate, TemplateParser, TemplatePathResolver};
use Framework\Templating\Tokens\{ControlToken, TemplateToken, VariableToken};

/**
 * TemplateRenderer - Konvertiert ParsedTemplate zu String
 */
class TemplateRenderer
{
    public function __construct(
        private readonly TemplateVariableResolver $variableResolver,
        private readonly FilterManager            $filterManager,
        private readonly TemplatePathResolver     $pathResolver
    )
    {
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

    private function renderToken(TemplateToken $token): string
    {
        return match ($token->getType()) {
            'text' => $token->getContent(),
            'variable' => $this->renderVariable($token instanceof VariableToken ? $token : throw new \LogicException('Expected VariableToken')),
            'control' => $this->renderControl($token instanceof ControlToken ? $token : throw new \LogicException('Expected ControlToken')),
            default => ''
        };
    }

    private function renderVariable(VariableToken $token): string
    {
        $value = $this->variableResolver->resolve($token->getVariable());

        // Apply filters
        foreach ($token->getFilters() as $filter) {
            $value = $this->filterManager->apply(
                $filter['name'],
                $value,
                $filter['parameters'] ?? []
            );
        }

        // Auto-escape
        if ($token->shouldEscape()) {
            $value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
            default => ''
        };
    }

    private function renderIf(ControlToken $token): string
    {
        $condition = $token->getMetadata()['condition'] ?? '';

        if ($this->variableResolver->evaluateCondition($condition)) {
            return $this->renderTokens($token->getChildren());
        } elseif ($token->hasElse()) {
            return $this->renderTokens($token->getElseChildren());
        }

        return '';
    }

    private function renderFor(ControlToken $token): string
    {
        $metadata = $token->getMetadata();
        $variable = $metadata['variable'] ?? '';
        $iterable = $metadata['iterable'] ?? '';

        // DEBUG: Log For-Loop-Verarbeitung
        error_log("=== FOR LOOP DEBUG ===");
        error_log("Variable: " . $variable);
        error_log("Iterable: " . $iterable);
        error_log("Metadata: " . json_encode($metadata));

        $items = $this->variableResolver->resolve($iterable);

        error_log("Items resolved: " . (is_array($items) ? count($items) : 'not array'));
        error_log("Items type: " . gettype($items));

        $output = '';

        if (is_iterable($items) && !empty($items)) {
            $itemIndex = 0;
            foreach ($items as $item) {
                error_log("Processing item {$itemIndex}: " . json_encode($item));

                $this->variableResolver->pushLoopContext($variable, $item);

                // DEBUG: Test variable resolution
                $testResolution = $this->variableResolver->resolve($variable);
                error_log("Variable '{$variable}' resolves to: " . json_encode($testResolution));

                $childOutput = $this->renderTokens($token->getChildren());
                error_log("Child output: " . $childOutput);

                $output .= $childOutput;
                $this->variableResolver->popLoopContext();

                $itemIndex++;
            }
        } elseif ($token->hasElse()) {
            error_log("Using else branch");
            $output = $this->renderTokens($token->getElseChildren());
        } else {
            error_log("No items and no else branch");
        }

        error_log("Final output: " . $output);
        error_log("=== END FOR LOOP DEBUG ===");

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