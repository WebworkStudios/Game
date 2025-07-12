<?php
declare(strict_types=1);

namespace Framework\Templating\Compiler;

use Framework\Templating\Parser\TemplateInheritanceParser;
use Framework\Templating\Parser\TemplateParser;
use RuntimeException;

/**
 * Template Compiler - Clean implementation without templatePath dependency
 *
 * The templatePath is provided at compile-time, not construction-time.
 * This allows proper dependency injection while maintaining inheritance support.
 */
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

    public function __construct(
        private readonly TemplateParser $parser
    ) {
    }

    /**
     * Compile template with full inheritance support
     */
    public function compile(string $content, string $templatePath = ''): string
    {
        $header = sprintf(
            self::TEMPLATE_HEADER,
            $templatePath,
            date('Y-m-d H:i:s')
        );

        // Use inheritance parser if template contains extends
        if ($this->hasInheritance($content)) {
            // Extract base path from template path for inheritance resolution
            $basePath = $templatePath ? dirname($templatePath) : '';
            $inheritanceParser = new TemplateInheritanceParser($this->parser, $basePath);
            $ast = $inheritanceParser->parseWithInheritance($content, $templatePath);
        } else {
            // Simple template without inheritance
            $ast = $this->parser->parse($content);
        }

        // Clean AST from any remaining inheritance artifacts
        $cleanAst = $this->cleanInheritanceArtifacts($ast);

        $body = $this->compileNodes($cleanAst);
        return $header . $body;
    }

    /**
     * Check if template uses inheritance
     */
    private function hasInheritance(string $content): bool
    {
        return str_contains($content, '{% extends') || str_contains($content, '{%extends');
    }

    /**
     * Remove any remaining inheritance artifacts from AST
     */
    private function cleanInheritanceArtifacts(array $nodes): array
    {
        $cleaned = [];

        foreach ($nodes as $node) {
            // Skip inheritance-related nodes
            if (in_array($node['type'] ?? '', ['extends', 'block', 'endblock', 'closing_tag'])) {
                continue;
            }

            // Recursively clean nested structures
            if (isset($node['body']) && is_array($node['body'])) {
                $node['body'] = $this->cleanInheritanceArtifacts($node['body']);
            }
            if (isset($node['else']) && is_array($node['else'])) {
                $node['else'] = $this->cleanInheritanceArtifacts($node['else']);
            }
            if (isset($node['content']) && is_array($node['content'])) {
                $node['content'] = $this->cleanInheritanceArtifacts($node['content']);
            }

            $cleaned[] = $node;
        }

        return $cleaned;
    }

    /**
     * Compile AST nodes to PHP code
     */
    private function compileNodes(array $nodes): string
    {
        $code = '';

        foreach ($nodes as $node) {
            $code .= $this->compileNode($node);
        }

        return $code;
    }

    /**
     * Compile single AST node
     */
    private function compileNode(array $node): string
    {
        // Defensive programming: ensure type exists
        if (!isset($node['type'])) {
            return ''; // Skip malformed nodes
        }

        return match ($node['type']) {
            'text' => $this->compileText($node),
            'variable' => $this->compileVariable($node),
            'literal' => $this->compileLiteral($node),
            'if' => $this->compileIf($node),
            'for' => $this->compileFor($node),
            'include' => $this->compileInclude($node),
            // Inheritance artifacts should be cleaned by now, but defensive handling
            'extends', 'block', 'endblock', 'closing_tag' => '',
            default => throw new RuntimeException("Unknown node type: {$node['type']}")
        };
    }

    private function compileText(array $node): string
    {
        $content = $node['content'] ?? '';

        // Fast escape using strtr
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
        if (($node['type'] ?? '') === 'literal') {
            $value = $node['value'] ?? '';
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

        $name = $node['name'] ?? '';
        $path = $node['path'] ?? [];

        // For simple variables without path
        if (empty($path)) {
            $code = "\$renderer->get('{$name}')";
        } else {
            // For nested accesses
            $code = "\$renderer->get('{$name}')";

            foreach ($path as $property) {
                if (is_numeric($property)) {
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
                    $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $code = "\$renderer->applyFilter('{$filterName}', {$code}, {$paramsJson})";
                }
            }
        }

        return $code;
    }

    private function compileLiteral(array $node): string
    {
        $value = $node['value'] ?? '';
        $filters = $node['filters'] ?? [];

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

    private function compileIf(array $node): string
    {
        $condition = $this->compileCondition($node['condition'] ?? []);

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
        $type = $condition['type'] ?? 'variable';

        return match ($type) {
            'variable' => $this->compileVariableCondition($condition['expression'] ?? []),
            'comparison' => $this->compileComparison($condition),
            default => throw new RuntimeException("Unknown condition type: {$type}")
        };
    }

    private function compileVariableCondition(array $variable): string
    {
        $access = $this->compileVariableAccess($variable);
        return "!empty({$access})";
    }

    private function compileComparison(array $condition): string
    {
        $left = $this->compileVariableAccess($condition['left'] ?? []);
        $operator = $condition['operator'] ?? '==';
        $right = is_string($condition['right'] ?? '')
            ? "'" . str_replace("'", "\\'", $condition['right']) . "'"
            : ($condition['right'] ?? 'null');

        return "({$left}) {$operator} {$right}";
    }

    private function compileFor(array $node): string
    {
        $array = $node['array'] ?? '';
        $item = $node['item'] ?? '';

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
        $template = $node['template'] ?? '';

        if (isset($node['data_source']) && isset($node['variable'])) {
            // Include with data mapping
            $dataSource = $this->compileVariableAccess([
                'name' => explode('.', $node['data_source'])[0],
                'path' => array_slice(explode('.', $node['data_source']), 1)
            ]);

            return "echo \$renderer->includeWith('{$template}', '{$node['variable']}', {$dataSource});\n";
        }

        // Simple include
        return "echo \$renderer->include('{$template}');\n";
    }
}