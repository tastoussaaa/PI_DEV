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
use App\Entity\DemandeAide;

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
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];
        
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('aide_soingnant/aideSoignantDashboard.html.twig', [
            'navigation' => $navigation,
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
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
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
    public function missions(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'dateCreation');
        $sortOrder = $request->query->get('sort_order', 'desc');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->leftJoin('m.demandeAide', 'd')
            ->select('m, d')
            ->andWhere('m.StatutMission = :status')
            ->setParameter('status', 'ACCEPTÉE');

        if (!empty($search)) {
            switch ($sortBy) {
                case 'dateCreation':
                    $date = $this->parseDate($search);
                    if ($date) {
                        $qb->andWhere('DATE(d.dateCreation) = :date')
                           ->setParameter('date', $date->format('Y-m-d'));
                    }
                    break;
                case 'budgetMax':
                    if (is_numeric($search)) {
                        $qb->andWhere('d.budgetMax = :budget')
                           ->setParameter('budget', (int) $search);
                    }
                    break;
                case 'typeDemande':
                    $qb->andWhere('d.typeDemande LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
                    break;
                default:
                    $qb->andWhere('d.descriptionBesoin LIKE :search OR d.typeDemande LIKE :search OR m.titreM LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
            }
        }

        $orderDirection = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        switch ($sortBy) {
            case 'dateCreation':
                $qb->orderBy('d.dateCreation', $orderDirection);
                break;
            case 'typeDemande':
                $qb->orderBy('d.typeDemande', $orderDirection);
                break;
            default:
                $qb->orderBy('d.dateCreation', 'DESC');
        }

        $totalCount = (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalCount / $limit);

        $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        $missions = $qb->getQuery()->getResult();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('aide_soingnant/missions.html.twig', [
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

    #[Route('/aidesoingnant/demandes', name: 'aidesoingnant_demandes')]
    public function demandes(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'dateCreation');
        $sortOrder = $request->query->get('sort_order', 'desc');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        // Get demandes with status EN_ATTENTE (via missions) and REFUSÉE
        $qb = $entityManager->getRepository(DemandeAide::class)->createQueryBuilder('d')
            ->leftJoin('d.missions', 'm')
            ->select('d')
            ->distinct()
            ->andWhere('(m.StatutMission = :status OR d.statut = :refused)')
            ->setParameter('status', 'EN_ATTENTE')
            ->setParameter('refused', 'REFUSÉE');

        if (!empty($search)) {
            switch ($sortBy) {
                case 'dateCreation':
                    $date = $this->parseDate($search);
                    if ($date) {
                        $qb->andWhere('DATE(d.dateCreation) = :date')
                           ->setParameter('date', $date->format('Y-m-d'));
                    }
                    break;
                case 'budgetMax':
                    if (is_numeric($search)) {
                        $qb->andWhere('d.budgetMax = :budget')
                           ->setParameter('budget', (int) $search);
                    }
                    break;
                case 'typeDemande':
                    $qb->andWhere('d.typeDemande LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
                    break;
                default:
                    $qb->andWhere('d.descriptionBesoin LIKE :search OR d.typeDemande LIKE :search OR d.titreD LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
            }
        }

        $orderDirection = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        switch ($sortBy) {
            case 'dateCreation':
                $qb->orderBy('d.dateCreation', $orderDirection);
                break;
            case 'typeDemande':
                $qb->orderBy('d.typeDemande', $orderDirection);
                break;
            case 'budgetMax':
                $qb->orderBy('d.budgetMax', $orderDirection);
                break;
            default:
                $qb->orderBy('d.dateCreation', 'DESC');
        }

        $totalCount = (clone $qb)->select('COUNT(DISTINCT d.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalCount / $limit);

        $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        $demandesData = $qb->getQuery()->getResult();

        // Convert to format compatible with template
        $demandes = [];
        foreach ($demandesData as $demande) {
            $missions = $demande->getMissions();
            if (!$missions->isEmpty()) {
                // If has missions, use the first one
                $demandes[] = $missions->first();
            } else {
                // If no missions (refusée), create a wrapper object
                $mission = new Mission();
                $mission->setDemandeAide($demande);
                $demandes[] = $mission;
            }
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('aide_soingnant/demandes.html.twig', [
            'demandes' => $demandes,
            'navigation' => $navigation,
            'search' => $search,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/aidesoingnant/missions/accept/{id}', name: 'aidesoingnant_accept_mission')]
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
        $mission->setTitreM($demande->getTitreD());
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

        // Update the demande status to REFUSÉE
        $demande->setStatut('REFUSÉE');

        // Delete all associated missions
        $missions = $demande->getMissions();
        foreach ($missions as $mission) {
            $entityManager->remove($mission);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Demande d\'aide refusée avec succès.');
        return $this->redirectToRoute('aidesoingnant_demandes');
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
}
