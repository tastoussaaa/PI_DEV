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
    public function missions(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Get search and sort parameters
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'dateCreation');
        $sortOrder = $request->query->get('sort_order', 'desc');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10; // Items per page

        // Build query
        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->leftJoin('m.demandeAide', 'd')
            ->select('m, d');

        // Apply search filter
        if (!empty($search)) {
            switch ($sortBy) {
                case 'dateCreation':
                    // Handle date search
                    $date = $this->parseDate($search);
                    if ($date) {
                        $qb->andWhere('DATE(d.dateCreation) = :date')
                           ->setParameter('date', $date->format('Y-m-d'));
                    }
                    break;
                case 'budgetMax':
                    // Handle budget search
                    if (is_numeric($search)) {
                        $qb->andWhere('d.budgetMax = :budget')
                           ->setParameter('budget', (int) $search);
                    }
                    break;
                case 'typeDemande':
                    $qb->andWhere('d.typeDemande LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
                    break;
                case 'statutMission':
                    $qb->andWhere('m.statutMission LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
                    break;
                default:
                    $qb->andWhere('d.descriptionBesoin LIKE :search OR d.typeDemande LIKE :search OR m.statutMission LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
            }
        }

        // Apply sorting
        $orderDirection = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        switch ($sortBy) {
            case 'dateCreation':
                $qb->orderBy('d.dateCreation', $orderDirection);
                break;
            case 'typeDemande':
                $qb->orderBy('d.typeDemande', $orderDirection);
                break;
            case 'statutMission':
                $qb->orderBy('m.statutMission', $orderDirection);
                break;
            case 'budgetMax':
                $qb->orderBy('d.budgetMax', $orderDirection);
                break;
            default:
                $qb->orderBy('m.id', 'DESC');
        }

        // Get total count for pagination
        $totalCount = (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalCount / $limit);

        // Apply pagination
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $missions = $qb->getQuery()->getResult();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('mission/list.html.twig', [
            'missions' => $missions,
            'navigation' => $navigation,
            'search' => $search,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    private function parseDate(string $dateString): ?\DateTime
    {
        $formats = ['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date && $date->format($format) === $dateString) {
                return $date;
            }
        }
        return null;
    }

    #[Route('/aidesoingnant/missions/accept/{id}', name: 'aidesoingnant_missions_accept')]
    public function acceptMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        // Get current aide-soignant from UserService
        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to accept missions');
        }

        // Find the existing Mission for this demande
        $missions = $demande->getMissions();
        if ($missions->isEmpty()) {
            throw $this->createNotFoundException('Mission not found for this demande');
        }

        $mission = $missions->first();

        // Update the existing Mission
        $mission->setAideSoignant($aideSoignant);
        $mission->setStatutMission('ACCEPTÉE');
        $mission->setPrixFinal(0); // To be negotiated later

        $demande->setStatut('ACCEPTÉE');

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

        // Verify user is aide soignant
        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to refuse missions');
        }

        // Find the existing Mission for this demande
        $missions = $demande->getMissions();
        if ($missions->isEmpty()) {
            throw $this->createNotFoundException('Mission not found for this demande');
        }

        $mission = $missions->first();

        // Update the existing Mission
        $mission->setStatutMission('REFUSÉE');

        $demande->setStatut('REFUSÉE');

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
