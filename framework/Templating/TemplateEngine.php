<?php
// framework/Templating/TemplateEngine.php

declare(strict_types=1);

namespace Framework\Templating;

use RuntimeException;

/**
 * Template Engine - Twig-ähnliche Syntax mit Variables, Controls und Inheritance
 */
class TemplateEngine
{
    private array $paths = [];
    private array $data = [];
    protected array $blocks = [];
    private ?string $parentTemplate = null;

    public function __construct(array $templatePaths = [])
    {
        $this->paths = $templatePaths;
    }

    /**
     * Rendert Template mit Daten
     */
    public function render(string $template, array $data = []): string
    {
        $this->data = $data;
        $this->blocks = [];
        $this->parentTemplate = null;

        $templatePath = $this->findTemplate($template);
        $content = file_get_contents($templatePath);

        // Parse Template
        $parsed = $this->parseTemplate($content);

        // Handle inheritance
        if ($this->parentTemplate) {
            return $this->renderWithInheritance($parsed);
        }

        return $this->renderParsed($parsed);
    }

    /**
     * Findet Template-Datei in den konfigurierten Pfaden
     */
    private function findTemplate(string $template): string
    {
        // Add .html extension if not present
        if (!str_contains($template, '.')) {
            $template .= '.html';
        }

        foreach ($this->paths as $path) {
            $fullPath = rtrim($path, '/') . '/' . ltrim($template, '/');
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        throw new RuntimeException("Template not found: {$template}");
    }

    /**
     * Parst Template-Content in AST-ähnliche Struktur
     */
    private function parseTemplate(string $content): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            // Find next template tag
            $varStart = strpos($content, '{{', $offset);
            $controlStart = strpos($content, '{%', $offset);

            // Determine which comes first
            $nextTag = false;
            $nextPos = $length;

            if ($varStart !== false && ($controlStart === false || $varStart < $controlStart)) {
                $nextTag = 'variable';
                $nextPos = $varStart;
            } elseif ($controlStart !== false) {
                $nextTag = 'control';
                $nextPos = $controlStart;
            }

            // Add text before tag
            if ($nextPos > $offset) {
                $text = substr($content, $offset, $nextPos - $offset);
                if ($text !== '') {
                    $tokens[] = ['type' => 'text', 'content' => $text];
                }
            }

            if ($nextTag === false) {
                break;
            }

            // Parse the tag
            if ($nextTag === 'variable') {
                $endPos = strpos($content, '}}', $nextPos);
                if ($endPos === false) {
                    throw new RuntimeException('Unclosed variable tag');
                }

                $variable = trim(substr($content, $nextPos + 2, $endPos - $nextPos - 2));
                $tokens[] = ['type' => 'variable', 'name' => $variable];
                $offset = $endPos + 2;

            } elseif ($nextTag === 'control') {
                $endPos = strpos($content, '%}', $nextPos);
                if ($endPos === false) {
                    throw new RuntimeException('Unclosed control tag');
                }

                $control = trim(substr($content, $nextPos + 2, $endPos - $nextPos - 2));
                $tokens[] = $this->parseControlTag($control);
                $offset = $endPos + 2;
            }
        }

