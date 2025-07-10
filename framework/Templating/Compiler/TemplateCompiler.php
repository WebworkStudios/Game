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

    public function __construct(
        private readonly TemplateParser $parser
    ) {}

    public function compile(string $content, string $templatePath = ''): string
    {
        $ast = $this->parser->parse($content);

        $header = sprintf(
            self::TEMPLATE_HEADER,
            $templatePath,
            date('Y-m-d H:i:s')
        );

        $body = $this->compileNodes($ast);

        return $header . $body;
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

    private function compileExtends(array $node): string
    {
        // Für Template-Vererbung - fürs erste als Kommentar
        return "// extends: {$node['template']}\n";
    }

    private function compileBlock(array $node): string
    {
        $body = $this->compileNodes($node['body']);
        return "// block: {$node['name']}\n{$body}// endblock\n";
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

        return <<<PHP
\$_array = \$renderer->get('{$array}') ?? [];
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
        return "echo \$renderer->include('{$template}');\n";
    }
}