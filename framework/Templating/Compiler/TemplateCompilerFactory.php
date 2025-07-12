<?php

declare(strict_types=1);

namespace Framework\Templating\Compiler;

use Framework\Templating\Parser\TemplateParser;

/**
 * Factory for creating TemplateCompiler instances with proper template paths
 *
 * This factory pattern allows us to create compilers with different template paths
 * as needed, while still leveraging dependency injection for the parser.
 */
class TemplateCompilerFactory
{
    public function __construct(
        private readonly TemplateParser $parser
    )
    {
    }

    /**
     * Create a TemplateCompiler instance for a specific template path
     */
    public function create(string $templatePath): TemplateCompiler
    {
        return new TemplateCompiler($this->parser, $templatePath);
    }

    /**
     * Create a TemplateCompiler instance without a specific template path
     * (for simple templates without inheritance)
     */
    public function createSimple(): TemplateCompiler
    {
        return new TemplateCompiler($this->parser, '');
    }
}