<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PatientController extends BaseController
{
    public function __construct(UserService $userService)
    {
        parent::__construct($userService);
    }

    #[Route('/patient/dashboard', name: 'app_patient_dashboard')]
    public function dashboard(): Response
    {
        // Ensure user is authenticated
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Ensure only patients can access this dashboard
        if (!$this->isCurrentUserPatient()) {
            $userType = $this->getCurrentUserType();
            return match ($userType) {
                'medecin' => $this->redirectToRoute('app_medecin_dashboard'),
                'aidesoignant' => $this->redirectToRoute('app_aide_soignant_dashboard'),
                'admin' => $this->redirectToRoute('app_admin_dashboard'),
                default => $this->redirectToRoute('app_login'),
            };
        }
        
        $patient = $this->getCurrentPatient();
        $userId = $this->getCurrentUserId();
        
        return $this->render('patient/patientDashboard.html.twig', [
            'patient' => $patient,
            'userId' => $userId,
        ]);
    }

    #[Route('/patient/consultations', name: 'patient_consultations')]
    public function consultations(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $userId = $this->getCurrentUserId();
        $patient = $this->getCurrentPatient();
        
        return $this->render('consultation/patientConsultations.html.twig', [
            'userId' => $userId,
            'patient' => $patient,
        ]);
    }
}
