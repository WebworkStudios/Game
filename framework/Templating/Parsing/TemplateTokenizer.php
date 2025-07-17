<?php
namespace Framework\Templating\Parsing;

use Framework\Templating\Tokens\{TemplateToken, TokenFactory, ControlToken, TextToken, VariableToken};

/**
 * TemplateTokenizer - Konvertiert Template-String zu Token-Array
 */
class TemplateTokenizer
{
    private const array TAG_PATTERNS = [
        '{{' => 'variable',
        '{%' => 'control',
        '{#' => 'comment'
    ];

    private const array TAG_ENDINGS = [
        'variable' => '}}',
        'control' => '%}',
        'comment' => '#}'
    ];

    public function tokenize(string $content): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $nextTag = $this->findNextTag($content, $offset);

            if ($nextTag === null) {
                // Rest as text
                if ($offset < $length) {
                    $tokens[] = TokenFactory::createText(substr($content, $offset));
                }
                break;
            }

            // Add text before tag
            if ($nextTag['position'] > $offset) {
                $tokens[] = TokenFactory::createText(
                    substr($content, $offset, $nextTag['position'] - $offset)
                );
            }

            // Parse tag
            $token = $this->parseTag($content, $nextTag);
            if ($token !== null) {
                $tokens[] = $token;
            }

            $offset = $nextTag['end'];
        }

        return $tokens;
    }

    private function findNextTag(string $content, int $offset): ?array
    {
        $nextMatch = null;
        $nextPosition = PHP_INT_MAX;

        foreach (self::TAG_PATTERNS as $pattern => $type) {
            $position = strpos($content, $pattern, $offset);
            if ($position !== false && $position < $nextPosition) {
                $nextPosition = $position;
                $nextMatch = ['position' => $position, 'type' => $type];
            }
        }

        return $nextMatch;
    }

    private function parseTag(string $content, array $tagInfo): ?TemplateToken
    {
        $ending = self::TAG_ENDINGS[$tagInfo['type']];
        $endPos = strpos($content, $ending, $tagInfo['position']);

        if ($endPos === false) {
            return null;
        }

        $tagInfo['end'] = $endPos + strlen($ending);
        $startPos = $tagInfo['position'] + strlen(array_search($tagInfo['type'], self::TAG_PATTERNS));
        $expression = trim(substr($content, $startPos, $endPos - $startPos));

        return match ($tagInfo['type']) {
            'variable' => TokenFactory::createVariable($expression),
            'control' => TokenFactory::createControl($expression),
            'comment' => null, // Skip comments
            default => null
        };
    }
}