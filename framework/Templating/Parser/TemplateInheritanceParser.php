<?php
declare(strict_types=1);

namespace Framework\Templating\Parser;

use Framework\Templating\Parser\Tokens\Token;
use Framework\Templating\Parser\Tokens\TokenType;

/**
 * Template Inheritance Parser - Handles extends/block logic BEFORE main parsing
 *
 * This class implements a two-phase parsing approach:
 * 1. Pre-processing: Extract template hierarchy and block definitions
 * 2. Main parsing: Parse the resolved template with block overrides
 */
class TemplateInheritanceParser
{
    private array $templateCache = [];
    private array $templateHierarchy = [];
    public array $blockDefinitions = [];

    public function __construct(
        private readonly TemplateParser $parser,
        private readonly string $templatePath
    ) {
    }

    /**
     * Parse template with full inheritance support
     */
    public function parseWithInheritance(string $content, string $templatePath): array
    {
        // Reset state
        $this->templateHierarchy = [];
        $this->blockDefinitions = [];

        // Phase 1: Build inheritance hierarchy
        $this->buildInheritanceHierarchy($content, $templatePath);

        // Phase 2: Resolve template inheritance
        $resolvedContent = $this->resolveInheritance();

        // Phase 3: Parse resolved template
        return $this->parser->parse($resolvedContent);
    }

    /**
     * Build complete inheritance hierarchy by following extends chain
     */
    private function buildInheritanceHierarchy(string $content, string $templatePath): void
    {
        $currentPath = $templatePath;
        $visited = [];

        while ($currentPath !== null) {
            // Prevent circular inheritance
            if (in_array($currentPath, $visited)) {
                throw new \RuntimeException("Circular template inheritance detected: " . implode(' -> ', $visited) . " -> {$currentPath}");
            }

            $visited[] = $currentPath;
            $templateContent = $this->loadTemplate($currentPath);

            // Extract template info
            $templateInfo = $this->extractTemplateInfo($templateContent, $currentPath);

            // Store in hierarchy (child first, parent last)
            array_unshift($this->templateHierarchy, $templateInfo);

            // Continue with parent template
            $currentPath = $templateInfo['extends'];
        }
    }

    /**
     * Extract extends and blocks from a template
     */
    private function extractTemplateInfo(string $content, string $templatePath): array
    {
        $tokens = $this->tokenizeForInheritance($content);
        $extends = null;
        $blocks = [];
        $otherContent = [];

        foreach ($tokens as $token) {
            if ($token['type'] === 'extends') {
                if ($extends !== null) {
                    throw new \RuntimeException("Multiple extends declarations in template: {$templatePath}");
                }
                $extends = $token['template'];
            } elseif ($token['type'] === 'block') {
                $blocks[$token['name']] = $token;
            } else {
                $otherContent[] = $token;
            }
        }

        return [
            'path' => $templatePath,
            'extends' => $extends,
            'blocks' => $blocks,
            'content' => $otherContent
        ];
    }

    /**
     * Tokenize content specifically for inheritance parsing
     */
    private function tokenizeForInheritance(string $content): array
    {
        $tokens = [];
        $position = 0;
        $length = strlen($content);

        // Split by block markers
        $pattern = '/(\{%\s*(?:extends|block|endblock).*?%})/s';
        $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $inBlock = false;
        $currentBlock = null;
        $blockContent = '';
        $blockDepth = 0;

        foreach ($parts as $part) {
            if (preg_match('/\{%\s*extends\s+["\']([^"\']+)["\']\s*%}/', $part, $matches)) {
                $tokens[] = [
                    'type' => 'extends',
                    'template' => $matches[1],
                    'raw' => $part
                ];
            } elseif (preg_match('/\{%\s*block\s+(\w+)\s*%}/', $part, $matches)) {
                if ($inBlock) {
                    $blockDepth++;
                    $blockContent .= $part;
                } else {
                    $inBlock = true;
                    $currentBlock = $matches[1];
                    $blockContent = '';
                    $blockDepth = 1;
                }
            } elseif (preg_match('/\{%\s*endblock\s*%}/', $part)) {
                if ($inBlock) {
                    $blockDepth--;
                    if ($blockDepth === 0) {
                        // Block complete
                        $tokens[] = [
                            'type' => 'block',
                            'name' => $currentBlock,
                            'content' => $blockContent,
                            'raw' => "{% block {$currentBlock} %}{$blockContent}{% endblock %}"
                        ];
                        $inBlock = false;
                        $currentBlock = null;
                        $blockContent = '';
                    } else {
                        $blockContent .= $part;
                    }
                }
            } else {
                if ($inBlock) {
                    $blockContent .= $part;
                } else {
                    // Regular content (not in a block)
                    if (trim($part) !== '') {
                        $tokens[] = [
                            'type' => 'content',
                            'content' => $part,
                            'raw' => $part
                        ];
                    }
                }
            }
        }

        return $tokens;
    }

