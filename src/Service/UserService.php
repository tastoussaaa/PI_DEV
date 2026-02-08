<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Medecin;
use App\Entity\AideSoignant;
use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UserService
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em
    ) {}

    /**
     * Get the currently connected user
     */
    public function getCurrentUser(): ?User
    {
        return $this->security->getUser();
    }

    /**
     * Get the ID of currently connected user
     */
    public function getCurrentUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user?->getId();
    }

    /**
     * Get the type of currently connected user (medecin, patient, aidesoignant)
     */
    public function getCurrentUserType(): ?string
    {
        $user = $this->getCurrentUser();
        return $user?->getUserType();
    }

    /**
     * Get the specific entity (Medecin, Patient, AideSoignant) for current user
     */
    public function getCurrentUserEntity(): Medecin|AideSoignant|Patient|null
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return null;
        }

        return match ($user->getUserType()) {
            'medecin' => $this->em->getRepository(Medecin::class)->findOneBy(['user' => $user]),
            'patient' => $this->em->getRepository(Patient::class)->findOneBy(['user' => $user]),
            'aidesoignant' => $this->em->getRepository(AideSoignant::class)->findOneBy(['user' => $user]),
            default => null,
        };
    }

    /**
     * Get Medecin entity for current user (if user is a Medecin)
     */
    public function getCurrentMedecin(): ?Medecin
    {
        $user = $this->getCurrentUser();
        if (!$user || $user->getUserType() !== 'medecin') {
            return null;
        }
        return $this->em->getRepository(Medecin::class)->findOneBy(['user' => $user]);
    }

    /**
     * Get Patient entity for current user (if user is a Patient)
     */
    public function getCurrentPatient(): ?Patient
    {
        $user = $this->getCurrentUser();
        if (!$user || $user->getUserType() !== 'patient') {
            return null;
        }
        return $this->em->getRepository(Patient::class)->findOneBy(['user' => $user]);
    }

    /**
     * Get AideSoignant entity for current user (if user is an AideSoignant)
     */
    public function getCurrentAideSoignant(): ?AideSoignant
    {
        $user = $this->getCurrentUser();
        if (!$user || $user->getUserType() !== 'aidesoignant') {
            return null;
        }
        return $this->em->getRepository(AideSoignant::class)->findOneBy(['user' => $user]);
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->security->getUser() !== null;
    }

    /**
     * Check if current user is a Medecin
     */
    public function isCurrentUserMedecin(): bool
    {
        return $this->getCurrentUserType() === 'medecin';
    }

    /**
     * Check if current user is a Patient
     */
    public function isCurrentUserPatient(): bool
    {
        return $this->getCurrentUserType() === 'patient';
    }

    /**
     * Check if current user is an AideSoignant
     */
    public function isCurrentUserAideSoignant(): bool
    {
        return $this->getCurrentUserType() === 'aidesoignant';
    }
}
