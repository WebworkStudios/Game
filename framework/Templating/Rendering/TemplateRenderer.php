<?php

namespace Framework\Templating\Rendering;

use Framework\Templating\FilterManager;
use Framework\Templating\Parsing\{ParsedTemplate, TemplateParser, TemplatePathResolver};
use Framework\Templating\Tokens\{ControlToken, TemplateToken, VariableToken};

/**
 * TemplateRenderer - Konvertiert ParsedTemplate zu String
 *
 * KORRIGIERT: Key-Value For-Loop Support hinzugefügt
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

    /**
     * Rendert For-Loops mit Key-Value Support
     *
     * KORRIGIERT: Unterstützt sowohl:
     * - {% for item in items %} (Value-only)
     * - {% for key, value in items %} (Key-Value)
     */
    private function renderFor(ControlToken $token): string
    {
        $metadata = $token->getMetadata();
        $variable = $metadata['variable'] ?? '';
        $iterable = $metadata['iterable'] ?? '';

        // DEBUG: Enhanced logging
        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("=== FOR LOOP DEBUG (Enhanced) ===");
            error_log("Variable: " . $variable);
            error_log("Iterable: " . $iterable);
            error_log("Metadata: " . json_encode($metadata));
        }

        $items = $this->variableResolver->resolve($iterable);

        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Items resolved: " . (is_array($items) ? count($items) : 'not array'));
            error_log("Items type: " . gettype($items));
            if (is_array($items)) {
                error_log("Items keys: " . json_encode(array_keys($items)));
                error_log("First item: " . json_encode(reset($items)));
            }
        }

        $output = '';

        if (is_iterable($items) && !empty($items)) {
            // KORRIGIERT: Check if variable contains comma (Key-Value syntax)
            if (str_contains($variable, ',')) {
                // Key-Value For-Loop: {% for key, value in items %}
                $output = $this->renderKeyValueForLoop($token, $variable, $items);
            } else {
                // Value-only For-Loop: {% for item in items %}
                $output = $this->renderValueOnlyForLoop($token, $variable, $items);
            }
        } elseif ($token->hasElse()) {
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("Using else branch");
            }
            $output = $this->renderTokens($token->getElseChildren());
        } else {
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("No items and no else branch");
            }
        }

        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Final output length: " . strlen($output));
            error_log("=== END FOR LOOP DEBUG ===");
        }

        return $output;
    }

    /**
     * NEUE METHODE: Rendert Key-Value For-Loops
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
        $itemIndex = 0;

        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Key-Value For-Loop detected:");
            error_log("Key variable: '{$keyVariable}'");
            error_log("Value variable: '{$valueVariable}'");
        }

        foreach ($items as $key => $value) {
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("Processing item {$itemIndex}: key='{$key}', value type=" . gettype($value));
                if (is_array($value)) {
                    error_log("Value is array with " . count($value) . " items");
                }
            }

            // KORRIGIERT: Push BOTH key and value to loop context
            $this->variableResolver->pushLoopContext($keyVariable, $key);
            $this->variableResolver->pushLoopContext($valueVariable, $value);

            // DEBUG: Verify variable resolution
            if ($_ENV['APP_DEBUG'] ?? false) {
                $keyResolution = $this->variableResolver->resolve($keyVariable);
                $valueResolution = $this->variableResolver->resolve($valueVariable);
                error_log("Key '{$keyVariable}' resolves to: " . json_encode($keyResolution));
                error_log("Value '{$valueVariable}' resolves to: " . (is_array($valueResolution) ? "array(" . count($valueResolution) . ")" : json_encode($valueResolution)));
            }

            // Render child tokens
            $childOutput = $this->renderTokens($token->getChildren());

            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("Child output length: " . strlen($childOutput));
            }

            $output .= $childOutput;

            // KORRIGIERT: Pop BOTH variables (in reverse order)
            $this->variableResolver->popLoopContext(); // Pop value
            $this->variableResolver->popLoopContext(); // Pop key

            $itemIndex++;
        }

        return $output;
    }

    /**
     * NEUE METHODE: Rendert Value-only For-Loops
     * Für: {% for player in players %}
     */
    private function renderValueOnlyForLoop(ControlToken $token, string $variable, iterable $items): string
    {
        $output = '';
        $itemIndex = 0;

        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Value-only For-Loop detected:");
            error_log("Variable: '{$variable}'");
        }

        foreach ($items as $item) {
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("Processing item {$itemIndex}: " . json_encode($item));
            }

            $this->variableResolver->pushLoopContext($variable, $item);

            // DEBUG: Test variable resolution
            if ($_ENV['APP_DEBUG'] ?? false) {
                $testResolution = $this->variableResolver->resolve($variable);
                error_log("Variable '{$variable}' resolves to: " . json_encode($testResolution));
            }

            $childOutput = $this->renderTokens($token->getChildren());

            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("Child output: " . $childOutput);
            }

            $output .= $childOutput;
            $this->variableResolver->popLoopContext();

            $itemIndex++;
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