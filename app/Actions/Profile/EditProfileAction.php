<?php
declare(strict_types=1);
namespace App\Actions\Profile;

use App\Domain\User\Services\AuthService;
use App\Domain\User\Services\UserService;
use App\Domain\User\Services\ImageUploadService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;
use Framework\Validation\Validator;
use Framework\Security\Csrf;
use Framework\Security\AuthMiddleware;

/**
 * Edit Profile Action - Profil bearbeiten
 */
#[Route(path: '/profile/edit', methods: ['GET', 'POST'], name: 'profile.edit', middlewares: [AuthMiddleware::class])]
class EditProfileAction
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserService $userService,
        private readonly ImageUploadService $imageUploadService,
        private readonly ResponseFactory $responseFactory,
        private readonly Validator $validator,
        private readonly Csrf $csrf
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();

        if ($request->isGet()) {
            return $this->showForm($user);
        }

        return $this->processUpdate($request, $user);
    }

    /**
     * Zeigt Bearbeitungs-Formular
     */
    private function showForm($user): Response
    {
        return $this->responseFactory->view('profile/edit', [
            'title' => 'Profil bearbeiten',
            'csrf_token' => $this->csrf->getToken(),
            'user' => $user,
        ]);
    }

    /**
     * Verarbeitet Profil-Update
     */
    private function processUpdate(Request $request, $user): Response
    {
        $data = $request->all();
        $updateType = $data['update_type'] ?? '';

        switch ($updateType) {
            case 'username':
                return $this->updateUsername($request, $user, $data);
            case 'password':
                return $this->updatePassword($request, $user, $data);
            case 'profile_image':
                return $this->updateProfileImage($request, $user);
            default:
                return $this->responseFactory->redirect('/profile/edit');
        }
    }

    /**
     * Username ändern
     */
    private function updateUsername(Request $request, $user, array $data): Response
    {
        $validation = $this->validator->validate($data, [
            'username' => 'required|string|min:3|max:50|unique:users,username,' . $user->getId()->toInt(),
        ]);

        if ($validation->fails()) {
            return $this->showForm($user)->withErrors($validation->errors(), 'username');
        }

        try {
            $updatedUser = $this->userService->changeUsername($user->getId(), $data['username']);
            $this->authService->updateSessionData($updatedUser);

            return $this->responseFactory->redirect('/profile?success=username_updated');

        } catch (\DomainException $e) {
            return $this->showForm($user)->withError($e->getMessage(), 'username');
        }
    }

    /**
     * Passwort ändern
     */
    private function updatePassword(Request $request, $user, array $data): Response
    {
        $validation = $this->validator->validate($data, [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|max:128',
            'new_password_confirmation' => 'required|same:new_password',
        ]);

        if ($validation->fails()) {
            return $this->showForm($user)->withErrors($validation->errors(), 'password');
        }

        try {
            $this->userService->changePassword(
                $user->getId(),
                $data['current_password'],
                $data['new_password']
            );

            return $this->responseFactory->redirect('/profile?success=password_updated');

        } catch (\DomainException $e) {
            return $this->showForm($user)->withError($e->getMessage(), 'password');
        }
    }

    /**
     * Profilbild ändern
     */
    private function updateProfileImage(Request $request, $user): Response
    {
        $files = $request->files();

        if (!isset($files['profile_image']) || $files['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
            return $this->showForm($user)->withError('Keine Datei ausgewählt', 'image');
        }

        try {
            $filename = $this->imageUploadService->processProfileImage(
                $files['profile_image'],
                $user->getId()
            );

            // Altes Bild löschen
            if ($user->getProfileImagePath()) {
                $this->imageUploadService->deleteProfileImage($user->getProfileImagePath());
            }

            $updatedUser = $this->userService->setProfileImage($user->getId(), $filename);
            $this->authService->updateSessionData($updatedUser);

            return $this->responseFactory->redirect('/profile?success=image_updated');

        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->showForm($user)->withError($e->getMessage(), 'image');
        }
    }
}
