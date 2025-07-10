<?php


declare(strict_types=1);

namespace Framework\Security;

use Attribute;

/**
 * CSRF Exempt Attribute - Markiert Actions als CSRF-befreit
 *
 * Verwendung:
 * #[CsrfExempt]
 * class WebhookAction { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class CsrfExempt
{
    public function __construct(
        public ?string $reason = null
    )
    {
    }
}