<?php
namespace Framework\Templating;

use Framework\Templating\Parsing\{TemplateParser, TemplateTokenizer, ControlFlowParser, TemplatePathResolver};
use Framework\Templating\Rendering\{TemplateRenderer, TemplateVariableResolver};

/**
 * TemplateEngine - Schlanke Koordinator-Klasse für Template-Rendering
 */
class TemplateEngine
{
    private readonly TemplateParser $parser;
    private readonly TemplateRenderer $renderer;

    public function __construct(
        private readonly TemplatePathResolver $pathResolver,
        private readonly TemplateCache $cache,
        private readonly FilterManager $filterManager
    ) {
        // Parser-Pipeline erstellen
        $tokenizer = new TemplateTokenizer();
        $controlFlowParser = new ControlFlowParser();
        $this->parser = new TemplateParser($tokenizer, $controlFlowParser, $pathResolver);

        // Renderer-Pipeline erstellen
        $variableResolver = new TemplateVariableResolver();
        $this->renderer = new TemplateRenderer($variableResolver, $filterManager, $pathResolver);
    }

    /**
     * Hauptmethode - Template rendern
     */
    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->pathResolver->resolve($template);

        // Try cache first
        if ($this->cache->isValid($template, [$templatePath])) {
            $compiled = $this->cache->load($template);
            if ($compiled !== null) {
                $parsedTemplate = \Framework\Templating\Parsing\ParsedTemplate::fromArray($compiled);
                return $this->renderer->render($parsedTemplate, $data);
            }
        }

        // Parse template
        $content = file_get_contents($templatePath);
        $parsedTemplate = $this->parser->parse($content, $templatePath);

        // Cache result
        $this->cache->store($template, $templatePath, $parsedTemplate->toArray(), $parsedTemplate->getDependencies());

        // Render
        return $this->renderer->render($parsedTemplate, $data);
    }

    /**
     * Widget-Rendering mit Fragment-Caching
     */
    public function renderWidget(string $template, array $data = [], int $ttl = 300): string
    {
        $cacheKey = 'widget_' . md5($template . serialize($data));

        if ($cached = $this->cache->getFragment($cacheKey)) {
            return $cached;
        }

        $content = $this->render($template, $data);
        $this->cache->storeFragment($cacheKey, $content, $ttl);

        return $content;
    }

    /**
     * Template mit Caching rendern
     */
    public function renderCached(string $template, array $data = [], int $ttl = 0): string
    {
        if ($ttl <= 0) {
            return $this->render($template, $data);
        }

        return $this->renderWidget($template, $data, $ttl);
    }
}