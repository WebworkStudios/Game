<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;

/**
 * TemplateToken - Basis-Interface für alle Token-Typen
 */
interface TemplateToken
{
    public static function fromArray(array $data): self;

    public function getType(): string;

    public function toArray(): array;
}