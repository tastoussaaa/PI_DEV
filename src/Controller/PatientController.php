<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ConsultationRepository;
use App\Form\ConsultationType;

final class PatientController extends AbstractController
{
    #[Route('/patient/dashboard', name: 'patient_dashboard')]
    public function dashboard()
    {
        return $this->render('patient/patientDashboard.html.twig');
    }

    #[Route('/patient/consultations', name: 'patient_consultations')]
    public function consultations(ConsultationRepository $repository)
    {
        $user = $this->getUser();
        $consultations = [];

        if ($user && method_exists($user, 'getEmail')) {
            $email = $user->getEmail();
            $all = $repository->findAll();
            $consultations = array_filter($all, function($c) use ($email) {
                $ce = strtolower((string) $c->getEmail());
                return $ce !== '' && strcasecmp($ce, $email) === 0;
            });

            // sort by createdAt desc
            usort($consultations, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        }

        $form = $this->createForm(ConsultationType::class, null, [
            'action' => $this->generateUrl('consultation_new'),
        ]);

        return $this->render('consultation/patientConsultations.html.twig', [
            'consultations' => $consultations,
            'form' => $form->createView(),
        ]);
    }

}
