<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClientController extends AbstractController
{
    #[Route('/client', name: 'app_client')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_client_module', ['module' => 'profil']);
    }

    #[Route('/client/{module}', name: 'app_client_module')]
    public function module(string $module): Response
    {
        $allowed = [
            'profil',         // Utilisateur (profil)
            'formations',
            'consultations',
            'demandes',       // Demande
            'suivi',          // Suivi
            'produits',
        ];

        if (!in_array($module, $allowed, true)) {
            throw $this->createNotFoundException('Module introuvable');
        }

        return $this->render('client/portal.html.twig', [
            'activeModule' => $module,
        ]);
    }
}
