<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class MedecinController extends AbstractController
{
    #[Route('/medecin/dashboard', name: 'medecin_dashboard')]
    public function dashboard()
    {
        return $this->render('medecin/dashboard.html.twig');
    }

    #[Route('/medecin/formations', name: 'medecin_formations')]
    public function formations()
    {
        return $this->render('formation/formations.html.twig');
    }

    #[Route('/medecin/consultations', name: 'medecin_consultations')]
    public function consultations()
    {
        return $this->render('consultation/consultations.html.twig');
    }
}
