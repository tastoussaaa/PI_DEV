<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email = trim((string)$request->request->get('email'));
            $plainPassword = (string)$request->request->get('password');
            $fullName = trim((string)$request->request->get('fullName'));

            if ($email === '' || $plainPassword === '' || $fullName === '') {
                $error = "Veuillez remplir tous les champs.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email invalide.";
            } elseif (strlen($plainPassword) < 6) {
                $error = "Mot de passe trop court (min 6).";
            } else {
                // Vérifier si email existe déjà
                $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existing) {
                    $error = "Cet email est déjà utilisé.";
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFullName($fullName);
                    $user->setRoles(['ROLE_USER']);
                    $user->setPassword($hasher->hashPassword($user, $plainPassword));

                    $em->persist($user);
                    $em->flush();

                    // Après inscription, rediriger vers login
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'error' => $error,
        ]);
    }
}
