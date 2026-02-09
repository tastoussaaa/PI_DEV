<?php

namespace App\Security;

use App\Service\UserService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private RouterInterface $router,
        private UserService $userService
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $userType = $this->userService->getCurrentUserType();

        $route = match ($userType) {
            'medecin' => 'app_medecin_dashboard',
            'patient' => 'app_patient_dashboard',
            'aidesoignant' => 'app_aide_soignant_dashboard',
            'admin' => 'app_admin_dashboard',
            default => 'app_home',
        };

        return new RedirectResponse($this->router->generate($route));
    }
}
