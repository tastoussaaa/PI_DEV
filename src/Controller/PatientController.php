<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PatientController extends AbstractController
{
    #[Route('/patient/dashboard', name: 'patient_dashboard')]
    public function dashboard()
    {
        return $this->render('patient/patientDashboard.html.twig');
    }

    #[Route('/consultation/new', name: 'patient_consultations')]
    public function consultations()
    {
        return $this->render('consultation/patientConsultations.html.twig');
    }

}
