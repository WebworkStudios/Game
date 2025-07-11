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
            $blockCode = $this->compileNodes($block['body']);
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
            'literal' => $this->compileLiteral($node), // NEW
            'function' => $this->compileFunction($node),
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

        // Escape only quotes and backslashes, preserve UTF-8 characters
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $content
        );

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

    /**
     * Compile function call
     */
    private function compileFunction(array $node): string
    {
        $functionName = $node['name'];
        $params = $node['params'] ?? [];
        $filters = $node['filters'] ?? [];

        $functionCall = match ($functionName) {
            't' => $this->compileTranslateFunction($params),
            't_plural' => $this->compileTranslatePluralFunction($params),
            'locale' => "\$renderer->getCurrentLocale()",
            'locales' => "\$renderer->getSupportedLocales()",
            default => throw new RuntimeException("Unknown function: {$functionName}")
        };

        // Apply filters if present
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $filterName = $filter['name'];
                $filterParams = $filter['params'] ?? [];

                if (empty($filterParams)) {
                    $functionCall = "\$renderer->applyFilter('{$filterName}', {$functionCall})";
                } else {
                    $paramsJson = json_encode($filterParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $functionCall = "\$renderer->applyFilter('{$filterName}', {$functionCall}, {$paramsJson})";
                }
            }
        }

        return "echo \$renderer->escape({$functionCall});\n";
    }

    /**
     * Compile translate function
     */
    private function compileTranslateFunction(array $params): string
    {
        if (empty($params)) {
            throw new RuntimeException("Function t() requires at least one parameter");
        }

        $key = $this->compileParameter($params[0]);

        if (count($params) > 1) {
            $parametersParam = $this->compileParameter($params[1]);
            return "\$renderer->t({$key}, {$parametersParam})";
        }

        return "\$renderer->t({$key})";
    }

    /**
     * Compile function parameter
     */
    private function compileParameter(array $param): string
    {
        return match ($param['type']) {
            'string' => "'" . str_replace("'", "\\'", $param['value']) . "'",
            'number' => (string)$param['value'],
            'object' => $this->compileObjectParameter($param['value']),
            'variable' => "\$renderer->get('" . $param['value'] . "')",
            default => throw new RuntimeException("Unknown parameter type: {$param['type']}")
        };
    }

    /**
     * Compile object parameter (basic JSON object)
     */
    private function compileObjectParameter(string $objectString): string
    {
        // Simple object parsing: {key: 'value', key2: 'value2'}
        $objectString = trim($objectString, '{}');

        if (empty($objectString)) {
            return '[]';
        }

        $pairs = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $braceLevel = 0;

        // Parse object string properly respecting quotes and braces
        for ($i = 0; $i < strlen($objectString); $i++) {
            $char = $objectString[$i];

            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
                continue;
            }

            if ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = null;
                $current .= $char;
                continue;
            }

            if (!$inQuotes && $char === '{') {
                $braceLevel++;
            }

            if (!$inQuotes && $char === '}') {
                $braceLevel--;
            }

            if (!$inQuotes && $char === ',' && $braceLevel === 0) {
                // Split here
                $pair = trim($current);
                if (!empty($pair)) {
                    $pairs[] = $pair;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Add last pair
        $pair = trim($current);
        if (!empty($pair)) {
            $pairs[] = $pair;
        }

        $phpArray = [];

        foreach ($pairs as $pair) {
            if (str_contains($pair, ':')) {
                $parts = explode(':', $pair, 2);
                $key = trim($parts[0], '\'" ');
                $value = trim($parts[1]);

                // Remove quotes from value if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $phpArray[$key] = $value;
            }
        }

        // Return as PHP array syntax, not JSON
        return var_export($phpArray, true);
    }

    /**
     * Compile translate plural function
     */
    private function compileTranslatePluralFunction(array $params): string
    {
        if (count($params) < 2) {
            throw new RuntimeException("Function t_plural() requires at least two parameters");
        }

        $key = $this->compileParameter($params[0]);
        $count = $this->compileParameter($params[1]);

        if (count($params) > 2) {
            $parametersParam = $this->compileParameter($params[2]);
            return "\$renderer->tPlural({$key}, {$count}, {$parametersParam})";
        }

        return "\$renderer->tPlural({$key}, {$count})";
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

        // In base templates, render block with potential child override
        $code = "// Block: {$blockName}\n";
        $code .= "if (\$renderer->hasBlock('{$blockName}')) {\n";
        $code .= "echo \$renderer->renderBlock('{$blockName}');\n";
        $code .= "} else {\n";
        $code .= "// Default block content\n";
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