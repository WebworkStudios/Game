<?php
declare(strict_types=1);
namespace App\Actions\Admin;

use App\Domain\User\Services\UserService;
use App\Domain\User\Services\AuthService;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Enums\UserRole;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;
use Framework\Security\AuthMiddleware;
use Framework\Security\RoleMiddleware;

/**
 * User Management Action - Admin-Benutzerverwaltung
 */
#[Route(
    path: '/admin/users',
    methods: ['GET', 'POST'],
    name: 'admin.users',
    middlewares: [AuthMiddleware::class, RoleMiddleware::class]
)]
class UserManagementAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuthService $authService,
        private readonly ResponseFactory $responseFactory
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->isPost()) {
            return $this->processAction($request);
        }

        return $this->showUserList($request);
    }

    /**
     * Zeigt Benutzerliste
     */
    private function showUserList(Request $request): Response
    {
        $status = $request->query('status');
        $role = $request->query('role');
        $page = (int)($request->query('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Filter anwenden
        if ($status) {
            $users = $this->userService->findByStatus(UserStatus::from($status), $limit, $offset);
        } elseif ($role) {
            $users = $this->userService->findByRole(UserRole::from($role), $limit, $offset);
        } else {
            // Alle User laden (vereinfacht - in Produktion Pagination)
            $users = [];
        }

        $stats = $this->userService->getUserStats();

        return $this->responseFactory->view('admin/users', [
            'title' => 'Benutzerverwaltung',
            'users' => $users,
            'stats' => $stats,
            'current_page' => $page,
            'filters' => [
                'status' => $status,
                'role' => $role,
            ],
        ]);
    }

    /**
     * Verarbeitet Admin-Aktionen
     */
    private function processAction(Request $request): Response
    {
        $data = $request->all();
        $action = $data['action'] ?? '';
        $userId = $data['user_id'] ?? '';

        if (!$userId) {
            return $this->responseFactory->redirect('/admin/users?error=no_user_id');
        }

        try {
            $userId = \App\Domain\User\ValueObjects\UserId::fromString($userId);

            switch ($action) {
                case 'activate':
                    $this->userService->changeUserStatus($userId, UserStatus::ACTIVE);
                    break;
                case 'suspend':
                    $this->userService->changeUserStatus($userId, UserStatus::SUSPENDED);
                    break;
                case 'make_admin':
                    $this->userService->changeUserRole($userId, UserRole::ADMIN);
                    break;
                case 'make_moderator':
                    $this->userService->changeUserRole($userId, UserRole::MODERATOR);
                    break;
                case 'make_user':
                    $this->userService->changeUserRole($userId, UserRole::USER);
                    break;
                case 'reset_login_attempts':
                    $this->userService->resetUserLoginAttempts($userId);
                    break;
                default:
                    return $this->responseFactory->redirect('/admin/users?error=invalid_action');
            }

            return $this->responseFactory->redirect('/admin/users?success=action_completed');

        } catch (\DomainException $e) {
            return $this->responseFactory->redirect('/admin/users?error=' . urlencode($e->getMessage()));
        }
    }
}