    /**
     * Resolve inheritance by building final template content
     */
    private function resolveInheritance(): string
    {
        if (empty($this->templateHierarchy)) {
            throw new \RuntimeException("No templates in hierarchy");
        }

        // Start with the root template (last in hierarchy)
        $rootTemplate = end($this->templateHierarchy);
        $resolvedContent = $this->buildContentFromTokens($rootTemplate['content']);

        // Collect all block definitions (child blocks override parent blocks)
        $allBlocks = [];
        foreach ($this->templateHierarchy as $template) {
            foreach ($template['blocks'] as $blockName => $blockData) {
                $allBlocks[$blockName] = $blockData['content'];
            }
        }

        // Replace block placeholders with resolved block content
        $resolvedContent = $this->replaceBlockPlaceholders($resolvedContent, $allBlocks);

        return $resolvedContent;
    }

    /**
     * Build content string from tokens
     */
    private function buildContentFromTokens(array $tokens): string
    {
        $content = '';
        foreach ($tokens as $token) {
            $content .= $token['raw'] ?? $token['content'] ?? '';
        }
        return $content;
    }

    /**
     * Replace block placeholders with actual block content
     */
    private function replaceBlockPlaceholders(string $content, array $blocks): string
    {
        // Replace each block placeholder
        foreach ($blocks as $blockName => $blockContent) {
            $pattern = '/\{%\s*block\s+' . preg_quote($blockName, '/') . '\s*%}.*?\{%\s*endblock\s*%}/s';
            $replacement = trim($blockContent);
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    // framework/Templating/Parser/TemplateInheritanceParser.php

    /**
     * Load template content from file
     */
    private function loadTemplate(string $templatePath): string
    {
        if (isset($this->templateCache[$templatePath])) {
            return $this->templateCache[$templatePath];
        }

        // Check if templatePath is already absolute
        if ($this->isAbsolutePath($templatePath)) {
            $fullPath = $templatePath;
        } else {
            // For relative paths like "layouts/base.html", use template root
            $fullPath = $this->templatePath . '/' . $templatePath;
        }

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Template not found: {$fullPath}");
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read template: {$fullPath}");
        }

        $this->templateCache[$templatePath] = $content;
        return $content;
    }

    /**
     * Check if path is absolute
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') ||
            (strlen($path) > 1 && $path[1] === ':'); // Windows drive letter
    }


    /**
     * Get template root directory from template path
     */
    private function getTemplateRoot(): string
    {
        // If templatePath is already a root directory, use it
        if (is_dir($this->templatePath)) {
            return $this->templatePath;
        }

        // Extract root from absolute path
        if (str_contains($this->templatePath, '/Views/') || str_contains($this->templatePath, '\\Views\\')) {
            $pattern = '/^(.+[\/\\\\]Views)[\/\\\\].+$/';
            if (preg_match($pattern, $this->templatePath, $matches)) {
                return $matches[1];
            }
        }

        // Fallback: use directory of current template
        return dirname($this->templatePath);
    }
}