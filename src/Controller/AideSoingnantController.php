<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AideSoingnantController extends AbstractController
{
   #[Route('/aidesoingnant/dashboard', name: 'aidesoingnant_dashboard')]
    public function dashboard()
    {
        return $this->render('aide_soingnant/aideSoignantDashboard.html.twig');
    }

    #[Route('/aidesoingnant/formations', name: 'aidesoingnant_formation')]
    public function formations()
    {
        return $this->render('formation/aideSoingnantFormation.html.twig');
    }
}
