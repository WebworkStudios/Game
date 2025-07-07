<?php
declare(strict_types=1);

namespace Registration\Actions;

use Framework\Core\Attributes\Route;
use Registration\Responder\RegistrationResponder;

#[Route('/register', 'GET', 'registration.form')]
class ShowRegistrationFormAction
{
    private RegistrationResponder $responder;

    public function __construct(RegistrationResponder $responder)
    {
        $this->responder = $responder;
    }

    public function __invoke(): void
    {
        $this->responder->showForm();
    }
}