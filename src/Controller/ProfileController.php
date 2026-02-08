<?php

namespace App\Controller;

use App\Service\UserService;
use App\Entity\User;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Entity\AideSoignant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/profile', name: 'profile_')]
class ProfileController extends BaseController
{
    public function __construct(UserService $userService)
    {
        parent::__construct($userService);
    }

    #[Route('/', name: 'index')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getCurrentUser();
        $userEntity = $this->getCurrentUserEntity();
        $userId = $this->getCurrentUserId();
        $userType = $this->getCurrentUserType();

        $dashboardRoute = match ($userType) {
            'medecin' => 'app_medecin_dashboard',
            'patient' => 'app_patient_dashboard',
            'aide_soignant' => 'app_aide_soignant_dashboard',
            'admin' => 'app_admin_dashboard',
            default => 'app_login',
        };

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl($dashboardRoute), 'icon' => '🏠'],
            ['name' => 'Mon Profil', 'path' => $this->generateUrl('profile_index'), 'icon' => '👤'],
        ];

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'userEntity' => $userEntity,
            'userId' => $userId,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/edit', name: 'edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getCurrentUser();
        $userEntity = $this->getCurrentUserEntity();
        $userId = $this->getCurrentUserId();
        $userType = $this->getCurrentUserType();

        $dashboardRoute = match ($userType) {
            'medecin' => 'app_medecin_dashboard',
            'patient' => 'app_patient_dashboard',
            'aide_soignant' => 'app_aide_soignant_dashboard',
            'admin' => 'app_admin_dashboard',
            default => 'app_login',
        };

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl($dashboardRoute), 'icon' => '🏠'],
            ['name' => 'Mon Profil', 'path' => $this->generateUrl('profile_index'), 'icon' => '👤'],
        ];
        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $fullName = trim((string)$request->request->get('fullName'));
            $email = trim((string)$request->request->get('email'));
            $currentPassword = (string)$request->request->get('currentPassword');
            $newPassword = (string)$request->request->get('newPassword');
            $confirmPassword = (string)$request->request->get('confirmPassword');

            // Validate full name
            if (empty($fullName)) {
                $error = "Le nom complet est requis.";
            }
            // Validate email
            elseif (empty($email)) {
                $error = "L'email est requis.";
            }
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "L'email est invalide.";
            }
            // Check if email already exists (but not for current user)
            elseif ($email !== $user->getEmail()) {
                $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existing) {
                    $error = "Cet email est déjà utilisé.";
                }
            }
            // If user wants to change password
            elseif (!empty($newPassword)) {
                if (empty($currentPassword)) {
                    $error = "Veuillez entrer votre mot de passe actuel.";
                } elseif (!$hasher->isPasswordValid($user, $currentPassword)) {
                    $error = "Le mot de passe actuel est incorrect.";
                } elseif (strlen($newPassword) < 6) {
                    $error = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
                } elseif ($newPassword !== $confirmPassword) {
                    $error = "Les mots de passe ne correspondent pas.";
                }
            }

            if (!$error) {
                // Update User entity
                $user->setFullName($fullName);
                $user->setEmail($email);

                if (!empty($newPassword)) {
                    $user->setPassword($hasher->hashPassword($user, $newPassword));
                }

                // Update specific user entity
                $userType = $this->getCurrentUserType();
                
                if ($userType === 'medecin' && $userEntity instanceof Medecin) {
                    $userEntity->setFullName($fullName);
                    $userEntity->setEmail($email);
                    
                    $specialty = trim((string)$request->request->get('specialty'));
                    $rpps = trim((string)$request->request->get('rpps'));
                    
                    if (!empty($specialty)) {
                        $userEntity->setSpecialite($specialty);
                    }
                    if (!empty($rpps)) {
                        $userEntity->setRpps($rpps);
                    }
                } elseif ($userType === 'patient' && $userEntity instanceof Patient) {
                    $userEntity->setFullName($fullName);
                    $userEntity->setEmail($email);
                    
                    $birthDate = $request->request->get('birthDate');
                    $ssn = trim((string)$request->request->get('ssn'));
                    
                    if (!empty($birthDate)) {
                        $userEntity->setBirthDate(new \DateTime($birthDate));
                    }
                    if (!empty($ssn)) {
                        $userEntity->setSsn($ssn);
                    }
                } elseif ($userType === 'aidesoignant' && $userEntity instanceof AideSoignant) {
                    // Parse fullName to nom and prenom
                    if (strpos($fullName, ' ') !== false) {
                        [$nom, $prenom] = explode(' ', $fullName, 2);
                    } else {
                        $nom = $fullName;
                        $prenom = '';
                    }
                    
                    $userEntity->setNom($nom);
                    $userEntity->setPrenom($prenom);
                    $userEntity->setEmail($email);
                    
                    $adeli = trim((string)$request->request->get('adeli'));
                    if (!empty($adeli)) {
                        $userEntity->setAdeli($adeli);
                    }
                }

                $em->persist($user);
                $em->persist($userEntity);
                $em->flush();

                $success = "Vos informations ont été mises à jour avec succès.";
            }
        }

        return $this->render('profile/edit.html.twig', [
            'user' => $user,
            'userEntity' => $userEntity,
            'userId' => $userId,
            'error' => $error,
            'success' => $success,
            'navigation' => $navigation,
        ]);
    }
}
