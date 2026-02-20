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
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('aide_soingnant/aideSoignantDashboard.html.twig', [
            'navigation' => $navigation,
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

        // Get current aide-soignant
        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to view missions');
        }

        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->leftJoin('m.demandeAide', 'd')
            ->select('m, d')
            ->andWhere('m.StatutMission = :status')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('m.aideSoignant = :aideSoignant')
            ->setParameter('status', 'ACCEPTÉE')
            ->setParameter('aideSoignant', $aideSoignant);

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

        return $this->render('mission/missions_list.html.twig', [
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

        // Get current aide-soignant
        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to view demandes');
        }

        // Convert aide-soignant sexe to demande sexe format
        // HOMME -> M, FEMME -> F
        $aideSexeMapped = ($aideSoignant->getSexe() === 'HOMME') ? 'M' : 'F';

        // Get demandes with status EN_ATTENTE (via missions)
        // Filter: aideChoisie = current, statut = EN_ATTENTE, date not passed, not refused/expired/cancelled
        $now = new \DateTime();
        $qb = $entityManager->getRepository(DemandeAide::class)->createQueryBuilder('d')
            ->leftJoin('d.missions', 'm')
            ->select('d')
            ->distinct()
            ->andWhere('d.aideChoisie = :aideSoignant')
            ->setParameter('aideSoignant', $aideSoignant)
            ->andWhere('m.StatutMission = :status')
            ->setParameter('status', 'EN_ATTENTE')
            ->andWhere('d.statut = :demandeStatus')
            ->setParameter('demandeStatus', 'EN_ATTENTE')
            ->andWhere('d.dateDebutSouhaitee > :now')
            ->setParameter('now', $now);

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

        return $this->render('demande_aide/demandes_list.html.twig', [
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

    #[Route('/aidesoingnant/demande/details/{id}', name: 'aidesoingnant_demande_details')]
    public function showDemandeDetails(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable');
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('demande_aide/demande_details.html.twig', [
            'demande' => $demande,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/aidesoingnant/missions/details/{id}', name: 'aidesoingnant_missions_details')]
    public function showMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $mission = null;
        $currentAide = $this->getCurrentAideSoignant();
        if ($currentAide) {
            $mission = $entityManager->getRepository(Mission::class)->findOneBy([
                'demandeAide' => $demande,
                'aideSoignant' => $currentAide,
            ]);
        }

        if (!$mission) {
            $mission = $entityManager->getRepository(Mission::class)->findOneBy([
                'demandeAide' => $demande,
            ]);
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('mission/show.html.twig', [
            'demande' => $demande,
            'mission' => $mission,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/mission/{id}/checkin', name: 'mission_checkin', methods: ['POST'])]
    public function checkInMission(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $mission = $entityManager->getRepository(Mission::class)->find($id);
        if (!$mission) {
            return new JsonResponse([
                'success' => false,
                'code' => 'MISSION_NOT_FOUND',
                'message' => 'Mission introuvable.',
            ], 404);
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide) {
            return new JsonResponse([
                'success' => false,
                'code' => 'AIDE_REQUIRED',
                'message' => 'Accès réservé aux aide-soignants.',
            ], 403);
        }

        if (!$mission->getAideSoignant() || $mission->getAideSoignant()->getId() !== $currentAide->getId()) {
            return new JsonResponse([
                'success' => false,
                'code' => 'NOT_OWNER',
                'message' => 'Vous ne pouvez pas faire le check-in de cette mission.',
            ], 403);
        }

        // Vérifier que la mission est acceptée
        if ($mission->getStatutMission() !== 'ACCEPTÉE') {
            return new JsonResponse([
                'success' => false,
                'code' => 'MISSION_NOT_ACCEPTED',
                'message' => 'Le check-in est uniquement disponible pour les missions acceptées.',
            ], 422);
        }

        if ($mission->getCheckInAt() !== null) {
            return new JsonResponse([
                'success' => false,
                'code' => 'ALREADY_CHECKED_IN',
                'message' => 'Le check-in a déjà été effectué.',
            ], 409);
        }

        // Validation temporelle: vérifier que le check-in est fait le bon jour et dans la fenêtre horaire
        $now = new \DateTime();
        $dateDebut = $mission->getDateDebut();
        
        if (!$dateDebut) {
            return new JsonResponse([
                'success' => false,
                'code' => 'NO_START_DATE',
                'message' => 'La date de début de la mission n\'est pas définie.',
            ], 422);
        }

        // Vérifier que c'est le bon jour
        if ($now->format('Y-m-d') !== $dateDebut->format('Y-m-d')) {
            return new JsonResponse([
                'success' => false,
                'code' => 'WRONG_DAY',
                'message' => sprintf(
                    'Le check-in ne peut être fait que le jour de la mission (%s). Aujourd\'hui: %s',
                    $dateDebut->format('d/m/Y'),
                    $now->format('d/m/Y')
                ),
            ], 422);
        }

        // Vérifier que l'heure est dans la fenêtre ±30 minutes
        $heureDebut = clone $dateDebut;
        $heureMin = (clone $heureDebut)->modify('-30 minutes');
        $heureMax = (clone $heureDebut)->modify('+30 minutes');

        if ($now < $heureMin || $now > $heureMax) {
            return new JsonResponse([
                'success' => false,
                'code' => 'OUTSIDE_TIME_WINDOW',
                'message' => sprintf(
                    'Le check-in est autorisé entre %s et %s. Il est actuellement %s.',
                    $heureMin->format('H:i'),
                    $heureMax->format('H:i'),
                    $now->format('H:i')
                ),
            ], 422);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        $consent = (bool) ($payload['consent'] ?? false);

        if (!$consent) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CONSENT_REQUIRED',
                'message' => 'Le consentement de géolocalisation est obligatoire.',
            ], 400);
        }

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_COORDINATES',
                'message' => 'Coordonnées GPS invalides.',
            ], 400);
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return new JsonResponse([
                'success' => false,
                'code' => 'OUT_OF_RANGE_COORDINATES',
                'message' => 'Coordonnées GPS hors limites.',
            ], 400);
        }

        $mission->setLatitudeCheckin($latitude);
        $mission->setLongitudeCheckin($longitude);
        $mission->setCheckInAt(new \DateTime());
        $mission->setStatusVerification('PENDING');

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Check-in enregistré avec succès.',
            'missionId' => $mission->getId(),
            'checkInAt' => $mission->getCheckInAt()?->format(DATE_ATOM),
            'statusVerification' => $mission->getStatusVerification(),
        ]);
    }

    #[Route('/mission/{id}/checkout', name: 'mission_checkout', methods: ['POST'])]
    public function checkOutMission(int $id, Request $request, EntityManagerInterface $entityManager, \App\Service\PDFGenerator $pdfGenerator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $mission = $entityManager->getRepository(Mission::class)->find($id);
        if (!$mission) {
            return new JsonResponse([
                'success' => false,
                'code' => 'MISSION_NOT_FOUND',
                'message' => 'Mission introuvable.',
            ], 404);
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide) {
            return new JsonResponse([
                'success' => false,
                'code' => 'AIDE_REQUIRED',
                'message' => 'Accès réservé aux aide-soignants.',
            ], 403);
        }

        if (!$mission->getAideSoignant() || $mission->getAideSoignant()->getId() !== $currentAide->getId()) {
            return new JsonResponse([
                'success' => false,
                'code' => 'NOT_OWNER',
                'message' => 'Vous ne pouvez pas faire le check-out de cette mission.',
            ], 403);
        }

        // Vérifier que la mission est acceptée
        if ($mission->getStatutMission() !== 'ACCEPTÉE') {
            return new JsonResponse([
                'success' => false,
                'code' => 'MISSION_NOT_ACCEPTED',
                'message' => 'Le check-out est uniquement disponible pour les missions acceptées.',
            ], 422);
        }

        if ($mission->getCheckInAt() === null) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CHECKIN_REQUIRED',
                'message' => 'Impossible de faire le check-out sans check-in.',
            ], 409);
        }

        if ($mission->getCheckOutAt() !== null) {
            return new JsonResponse([
                'success' => false,
                'code' => 'ALREADY_CHECKED_OUT',
                'message' => 'Le check-out a déjà été effectué.',
            ], 409);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        $consent = (bool) ($payload['consent'] ?? false);

        if (!$consent) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CONSENT_REQUIRED',
                'message' => 'Le consentement de géolocalisation est obligatoire.',
            ], 400);
        }

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_COORDINATES',
                'message' => 'Coordonnées GPS invalides.',
            ], 400);
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return new JsonResponse([
                'success' => false,
                'code' => 'OUT_OF_RANGE_COORDINATES',
                'message' => 'Coordonnées GPS hors limites.',
            ], 400);
        }

        $demande = $mission->getDemandeAide();
        if (!$demande || $demande->getLatitude() === null || $demande->getLongitude() === null) {
            return new JsonResponse([
                'success' => false,
                'code' => 'PATIENT_LOCATION_MISSING',
                'message' => 'Localisation patient indisponible pour vérification.',
            ], 422);
        }

        $distanceMeters = $this->haversineDistanceMeters(
            $latitude,
            $longitude,
            $demande->getLatitude(),
            $demande->getLongitude()
        );

        $statusVerification = $distanceMeters < 200 ? 'VALIDEE' : 'SUSPECTE';

        $mission->setLatitudeCheckout($latitude);
        $mission->setLongitudeCheckout($longitude);
        $mission->setCheckOutAt(new \DateTime());
        $mission->setStatusVerification($statusVerification);

        $entityManager->flush();

        // Auto-generate PDF report after successful checkout
        try {
            $pdfPath = $pdfGenerator->generateMissionReport($mission);
            $mission->setPdfFilePath($pdfPath);
            $entityManager->flush();
        } catch (\Exception $e) {
            // Log error but don't fail the checkout
            error_log("PDF generation failed for mission {$mission->getId()}: " . $e->getMessage());
        }

        // AUTO-ARCHIVE: Mark mission as TERMINÉE after successful checkout
        $mission->setFinalStatus('TERMINÉE');
        $mission->setArchivedAt(new \DateTime());
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => $statusVerification === 'VALIDEE'
                ? 'Check-out validé. Mission archivée automatiquement.'
                : 'Check-out enregistré. Mission archivée mais marquée suspecte (distance > 200m).',
            'missionId' => $mission->getId(),
            'checkOutAt' => $mission->getCheckOutAt()?->format(DATE_ATOM),
            'statusVerification' => $statusVerification,
            'distanceMeters' => round($distanceMeters, 2),
            'thresholdMeters' => 200,
        ]);
    }

    private function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    #[Route('/mission/{id}/pdf', name: 'mission_pdf_download', methods: ['GET'])]
    public function downloadMissionPDF(int $id, EntityManagerInterface $entityManager, \App\Service\PDFGenerator $pdfGenerator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $mission = $entityManager->getRepository(Mission::class)->find($id);
        if (!$mission) {
            throw $this->createNotFoundException('Mission introuvable.');
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide) {
            throw $this->createAccessDeniedException('Accès réservé aux aide-soignants.');
        }

        if (!$mission->getAideSoignant() || $mission->getAideSoignant()->getId() !== $currentAide->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas télécharger le PDF de cette mission.');
        }

        $pdfContent = $pdfGenerator->generateMissionReportForDownload($mission);

        $filename = sprintf('mission_%d_rapport.pdf', $mission->getId());

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
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
            $this->addFlash('error', 'Demande non trouvée');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        try {
            // Physically delete all missions in this demande
            $missions = $demande->getMissions();
            foreach ($missions as $mission) {
                $entityManager->remove($mission);
            }

            // Physically delete the demande itself
            $entityManager->remove($demande);
            $entityManager->flush();

            $this->addFlash('success', 'Mission supprimée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('aidesoingnant_missions');
    }

    #[Route('/aidesoingnant/historique', name: 'aidesoingnant_history')]
    public function history(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        // Récupérer toutes les missions TERMINÉES, EXPIRÉES, ANNULÉES de cet aide-soignant
        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->where('m.aideSoignant = :aideSoignant')
            ->setParameter('aideSoignant', $aideSoignant)
            ->andWhere('m.finalStatus IN (:statuses)')
            ->setParameter('statuses', ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'])
            ->orderBy('m.archivedAt', 'DESC');

        $totalCount = count($qb->getQuery()->getResult());
        $totalPages = ceil($totalCount / $limit);

        $missions = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
            ['name' => 'Historique', 'path' => $this->generateUrl('aidesoingnant_history'), 'icon' => '📚'],
        ];

        return $this->render('mission/history.html.twig', [
            'missions' => $missions,
            'navigation' => $navigation,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }
}
