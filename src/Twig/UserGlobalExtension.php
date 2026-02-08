<?php

namespace App\Twig;

use App\Service\UserService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class UserGlobalExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private UserService $userService) {}

    public function getGlobals(): array
    {
        return [
            'current_user' => $this->userService->getCurrentUser(),
            'current_user_id' => $this->userService->getCurrentUserId(),
            'current_user_type' => $this->userService->getCurrentUserType(),
            'current_user_entity' => $this->userService->getCurrentUserEntity(),
            'is_authenticated' => $this->userService->isAuthenticated(),
            'is_medecin' => $this->userService->isCurrentUserMedecin(),
            'is_patient' => $this->userService->isCurrentUserPatient(),
            'is_aide_soignant' => $this->userService->isCurrentUserAideSoignant(),
        ];
    }
}
