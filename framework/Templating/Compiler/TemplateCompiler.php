<?php
declare(strict_types=1);

namespace Framework\Templating\Compiler;

use Framework\Templating\Parser\TemplateParser;

class TemplateCompiler
{
    private const string TEMPLATE_HEADER = <<<'PHP'
<?php
/* Compiled template: %s */
/* Generated at: %s */

if (!isset($renderer)) {
    throw new \RuntimeException('Template renderer not available');
}

PHP;

    private ?string $extendsTemplate = null;
    private array $blocks = [];

    public function __construct(
        private readonly TemplateParser $parser
    )
    {
    }

    public function compile(string $content, string $templatePath = ''): string
    {
        // Reset state for each compilation
        $this->extendsTemplate = null;
        $this->blocks = [];

        $ast = $this->parser->parse($content);

        // Extract extends and blocks first
        $this->extractExtendsAndBlocks($ast);

        $header = sprintf(
            self::TEMPLATE_HEADER,
            $templatePath,
            date('Y-m-d H:i:s')
        );

        // If this template extends another, compile differently
        if ($this->extendsTemplate !== null) {
            return $header . $this->compileExtendingTemplate($ast);
        }

        $body = $this->compileNodes($ast);
        return $header . $body;
    }

    private function extractExtendsAndBlocks(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node['type'] === 'extends') {
                $this->extendsTemplate = $node['template'];
            } elseif ($node['type'] === 'block') {
                $this->blocks[$node['name']] = $node;
            }
        }
    }

    private function compileExtendingTemplate(array $nodes): string
    {
        $code = '';

        // Define child blocks BEFORE including parent
        $code .= "\$_childBlocks = [];\n";

        // Define child blocks as closures
        foreach ($this->blocks as $blockName => $block) {
            $blockCode = $this->compileNodes($block['body']);
            $code .= "\$_childBlocks['{$blockName}'] = function() use (\$renderer) {\n";
            $code .= "ob_start();\n";
            $code .= $blockCode;
            $code .= "return ob_get_clean();\n";
            $code .= "};\n";
        }

        // Set blocks in renderer BEFORE including parent
        $code .= "\$renderer->setBlocks(\$_childBlocks);\n";

        // Include parent template (which will now see the blocks)
        $code .= "echo \$renderer->include('{$this->extendsTemplate}');\n";

        return $code;
    }

    private function compileNodes(array $nodes): string
    {
        $code = '';

        foreach ($nodes as $node) {
            $code .= $this->compileNode($node);
        }

        return $code;
    }

    private function compileNode(array $node): string
    {
        return match ($node['type']) {
            'text' => $this->compileText($node),
            'variable' => $this->compileVariable($node),
            'extends' => $this->compileExtends($node),
            'block' => $this->compileBlock($node),
            'if' => $this->compileIf($node),
            'for' => $this->compileFor($node),
            'include' => $this->compileInclude($node),
            default => throw new \RuntimeException("Unknown node type: {$node['type']}")
        };
    }

    private function compileText(array $node): string
    {
        $content = addcslashes($node['content'], "'\\");
        return "echo '{$content}';\n";
    }

    private function compileVariable(array $node): string
    {
        $variableAccess = $this->compileVariableAccess($node);
        return "echo \$renderer->escape({$variableAccess});\n";
    }

    private function compileVariableAccess(array $node): string
    {
        $name = $node['name'];
        $path = $node['path'] ?? [];

        // Start with base variable
        $code = "\$renderer->get('{$name}')";

        // Add path traversal
        foreach ($path as $property) {
            if (is_numeric($property)) {
                $code = "({$code})[{$property}] ?? null";
            } else {
                $code = "({$code})['{$property}'] ?? null";
            }
        }

        // Apply filters if present
        if (!empty($node['filters'])) {
            foreach ($node['filters'] as $filter) {
                $code = "\$renderer->applyFilter('{$filter}', {$code})";
            }
        }

        return $code;
    }

    private function compileExtends(array $node): string
    {
        // Extends is handled in extractExtendsAndBlocks, return empty string
        return '';
    }

    private function compileBlock(array $node): string
    {
        $blockName = $node['name'];

        // In extending templates, blocks are handled differently
        if ($this->extendsTemplate !== null) {
            return ''; // Blocks in extending templates are handled in compileExtendingTemplate
        }

        // In base templates, render block with potential override
        $code = "if (\$renderer->hasBlock('{$blockName}')) {\n";
        $code .= "echo \$renderer->renderBlock('{$blockName}');\n";
        $code .= "} else {\n";
        $code .= $this->compileNodes($node['body']);
        $code .= "}\n";

        return $code;
    }

    private function compileIf(array $node): string
    {
        $condition = $this->compileCondition($node['condition']);
        $body = $this->compileNodes($node['body']);

        $code = "if ({$condition}) {\n{$body}";

        if (!empty($node['else'])) {
            $elseBody = $this->compileNodes($node['else']);
            $code .= "} else {\n{$elseBody}";
        }

        $code .= "}\n";

        return $code;
    }

    private function compileCondition(array $condition): string
    {
        return match ($condition['type']) {
            'variable' => $this->compileVariableCondition($condition['expression']),
            'comparison' => $this->compileComparison($condition),
            default => throw new \RuntimeException("Unknown condition type: {$condition['type']}")
        };
    }

    private function compileVariableCondition(array $variable): string
    {
        $access = $this->compileVariableAccess($variable);
        return "!empty({$access})";
    }

    private function compileComparison(array $condition): string
    {
        $left = $this->compileVariableAccess($condition['left']);
        $operator = $condition['operator'];
        $right = is_string($condition['right'])
            ? "'{$condition['right']}'"
            : $condition['right'];

        return "({$left}) {$operator} {$right}";
    }

    private function compileFor(array $node): string
    {
        $array = $node['array'];
        $item = $node['item'];
        $body = $this->compileNodes($node['body']);

        // Handle dot notation in array access
        if (str_contains($array, '.')) {
            $parts = explode('.', $array);
            $arrayAccess = "\$renderer->get('{$parts[0]}')";

            foreach (array_slice($parts, 1) as $part) {
                $arrayAccess = "({$arrayAccess})['{$part}'] ?? null";
            }
        } else {
            $arrayAccess = "\$renderer->get('{$array}')";
        }

        return <<<PHP
\$_array = {$arrayAccess} ?? [];
if (is_array(\$_array) || \$_array instanceof \Traversable) {
    foreach (\$_array as \$_key => \$_item) {
        \$renderer->data['{$item}'] = \$_item;
        \$renderer->data['{$item}_key'] = \$_key;
{$body}    }
    unset(\$renderer->data['{$item}'], \$renderer->data['{$item}_key']);
}

PHP;
    }

    private function compileInclude(array $node): string
    {
        $template = $node['template'];

        if (isset($node['data_source']) && isset($node['variable'])) {
            // Include with data mapping
            $dataSource = $this->compileVariableAccess([
                'name' => explode('.', $node['data_source'])[0],
                'path' => array_slice(explode('.', $node['data_source']), 1)
            ]);

            $code = "echo \$renderer->includeWith('{$template}', '{$node['variable']}', {$dataSource});\n";
            return $code;
        }

        // Simple include
        return "echo \$renderer->include('{$template}');\n";
    }
}