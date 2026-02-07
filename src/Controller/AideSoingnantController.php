<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\Request; // <-- make sure to add this at the top

final class AideSoingnantController extends AbstractController
{
    #[Route('/aidesoingnant/dashboard', name: 'aidesoingnant_dashboard')]
    public function dashboard()
    {
        return $this->render('aide_soingnant/aideSoignantDashboard.html.twig');
    }


    #[Route('/aidesoignant/formations', name: 'aidesoignant_formations')]
    public function formations(Request $request, FormationRepository $formationRepository): Response
    {
        // Get selected category from query parameter (e.g., ?category=Urgence)
        $selectedCategory = $request->query->get('category');

        // Get formations filtered by category (or all if none selected)
        $formations = $formationRepository->findValidatedByCategory($selectedCategory);

        // Get all categories for dropdown
        $categories = $formationRepository->findAllCategories();

        return $this->render('formation/formations.html.twig', [
            'formations' => $formations,         
            'categories' => $categories,          
            'selectedCategory' => $selectedCategory 
        ]);
    }
}
