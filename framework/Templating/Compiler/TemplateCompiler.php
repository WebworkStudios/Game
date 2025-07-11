<?php
declare(strict_types=1);

namespace Framework\Templating\Compiler;

use Framework\Templating\Parser\TemplateParser;
use RuntimeException;

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

        // 1. Child-Blocks als Closures definieren BEVOR Parent-Include
        $code .= "\$_childBlocks = [];\n";

        // Define child blocks as closures with proper scope
        foreach ($this->blocks as $blockName => $block) {
            // FIX: Null Safety für block body
            $blockBody = $block['body'] ?? $block['content'] ?? [];

            if (!is_array($blockBody)) {
                $blockBody = [];
            }

            $blockCode = $this->compileNodes($blockBody);
            $code .= "\$_childBlocks['{$blockName}'] = function() use (\$renderer) {\n";
            $code .= "ob_start();\n";
            $code .= "try {\n";
            $code .= $blockCode;
            $code .= "return ob_get_clean();\n";
            $code .= "} catch (\\Throwable \$e) {\n";
            $code .= "ob_end_clean();\n";
            $code .= "throw \$e;\n";
            $code .= "}\n";
            $code .= "};\n";
        }

        // 2. Blocks in renderer setzen BEVOR Parent-Template geladen wird
        $code .= "if (!empty(\$_childBlocks)) {\n";
        $code .= "\$renderer->setBlocks(\$_childBlocks);\n";
        $code .= "}\n";

        // 3. Parent-Template includen - sieht jetzt die Child-Blocks
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
            'literal' => $this->compileLiteral($node),
            // 'function' => REMOVED - No longer supported!
            'extends' => $this->compileExtends($node),
            'block' => $this->compileBlock($node),
            'if' => $this->compileIf($node),
            'for' => $this->compileFor($node),
            'include' => $this->compileInclude($node),
            default => throw new RuntimeException("Unknown node type: {$node['type']}")
        };
    }

    private function compileText(array $node): string
    {
        $content = $node['content'];

        // Use strtr() instead of str_replace() - up to 4x faster for multiple replacements
        $escaped = strtr($content, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t'
        ]);

        return "echo \"{$escaped}\";\n";
    }

    private function compileVariable(array $node): string
    {
        $variableAccess = $this->compileVariableAccess($node);
        return "echo \$renderer->escape({$variableAccess});\n";
    }

    private function compileVariableAccess(array $node): string
    {
        // Handle literal values
        if ($node['type'] === 'literal') {
            $value = $node['value'];
            $code = "'" . str_replace("'", "\\'", $value) . "'";

            // Apply filters if present
            if (!empty($node['filters'])) {
                foreach ($node['filters'] as $filter) {
                    $filterName = $filter['name'];
                    $params = $filter['params'] ?? [];

                    if (empty($params)) {
                        $code = "\$renderer->applyFilter('{$filterName}', {$code})";
                    } else {
                        $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $code = "\$renderer->applyFilter('{$filterName}', {$code}, {$paramsJson})";
                    }
                }
            }

            return $code;
        }

        $name = $node['name'];
        $path = $node['path'] ?? [];
        $isDynamicKey = $node['is_dynamic_key'] ?? false;

        // If name contains quotes or template syntax, treat as literal
        if (str_contains($name, '"') || str_contains($name, "'") || str_contains($name, '{{')) {
            // This is a malformed variable, return as escaped string
            $cleaned = str_replace(['{{', '}}', '"', "'"], '', $name);
            return "'" . addslashes($cleaned) . "'";
        }

        // For simple variables without path
        if (empty($path)) {
            $code = "\$renderer->get('{$name}')";
        } else {
            // For nested accesses
            $code = "\$renderer->get('{$name}')";

            foreach ($path as $index => $property) {
                if ($isDynamicKey && $index === count($path) - 1) {
                    // Last element is dynamic (from variable)
                    $code = "is_array({$code}) ? ({$code})[\$renderer->get('{$property}')] ?? null : null";
                } elseif (is_numeric($property)) {
                    $code = "is_array({$code}) ? ({$code})[{$property}] ?? null : null";
                } else {
                    $code = "is_array({$code}) ? ({$code})['{$property}'] ?? null : null";
                }
            }
        }

        // Apply filters if present
        if (!empty($node['filters'])) {
            foreach ($node['filters'] as $filter) {
                $filterName = $filter['name'];
                $params = $filter['params'] ?? [];

                if (empty($params)) {
                    $code = "\$renderer->applyFilter('{$filterName}', {$code})";
                } else {
                    $cleanParams = array_map(function ($param) {
                        if (is_string($param)) {
                            return str_replace(['\\\'', '\\"', '\\\\'], ["'", '"', '\\'], trim($param, '\'"'));
                        }
                        return $param;
                    }, $params);

                    $paramsJson = json_encode($cleanParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $code = "\$renderer->applyFilter('{$filterName}', {$code}, {$paramsJson})";
                }
            }
        }

        return $code;
    }

    private function compileLiteral(array $node): string
    {
        $value = $node['value'];
        $filters = $node['filters'] ?? [];

        // Start with literal string
        $code = "'" . str_replace("'", "\\'", $value) . "'";

        // Apply filters if present
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $filterName = $filter['name'];
                $params = $filter['params'] ?? [];

                if (empty($params)) {
                    $code = "\$renderer->applyFilter('{$filterName}', {$code})";
                } else {
                    $cleanParams = array_map(function ($param) {
                        if (is_string($param)) {
                            return str_replace(['\\\'', '\\"', '\\\\'], ["'", '"', '\\'], trim($param, '\'"'));
                        }
                        return $param;
                    }, $params);

                    $paramsJson = json_encode($cleanParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $code = "\$renderer->applyFilter('{$filterName}', {$code}, {$paramsJson})";
                }
            }
        }

        return "echo \$renderer->escape({$code});\n";
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

        // FIX: Null Safety für block body/content
        $blockBody = $node['body'] ?? $node['content'] ?? [];

        if (!is_array($blockBody)) {
            $blockBody = [];
        }

        // In base templates, render block with potential child override
        $code = "// Block: {$blockName}\n";
        $code .= "if (\$renderer->hasBlock('{$blockName}')) {\n";
        $code .= "echo \$renderer->renderBlock('{$blockName}');\n";
        $code .= "} else {\n";
        $code .= "// Default block content\n";
        $code .= $this->compileNodes($blockBody);
        $code .= "}\n";

        return $code;
    }

    private function compileIf(array $node): string
    {
        $condition = $this->compileCondition($node['condition']);

        // FIX: Null Safety für if body/content
        $ifBody = $node['body'] ?? $node['content'] ?? [];
        if (!is_array($ifBody)) {
            $ifBody = [];
        }

        $body = $this->compileNodes($ifBody);

        $code = "if ({$condition}) {\n{$body}";

        if (!empty($node['else'])) {
            $elseBody = is_array($node['else']) ? $node['else'] : [];
            $elseCode = $this->compileNodes($elseBody);
            $code .= "} else {\n{$elseCode}";
        }

        $code .= "}\n";

        return $code;
    }

    private function compileCondition(array $condition): string
    {
        return match ($condition['type']) {
            'variable' => $this->compileVariableCondition($condition['expression']),
            'comparison' => $this->compileComparison($condition),
            default => throw new RuntimeException("Unknown condition type: {$condition['type']}")
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
            ? "'" . str_replace("'", "\\'", $condition['right']) . "'"
            : $condition['right'];

        return "({$left}) {$operator} {$right}";
    }

    private function compileFor(array $node): string
    {
        $array = $node['array'];
        $item = $node['item'];

        // FIX: Null Safety für for body/content
        $forBody = $node['body'] ?? $node['content'] ?? [];
        if (!is_array($forBody)) {
            $forBody = [];
        }

        $body = $this->compileNodes($forBody);

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