<?php
namespace Framework\Templating\Parsing;

use Framework\Templating\Tokens\{TemplateToken, TokenFactory, ControlToken, TextToken, VariableToken};

/**
 * ControlFlowParser - Parst Control-Flow-Strukturen (if/for/block)
 */
class ControlFlowParser
{
    public function parseControlFlow(array $tokens): array
    {
        $parsed = [];
        $i = 0;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token instanceof ControlToken) {
                $result = $this->parseControlStructure($tokens, $i);
                $parsed[] = $result['token'];
                $i = $result['nextIndex'];
            } else {
                $parsed[] = $token;
                $i++;
            }
        }

        return $parsed;
    }

    private function parseControlStructure(array $tokens, int $startIndex): array
    {
        $startToken = $tokens[$startIndex];
        $command = $startToken->getCommand();

        return match ($command) {
            'if' => $this->parseIfStructure($tokens, $startIndex),
            'for' => $this->parseForStructure($tokens, $startIndex),
            'block' => $this->parseBlockStructure($tokens, $startIndex),
            default => ['token' => $startToken, 'nextIndex' => $startIndex + 1]
        };
    }

    private function parseIfStructure(array $tokens, int $startIndex): array
    {
        $startToken = $tokens[$startIndex];
        $children = [];
        $elseChildren = [];
        $currentChildren = &$children;
        $i = $startIndex + 1;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token instanceof ControlToken) {
                switch ($token->getCommand()) {
                    case 'endif':
                        $ifToken = $startToken->withChildren($children)->withElseChildren($elseChildren);
                        return ['token' => $ifToken, 'nextIndex' => $i + 1];

                    case 'else':
                        $currentChildren = &$elseChildren;
                        $i++;
                        continue 2;

                    default:
                        // Nested control structure
                        $result = $this->parseControlStructure($tokens, $i);
                        $currentChildren[] = $result['token'];
                        $i = $result['nextIndex'];
                        continue 2;
                }
            }

            $currentChildren[] = $token;
            $i++;
        }

        throw new \RuntimeException('Unclosed if statement');
    }

    private function parseForStructure(array $tokens, int $startIndex): array
    {
        $startToken = $tokens[$startIndex];
        $children = [];
        $elseChildren = [];
        $currentChildren = &$children;
        $i = $startIndex + 1;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token instanceof ControlToken) {
                switch ($token->getCommand()) {
                    case 'endfor':
                        $forToken = $startToken->withChildren($children)->withElseChildren($elseChildren);
                        return ['token' => $forToken, 'nextIndex' => $i + 1];

                    case 'else':
                        $currentChildren = &$elseChildren;
                        $i++;
                        continue 2;

                    default:
                        // Nested control structure
                        $result = $this->parseControlStructure($tokens, $i);
                        $currentChildren[] = $result['token'];
                        $i = $result['nextIndex'];
                        continue 2;
                }
            }

            $currentChildren[] = $token;
            $i++;
        }

        throw new \RuntimeException('Unclosed for statement');
    }

    private function parseBlockStructure(array $tokens, int $startIndex): array
    {
        $startToken = $tokens[$startIndex];
        $children = [];
        $i = $startIndex + 1;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token instanceof ControlToken && $token->getCommand() === 'endblock') {
                $blockToken = $startToken->withChildren($children);
                return ['token' => $blockToken, 'nextIndex' => $i + 1];
            }

            if ($token instanceof ControlToken) {
                $result = $this->parseControlStructure($tokens, $i);
                $children[] = $result['token'];
                $i = $result['nextIndex'];
            } else {
                $children[] = $token;
                $i++;
            }
        }

        throw new \RuntimeException('Unclosed block statement');
    }
}