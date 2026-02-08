<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\Request;

final class AideSoingnantController extends BaseController
{
    public function __construct(UserService $userService)
    {
        parent::__construct($userService);
    }

    #[Route('/aidesoingnant/dashboard', name: 'app_aide_soignant_dashboard')]
    public function dashboard(): Response
    {
        // Ensure user is authenticated
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Ensure only aide soignants can access this dashboard
        if (!$this->isCurrentUserAideSoignant()) {
            $userType = $this->getCurrentUserType();
            return match ($userType) {
                'medecin' => $this->redirectToRoute('app_medecin_dashboard'),
                'patient' => $this->redirectToRoute('app_patient_dashboard'),
                'admin' => $this->redirectToRoute('app_admin_dashboard'),
                default => $this->redirectToRoute('app_login'),
            };
        }
        
        $aideSoignant = $this->getCurrentAideSoignant();
        $userId = $this->getCurrentUserId();
        
        return $this->render('aide_soingnant/aideSoignantDashboard.html.twig', [
            'aideSoignant' => $aideSoignant,
            'userId' => $userId,
        ]);
    }

    #[Route('/aidesoignant/formations', name: 'aidesoignant_formations')]
    public function formations(Request $request, FormationRepository $formationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $userId = $this->getCurrentUserId();
        $aideSoignant = $this->getCurrentAideSoignant();
        
        // Get selected category from query parameter (e.g., ?category=Urgence)
        $selectedCategory = $request->query->get('category');

        // Get formations filtered by category (or all if none selected)
        $formations = $formationRepository->findValidatedByCategory($selectedCategory);

        // Get all categories for dropdown
        $categories = $formationRepository->findAllCategories();

        return $this->render('formation/formations.html.twig', [
            'formations' => $formations,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'userId' => $userId,
            'aideSoignant' => $aideSoignant,
        ]);
    }
}
