<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use App\Repository\FormationRepository;
use App\Repository\DemandeAideRepository;
use App\Repository\AideSoignantRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Mission;
use App\Entity\Formation;

final class AideSoingnantController extends BaseController
{
    public function __construct(UserService $userService)
    {
        parent::__construct($userService);
    }

    #[Route('/aide-soignant/dashboard', name: 'app_aide_soignant_dashboard')]
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

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];
        
        return $this->render('aide_soingnant/aideSoignantDashboard.html.twig', [
            'aideSoignant' => $aideSoignant,
            'userId' => $userId,
            'navigation' => $navigation,
        ]);
    }

 #[Route('/aide-soignant/formations', name: 'aidesoignant_formations')]
public function formations(Request $request, FormationRepository $formationRepository): Response
{
    $this->denyAccessUnlessGranted('ROLE_USER');
    
    $userId = $this->getCurrentUserId();
    $aideSoignant = $this->getCurrentAideSoignant();
    
    $selectedCategory = $request->query->get('category');
    $searchTerm = $request->query->get('search');

    $formations = $formationRepository->findValidatedByCategory($selectedCategory, $searchTerm);
    $categories = $formationRepository->findAllCategories();

    $navigation = [
        ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
        ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
        ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
    ];

    // ✅ If AJAX request → return only cards
    if ($request->isXmlHttpRequest()) {
        return $this->render('formation/_formations_list.html.twig', [
            'formations' => $formations,
            'current_user_type' => 'aidesoignant',
        ]);
    }

    // ✅ Normal page load
    return $this->render('formation/aidesoingnant_formations_list.html.twig', [
        'formations' => $formations,
        'categories' => $categories,
        'selectedCategory' => $selectedCategory,
        'searchTerm' => $searchTerm,
        'userId' => $userId,
        'aideSoignant' => $aideSoignant,
        'navigation' => $navigation,
        'current_user_type' => 'aidesoignant',
    ]);
}

 #[Route('/formation/{id}', name: 'formation_details')]
    public function details(Formation $formation): Response
    {
        return $this->render('formation/aideSoingnantFormation.html.twig', [
            'formation' => $formation
        ]);
    }

    #[Route('/aidesoingnant/missions', name: 'aidesoingnant_missions')]
    public function missions(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Get all missions (both EN_ATTENTE and processed ones)
        $missions = $entityManager->getRepository(Mission::class)->findAll();
        
        // Sort by most recent first
        usort($missions, function($a, $b) {
            return $b->getId() <=> $a->getId();
        });

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('mission/index.html.twig', [
            'missions' => $missions,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/aidesoingnant/missions/accept/{id}', name: 'aidesoingnant_missions_accept')]
    public function acceptMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $mission = $entityManager->getRepository(Mission::class)->findOneBy(['demandeAide' => $demande]);
        if (!$mission) {
            throw $this->createNotFoundException('Mission not found');
        }

        // Get current aide-soignant from UserService
        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to accept missions');
        }

        // Link aide-soignant to mission and update status
        $mission->setAideSoignant($aideSoignant);
        $mission->setStatutMission('ACCEPTED');
        $demande->setStatut('ACCEPTED');

        $entityManager->flush();

        $this->addFlash('success', 'Mission acceptée avec succès!');
        return $this->redirectToRoute('aidesoingnant_missions');
    }

    #[Route('/aidesoingnant/missions/refuse/{id}', name: 'aidesoingnant_missions_refuse')]
    public function refuseMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $mission = $entityManager->getRepository(Mission::class)->findOneBy(['demandeAide' => $demande]);
        if (!$mission) {
            throw $this->createNotFoundException('Mission not found');
        }

        // Verify user is aide soignant
        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to refuse missions');
        }

        // Update mission and demande status
        $mission->setStatutMission('REFUSED');
        $demande->setStatut('REFUSED');

        $entityManager->flush();

        $this->addFlash('success', 'Mission refusée avec succès.');
        return $this->redirectToRoute('aidesoingnant_missions');
    }

    #[Route('/aidesoingnant/missions/details/{id}', name: 'aidesoingnant_missions_details')]
    public function showMission(int $id, DemandeAideRepository $demandeAideRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('mission/show.html.twig', [
            'demande' => $demande,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/aidesoingnant/missions/propose-price/{id}', name: 'aidesoingnant_missions_propose_price', methods: ['POST'])]
    public function proposePrice(int $id, Request $request, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $demande = $demandeAideRepository->find($id);
            if (!$demande) {
                return new JsonResponse(['success' => false, 'message' => 'Demande not found'], 404);
            }

            $data = json_decode($request->getContent(), true);
            $proposedPrice = $data['proposedPrice'] ?? null;

            if (!is_numeric($proposedPrice) || $proposedPrice <= 0) {
                return new JsonResponse(['success' => false, 'message' => 'Le prix doit être un nombre positif.'], 400);
            }

            if ($proposedPrice > $demande->getBudgetMax() + 50) {
                return new JsonResponse(['success' => false, 'message' => 'Le prix proposé doit être inférieur ou égal au budget maximum + 50 DT.'], 400);
            }

            // Update the mission price
            $mission = $entityManager->getRepository(Mission::class)->findOneBy(['demandeAide' => $demande]);
            if (!$mission) {
                return new JsonResponse(['success' => false, 'message' => 'Mission not found'], 404);
            }

            $mission->setPrixFinal($proposedPrice);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Prix proposé avec succès!']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/aidesoingnant/missions/delete/{id}', name: 'aidesoingnant_missions_delete', methods: ['POST'])]
    public function deleteMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        // Delete the demandeAide (this will cascade delete the associated mission)
        $entityManager->remove($demande);
        $entityManager->flush();

        $this->addFlash('success', 'Mission supprimée avec succès.');

        return $this->redirectToRoute('aidesoingnant_missions');
    }

    #[Route('/aide-soignant/formation/{id}/apply', name: 'aidesoingnant_formation_apply', methods: ['POST'])]
    public function applyForFormation(int $id, FormationRepository $formationRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $formation = $formationRepository->find($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation not found');
        }

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to apply for formations');
        }

        // Check if already applied
        if ($aideSoignant->getFormations()->contains($formation)) {
            $this->addFlash('warning', 'Vous avez déjà postulé à cette formation.');
            return $this->redirectToRoute('aidesoignant_formations');
        }

        // Add aide soignant to formation
        $aideSoignant->addFormation($formation);
        $entityManager->flush();

        $this->addFlash('success', 'Vous avez postulé à la formation avec succès!');
        return $this->redirectToRoute('aidesoignant_formations');
    }

    #[Route('/aide-soignant/formation/{id}/withdraw', name: 'aidesoingnant_formation_withdraw', methods: ['POST'])]
    public function withdrawFromFormation(int $id, FormationRepository $formationRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $formation = $formationRepository->find($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation not found');
        }

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to withdraw from formations');
        }

        // Check if applied
        if (!$aideSoignant->getFormations()->contains($formation)) {
            $this->addFlash('warning', 'Vous n\'avez pas postulé à cette formation.');
            return $this->redirectToRoute('aidesoignant_formations');
        }

        // Remove aide soignant from formation
        $aideSoignant->removeFormation($formation);
        $entityManager->flush();

        $this->addFlash('success', 'Vous avez retiré votre candidature avec succès!');
        return $this->redirectToRoute('aidesoignant_formations');
    }
}
