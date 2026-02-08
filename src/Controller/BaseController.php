<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Medecin;
use App\Entity\AideSoignant;
use App\Entity\Patient;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Base controller with user-related helper methods
 */
abstract class BaseController extends AbstractController
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Get current authenticated user
     */
    protected function getCurrentUser(): ?User
    {
        return $this->userService->getCurrentUser();
    }

    /**
     * Get ID of current authenticated user
     */
    protected function getCurrentUserId(): ?int
    {
        return $this->userService->getCurrentUserId();
    }

    /**
     * Get type of current user (medecin, patient, aidesoignant)
     */
    protected function getCurrentUserType(): ?string
    {
        return $this->userService->getCurrentUserType();
    }

    /**
     * Get the specific entity (Medecin, Patient, AideSoignant) for current user
     */
    protected function getCurrentUserEntity(): Medecin|AideSoignant|Patient|null
    {
        return $this->userService->getCurrentUserEntity();
    }

    /**
     * Get current Medecin entity (if user is a Medecin)
     */
    protected function getCurrentMedecin(): ?Medecin
    {
        return $this->userService->getCurrentMedecin();
    }

    /**
     * Get current Patient entity (if user is a Patient)
     */
    protected function getCurrentPatient(): ?Patient
    {
        return $this->userService->getCurrentPatient();
    }

    /**
     * Get current AideSoignant entity (if user is an AideSoignant)
     */
    protected function getCurrentAideSoignant(): ?AideSoignant
    {
        return $this->userService->getCurrentAideSoignant();
    }

    /**
     * Check if user is authenticated
     */
    protected function isUserAuthenticated(): bool
    {
        return $this->userService->isAuthenticated();
    }

    /**
     * Check if current user is a Medecin
     */
    protected function isCurrentUserMedecin(): bool
    {
        return $this->userService->isCurrentUserMedecin();
    }

    /**
     * Check if current user is a Patient
     */
    protected function isCurrentUserPatient(): bool
    {
        return $this->userService->isCurrentUserPatient();
    }

    /**
     * Check if current user is an AideSoignant
     */
    protected function isCurrentUserAideSoignant(): bool
    {
        return $this->userService->isCurrentUserAideSoignant();
    }
}