        return $tokens;
    }

    /**
     * Parst Control-Tags (if, for, extends, block, include)
     */
    private function parseControlTag(string $control): array
    {
        $parts = preg_split('/\s+/', trim($control), 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        return match ($command) {
            'extends' => ['type' => 'extends', 'template' => trim($args, '\'"')],
            'block' => ['type' => 'block', 'name' => trim($args)],
            'endblock' => ['type' => 'endblock'],
            'include' => ['type' => 'include', 'template' => trim($args, '\'"')],
            'if' => ['type' => 'if', 'condition' => $args],
            'endif' => ['type' => 'endif'],
            'for' => ['type' => 'for', 'expression' => $args],
            'endfor' => ['type' => 'endfor'],
            'else' => ['type' => 'else'],
            default => throw new RuntimeException("Unknown control tag: {$command}")
        };
    }

    /**
     * Rendert geparste Tokens
     */
    private function renderParsed(array $tokens): string
    {
        $output = '';
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            switch ($token['type']) {
                case 'text':
                    $output .= $token['content'];
                    break;

                case 'variable':
                    $output .= $this->renderVariable($token['name']);
                    break;

                case 'extends':
                    $this->parentTemplate = $token['template'];
                    break;

                case 'block':
                    $blockContent = $this->extractBlock($tokens, $i);
                    $this->blocks[$token['name']] = $blockContent['content'];
                    $i = $blockContent['endIndex'];
                    break;

                case 'include':
                    $output .= $this->renderInclude($token['template']);
                    break;

                case 'if':
                    $ifResult = $this->renderIf($tokens, $i);
                    $output .= $ifResult['output'];
                    $i = $ifResult['endIndex'];
                    break;

                case 'for':
                    $forResult = $this->renderFor($tokens, $i);
                    $output .= $forResult['output'];
                    $i = $forResult['endIndex'];
                    break;
            }

            $i++;
        }

        return $output;
    }

    /**
     * Rendert Variable mit Dot-Notation Support
     */
    private function renderVariable(string $name): string
    {
        $value = $this->getValue($name);

        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Holt Wert mit Dot-Notation (z.B. user.name)
     */
    private function getValue(string $name): mixed
    {
        if (str_contains($name, '.')) {
            $parts = explode('.', $name);
            $current = $this->data;

            foreach ($parts as $part) {
                if (is_array($current) && isset($current[$part])) {
                    $current = $current[$part];
                } else {
                    return null;
                }
            }

            return $current;
        }

        return $this->data[$name] ?? null;
    }

    /**
     * Extrahiert Block-Content zwischen block und endblock
     */
    private function extractBlock(array $tokens, int $startIndex): array
    {
        $content = [];
        $blockDepth = 1;
        $i = $startIndex + 1;

        while ($i < count($tokens) && $blockDepth > 0) {
            $token = $tokens[$i];

            if ($token['type'] === 'block') {
                $blockDepth++;
            } elseif ($token['type'] === 'endblock') {
                $blockDepth--;
                if ($blockDepth === 0) {
                    break;
                }
            }

            $content[] = $token;
            $i++;
        }

        return ['content' => $content, 'endIndex' => $i];
    }

    /**
     * Rendert Include
     */
    private function renderInclude(string $template): string
    {
        $includePath = $this->findTemplate($template);
        $includeContent = file_get_contents($includePath);
        $parsed = $this->parseTemplate($includeContent);

        return $this->renderParsed($parsed);
    }

    /**
     * Rendert IF-Bedingung
     */
    private function renderIf(array $tokens, int $startIndex): array
    {
        $condition = $tokens[$startIndex]['condition'];
        $conditionResult = $this->evaluateCondition($condition);

        $output = '';
        $i = $startIndex + 1;
        $ifDepth = 1;
        $inElse = false;

        while ($i < count($tokens) && $ifDepth > 0) {
            $token = $tokens[$i];

            if ($token['type'] === 'if') {
                $ifDepth++;
            } elseif ($token['type'] === 'endif') {
                $ifDepth--;
                if ($ifDepth === 0) {
                    break;
                }
            } elseif ($token['type'] === 'else' && $ifDepth === 1) {
                $inElse = true;
                $i++;
                continue;
            }

            // Render content based on condition
            if (($conditionResult && !$inElse) || (!$conditionResult && $inElse)) {
                if ($token['type'] === 'text') {
                    $output .= $token['content'];
                } elseif ($token['type'] === 'variable') {
                    $output .= $this->renderVariable($token['name']);
                }
                // TODO: Handle nested controls
            }

            $i++;
        }

        return ['output' => $output, 'endIndex' => $i];
    }

    /**
     * Einfache Bedingungsauswertung
     */
    private function evaluateCondition(string $condition): bool
    {
        $condition = trim($condition);

        // Simple variable check (user.isAdmin)
        if (!str_contains($condition, ' ')) {
            $value = $this->getValue($condition);
            return !empty($value);
        }

        // TODO: Erweiterte Bedingungen (==, !=, etc.)
        return false;
    }

    /**
     * Rendert FOR-Schleife
     */
    private function renderFor(array $tokens, int $startIndex): array
    {
        $expression = $tokens[$startIndex]['expression'];

        // Parse "item in items" syntax
        if (!preg_match('/(\w+)\s+in\s+([\w.]+)/', $expression, $matches)) {
            throw new RuntimeException("Invalid for loop syntax: {$expression}");
        }

        $itemVar = $matches[1];
        $arrayVar = $matches[2];
        $array = $this->getValue($arrayVar);

        if (!is_array($array)) {
            return ['output' => '', 'endIndex' => $this->findEndFor($tokens, $startIndex)];
        }

        $output = '';
        $loopContent = $this->extractForContent($tokens, $startIndex);

        foreach ($array as $item) {
            // Backup current data
            $originalData = $this->data;

            // Add loop item to data
            $this->data[$itemVar] = $item;

            // Render loop content
            $output .= $this->renderParsed($loopContent['content']);

            // Restore original data
            $this->data = $originalData;
        }

        return ['output' => $output, 'endIndex' => $loopContent['endIndex']];
    }

    /**
     * Extrahiert FOR-Content
     */
    private function extractForContent(array $tokens, int $startIndex): array
    {
        $content = [];
        $forDepth = 1;
        $i = $startIndex + 1;

        while ($i < count($tokens) && $forDepth > 0) {
            $token = $tokens[$i];

            if ($token['type'] === 'for') {
                $forDepth++;
            } elseif ($token['type'] === 'endfor') {
                $forDepth--;
                if ($forDepth === 0) {
                    break;
                }
            }

            $content[] = $token;
            $i++;
        }

        return ['content' => $content, 'endIndex' => $i];
    }

    /**
     * Findet endfor Position
     */
    private function findEndFor(array $tokens, int $startIndex): int
    {
        $forDepth = 1;
        $i = $startIndex + 1;

        while ($i < count($tokens) && $forDepth > 0) {
            $token = $tokens[$i];

            if ($token['type'] === 'for') {
                $forDepth++;
            } elseif ($token['type'] === 'endfor') {
                $forDepth--;
            }

            $i++;
        }

        return $i - 1;
    }

    /**
     * Rendert Template mit Vererbung
     */
    private function renderWithInheritance(array $childTokens): string
    {
        // Render parent template
        $parentPath = $this->findTemplate($this->parentTemplate);
        $parentContent = file_get_contents($parentPath);
        $parentTokens = $this->parseTemplate($parentContent);

        return $this->renderParsed($parentTokens);
    }

    /**
     * Fügt Template-Pfad hinzu
     */
    public function addPath(string $path): void
    {
        $this->paths[] = $path;
    }

    /**
     * Holt alle Template-Pfade
     */
    public function getPaths(): array
    {
        return $this->paths;
    }
}