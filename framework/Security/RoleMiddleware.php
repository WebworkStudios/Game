<?php
// ========================================================================
// framework/Security/RoleMiddleware.php
// ========================================================================

namespace Framework\Security;

use App\Domain\User\Services\AuthService;
use App\Domain\User\Enums\UserRole;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Http\HttpStatus;
use Framework\Routing\MiddlewareInterface;

/**
 * Role Middleware - Rollenbasierte Zugriffskontrolle
 */
class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ResponseFactory $responseFactory,
        private readonly string $requiredRole = 'user'
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return $this->handleUnauthenticated($request);
        }

        $requiredRoleEnum = UserRole::from($this->requiredRole);

        if (!$user->hasMinimumRole($requiredRoleEnum)) {
            return $this->handleInsufficientRole($request, $user->getRole()->label(), $requiredRoleEnum->label());
        }

        return $next($request);
    }

    /**
     * Factory für spezifische Rollen
     */
    public static function requireAdmin(AuthService $authService, ResponseFactory $responseFactory): self
    {
        return new self($authService, $responseFactory, 'admin');
    }

    public static function requireModerator(AuthService $authService, ResponseFactory $responseFactory): self
    {
        return new self($authService, $responseFactory, 'moderator');
    }

    private function handleUnauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication required'
            ], HttpStatus::UNAUTHORIZED);
        }

        return $this->responseFactory->redirect('/login');
    }

    private function handleInsufficientRole(Request $request, string $userRole, string $requiredRole): Response
    {
        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'error' => 'Insufficient Role',
                'message' => "Required role: {$requiredRole}, your role: {$userRole}"
            ], HttpStatus::FORBIDDEN);
        }

        return $this->responseFactory->view('errors/403', [
            'message' => "Zugriff verweigert. Benötigte Rolle: {$requiredRole}"
        ]);
    }
}