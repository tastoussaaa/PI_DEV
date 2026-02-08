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

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
        ];
        
        return $this->render('aide_soingnant/aideSoignantDashboard.html.twig', [
            'aideSoignant' => $aideSoignant,
            'userId' => $userId,
            'navigation' => $navigation,
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

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
        ];

        return $this->render('formation/formations.html.twig', [
            'formations' => $formations,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'userId' => $userId,
            'aideSoignant' => $aideSoignant,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/aidesoingnant/missions', name: 'aidesoingnant_missions')]
    public function missions(EntityManagerInterface $entityManager): Response
    {
        // Get all missions from database
        $missions = $entityManager->getRepository(Mission::class)->findAll();

        return $this->render('mission/index.html.twig', [
            'missions' => $missions
        ]);
    }

    #[Route('/aidesoingnant/missions/accept/{id}', name: 'aidesoingnant_missions_accept')]
    public function acceptMission(int $id, DemandeAideRepository $demandeAideRepository, AideSoignantRepository $aideSoignantRepository, EntityManagerInterface $entityManager): Response
    {
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $mission = $entityManager->getRepository(Mission::class)->findOneBy(['demandeAide' => $demande]);
        if (!$mission) {
            throw $this->createNotFoundException('Mission not found');
        }

        // Get current aide-soignant (assuming authenticated user)
        $user = $this->getUser();
        $aideSoignant = $aideSoignantRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('Aide-soignant not found');
        }

        // Link aide-soignant to mission and update status
        $mission->setAideSoignant($aideSoignant);
        $mission->setStatutMission('ACCEPTED');
        $demande->setStatut('ACCEPTED');

        $entityManager->flush();

        return $this->redirectToRoute('aidesoingnant_missions');
    }

    #[Route('/aidesoingnant/missions/refuse/{id}', name: 'aidesoingnant_missions_refuse')]
    public function refuseMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $mission = $entityManager->getRepository(Mission::class)->findOneBy(['demandeAide' => $demande]);
        if (!$mission) {
            throw $this->createNotFoundException('Mission not found');
        }

        // For demo purposes, no aide-soignant needed - just update status
        $mission->setStatutMission('REFUSED');
        $demande->setStatut('REFUSED');

        $entityManager->flush();

        return $this->redirectToRoute('aidesoingnant_missions');
    }

    #[Route('/aidesoingnant/missions/details/{id}', name: 'aidesoingnant_missions_details')]
    public function showMission(int $id, DemandeAideRepository $demandeAideRepository): Response
    {
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        return $this->render('mission/show.html.twig', [
            'demande' => $demande
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
}
