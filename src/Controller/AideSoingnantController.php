<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\FormationRepository;

final class AideSoingnantController extends AbstractController
{
    #[Route('/aidesoingnant/dashboard', name: 'aidesoingnant_dashboard')]
    public function dashboard()
    {
        return $this->render('aide_soingnant/aideSoignantDashboard.html.twig');
    }

    #[Route('/aidesoingnant/formations', name: 'aidesoingnant_formations')]
    public function formations(FormationRepository $formationRepository): Response
    {
        $formations = $formationRepository->findValidated();

        return $this->render('formation/formations.html.twig', [
            'formations' => $formations
        ]);
    }
}
