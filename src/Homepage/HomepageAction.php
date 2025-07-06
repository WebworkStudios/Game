<?php


declare(strict_types=1);

namespace Homepage;

use Framework\Core\Attributes\Route;
use Framework\Core\TemplateEngine;
use Framework\Core\SessionManagerInterface;

#[Route('/', 'GET', 'homepage')]
class HomepageAction
{
    private TemplateEngine $templates;
    private SessionManagerInterface $session;

    public function __construct(TemplateEngine $templates, SessionManagerInterface $session)
    {
        $this->templates = $templates;
        $this->session = $session;
    }

    public function __invoke(): void
    {
        $isAuthenticated = $this->session->isAuthenticated;

        $data = [
            'page_title' => 'Welcome to Kickerscup',
            'is_authenticated' => $isAuthenticated,
            'user_data' => $isAuthenticated ? $this->session->userData : null
        ];

        $this->templates->render('homepage', $data);
    }
}