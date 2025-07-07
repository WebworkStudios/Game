<?php


declare(strict_types=1);

namespace Homepage;

use Framework\Core\Attributes\Route;
use Framework\Core\TemplateEngine;

#[Route('/impressum', 'GET', 'impressum')]
class ImpressumAction
{
    private TemplateEngine $templates;

    public function __construct(TemplateEngine $templates)
    {
        $this->templates = $templates;
    }

    public function __invoke(): void
    {
        $data = [
            'page_title' => 'Impressum',
            'company_name' => 'Kickerscup GmbH',
            'address' => [
                'street' => 'Musterstraße 123',
                'postal_code' => '12345',
                'city' => 'Musterstadt',
                'country' => 'Deutschland'
            ],
            'contact' => [
                'phone' => '+49 (0) 123 456789',
                'fax' => '+49 (0) 123 456790',
                'email' => 'info@kickerscup.de'
            ],
            'legal' => [
                'managing_director' => 'Max Mustermann',
                'register_court' => 'Amtsgericht Musterstadt',
                'register_number' => 'HRB 12345',
                'vat_id' => 'DE123456789'
            ],
            'responsible_content' => [
                'name' => 'Max Mustermann',
                'address' => 'Musterstraße 123, 12345 Musterstadt'
            ]
        ];

        $this->templates->render('legal/impressum', $data);
    }
}