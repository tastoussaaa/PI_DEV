<?php

namespace App\Controller;

use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Entity\AideSoignant;
use App\Form\DemandeAideType;
use App\Repository\DemandeAideRepository;
use App\Service\TransitionNotificationService;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DemandeAideController extends BaseController
{
    public function __construct(
        UserService $userService,
        private TransitionNotificationService $transitionNotificationService,
    )
    {
        parent::__construct($userService);
    }

    #[Route('/demandes', name: 'app_demandes_index', methods: ['GET'])]
    public function list(Request $request, DemandeAideRepository $demandeAideRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Initialize variables
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'dateCreation');
        $sortOrder = $request->query->get('sort_order', 'desc');

        $user = $this->getUser();
        $demandesAide = [];

        // Get only this patient's demandes
        if ($user) {
            try {
                $email = $user->getEmail();
                $allDemandes = $demandeAideRepository->findAll();
                $demandesAide = array_filter($allDemandes, function($d) use ($email) {
                    $de = strtolower((string) $d->getEmail());
                    $isUserDemande = $de !== '' && strcasecmp($de, $email) === 0;
                    // Exclude archived demandes from active flow
                    $isNotArchived = !in_array($d->getStatut(), ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'], true);
                    return $isUserDemande && $isNotArchived;
                });

                // Apply search filter with validation based on sort_by
                if (!empty($search)) {
                    $demandesAide = array_filter($demandesAide, function($d) use ($search, $sortBy) {
                        $searchLower = strtolower($search);

                        switch ($sortBy) {
                            case 'dateCreation':
                                // Validate date format (YYYY-MM-DD or DD/MM/YYYY)
                                if (!preg_match('/^\d{4}-\d{2}-\d{2}$|^\d{2}\/\d{2}\/\d{4}$/', $search)) {
                                    return false;
                                }
                                // For date search, we search in date fields
                                $dateStr = $d->getDateCreation() ? $d->getDateCreation()->format('Y-m-d') : '';
                                return stripos($dateStr, $search) !== false;

                            case 'budgetMax':
                                // Validate integer
                                if (!is_numeric($search) || intval($search) != $search) {
                                    return false;
                                }
                                return $d->getBudgetMax() == intval($search);

                            case 'typeDemande':
                                return stripos($d->getTypeDemande(), $searchLower) !== false;

                            case 'statut':
                                return stripos($d->getStatut(), $searchLower) !== false;

                            default:
                                // General search across multiple fields
                                return stripos($d->getDescriptionBesoin(), $searchLower) !== false ||
                                       stripos($d->getTypeDemande(), $searchLower) !== false ||
                                       stripos($d->getTypePatient(), $searchLower) !== false ||
                                       stripos($d->getStatut(), $searchLower) !== false;
                        }
                    });
                }

                // Apply sorting
                usort($demandesAide, function($a, $b) use ($sortBy, $sortOrder) {
                    $valueA = $this->getSortValue($a, $sortBy);
                    $valueB = $this->getSortValue($b, $sortBy);

                    if ($sortOrder === 'asc') {
                        return $valueA <=> $valueB;
                    } else {
                        return $valueB <=> $valueA;
                    }
                });
            } catch (\Exception $e) {
                // User filtering error, skip
            }
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;
        $totalDemandes = count($demandesAide);
        $totalPages = ceil($totalDemandes / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedDemandes = array_slice($demandesAide, $offset, $perPage);

        // Search history
        $session = $request->getSession();
        $searchHistory = $session->get('search_history', []);
        if (!empty($search) && !in_array($search, $searchHistory)) {
            array_unshift($searchHistory, $search);
            $searchHistory = array_slice($searchHistory, 0, 5); // Keep last 5
            $session->set('search_history', $searchHistory);
        }

        // Handle AJAX request
        if ($request->isXmlHttpRequest()) {
            return $this->render('demande_aide/_demandes_list.html.twig', [
                'demandesAide' => $paginatedDemandes,
                'search' => $search,
            ]);
        }

        return $this->render('demande_aide/index.html.twig', [
            'demandesAide' => $paginatedDemandes,
            'navigation' => $navigation,
            'search' => $search,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'search_history' => $searchHistory,
        ]);
    }

    private function getSortValue($demande, $sortBy)
    {
        switch ($sortBy) {
            case 'dateCreation':
                return $demande->getDateCreation() ? $demande->getDateCreation()->getTimestamp() : 0;
            case 'typeDemande':
                return $demande->getTypeDemande();
            case 'statut':
                return $demande->getStatut();
            case 'budgetMax':
                return $demande->getBudgetMax() ?? 0;
            default:
                return $demande->getDateCreation() ? $demande->getDateCreation()->getTimestamp() : 0;
        }
    }

    #[Route('/demande/aide', name: 'app_demande_aide', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, \App\Service\UrgencyCalculator $urgencyCalculator): Response
    {
        $demandeAide = new DemandeAide();
        
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];
        
        if ($request->isMethod('POST')) {
            try {
                // Récupérer les données brutes du formulaire
                $data = $request->request->all();
                $demandeAideData = $data['demande_aide'] ?? [];
                
                // Vérifier les coordonnées
                if (empty($demandeAideData['latitude']) || empty($demandeAideData['longitude'])) {
                    $this->addFlash('error', 'Veuillez sélectionner une localisation sur la carte !');
                    return $this->render('demande_aide/demandeForm.html.twig', [
                        'navigation' => $navigation,
                    ]);
                }
                
                // Remplir les données du formulaire
                $demandeAide->setTitreD($demandeAideData['TitreD'] ?? '');
                $demandeAide->setTypeDemande($demandeAideData['typeDemande'] ?? null);
                $demandeAide->setDescriptionBesoin($demandeAideData['descriptionBesoin'] ?? null);
                $demandeAide->setTypePatient($demandeAideData['typePatient'] ?? null);
                $demandeAide->setSexe($demandeAideData['sexe'] ?? null);
                $demandeAide->setBesoinCertifie(isset($demandeAideData['besoinCertifie']) ? true : false);
                $demandeAide->setLieu($demandeAideData['lieu'] ?? null);
                
                // Dates
                if (!empty($demandeAideData['dateDebutSouhaitee'])) {
                    $demandeAide->setDateDebutSouhaitee(new \DateTime($demandeAideData['dateDebutSouhaitee']));
                }
                if (!empty($demandeAideData['dateFinSouhaitee'])) {
                    $demandeAide->setDateFinSouhaitee(new \DateTime($demandeAideData['dateFinSouhaitee']));
                }
                
                // Budget
                if (!empty($demandeAideData['budgetMax'])) {
                    $demandeAide->setBudgetMax((int)$demandeAideData['budgetMax']);
                }
                
                // Coordonnées et métadonnées
                $demandeAide->setLatitude((float)$demandeAideData['latitude']);
                $demandeAide->setLongitude((float)$demandeAideData['longitude']);
                $demandeAide->setDateCreation(new \DateTime());
                $demandeAide->setStatut('EN_ATTENTE');
                $demandeAide->setEmail($this->getUser()->getEmail());

                // Calculate urgency score using AI-powered service
                $urgencyScore = $urgencyCalculator->calculateUrgencyScore($demandeAide);
                $demandeAide->setUrgencyScore($urgencyScore);
                
                // Valider l'entité
                $errors = $validator->validate($demandeAide);
                
                if (count($errors) > 0) {
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                    }
                    $this->addFlash('error', 'Validation échouée: ' . implode(', ', $errorMessages));
                    return $this->render('demande_aide/demandeForm.html.twig', [
                        'navigation' => $navigation,
                    ]);
                }
                
                // Enregistrer d'abord la demande d'aide
                $entityManager->persist($demandeAide);
                $entityManager->flush();

                // Créer automatiquement une mission pour cette demande
                $mission = new Mission();
                $mission->setDemandeAide($demandeAide);
                $mission->setTitreM($demandeAide->getTitreD());
                $mission->setStatutMission('EN_ATTENTE');
                $mission->setPrixFinal(0);
                $mission->setNote(null);
                $mission->setCommentaire(null);
                $mission->setDateDebut($demandeAide->getDateDebutSouhaitee());
                $mission->setDateFin($demandeAide->getDateFinSouhaitee());

                // Enregistrer la mission
                $entityManager->persist($mission);
                $entityManager->flush();

                $this->addFlash('success', 'Votre demande d\'aide a été enregistrée avec succès !');
                return $this->redirectToRoute('app_demande_select_aide', ['id' => $demandeAide->getId()]);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed: ' . $e->getMessage());
                return $this->render('demande_aide/demandeForm.html.twig', [
                    'navigation' => $navigation,
                ]);
            }
        }

        return $this->render('demande_aide/demandeForm.html.twig', [
            'navigation' => $navigation,
        ]);
    }

    #[Route('/demande/{id}', name: 'app_demande_aide_show', methods: ['GET'])]
    public function show(DemandeAide $demandeAide, EntityManagerInterface $entityManager, \App\Service\ReportAssistant $reportAssistant, \App\Service\CalendarBlockingService $calendarBlocker): Response
    {
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];

        $qb = $entityManager->getRepository(AideSoignant::class)->createQueryBuilder('a')
            ->andWhere('a.isValidated = :validated')
            ->setParameter('validated', true);

        $demandeSexe = $demandeAide->getSexe();
        if ($demandeSexe === 'M') {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['HOMME', 'M']);
        } elseif ($demandeSexe === 'F') {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['FEMME', 'F']);
        } else {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['HOMME', 'FEMME', 'M', 'F']);
        }

        $aidesSoignantsCompatibles = $qb
            ->orderBy('a.disponible', 'DESC')
            ->addOrderBy('a.niveauExperience', 'DESC')
            ->getQuery()
            ->getResult();

        // **SECTION 3: Filtrer les aides basé sur le calendrier (CalendarBlockingService)**
        $startDate = $demandeAide->getDateDebutSouhaitee();
        $endDate = $demandeAide->getDateFinSouhaitee();
        $aidesSoignantsCompatibles = $calendarBlocker->filterAvailableAides($aidesSoignantsCompatibles, $startDate, $endDate);

        $aidesRanking = $this->buildAidesRanking($aidesSoignantsCompatibles, $demandeAide, $entityManager);
        usort($aidesSoignantsCompatibles, function($a, $b) use ($aidesRanking) {
            $scoreA = $aidesRanking[$a->getId()]['score'] ?? 0;
            $scoreB = $aidesRanking[$b->getId()]['score'] ?? 0;
            return $scoreB <=> $scoreA;
        });

        // Generate AI-powered report
        $report = $reportAssistant->generateReport($demandeAide);
        
        return $this->render('demande_aide/show.html.twig', [
            'demande' => $demandeAide,
            'navigation' => $navigation,
            'aidesSoignantsCompatibles' => $aidesSoignantsCompatibles,
            'aidesRanking' => $aidesRanking,
            'aiReport' => $report,
            'reportAssistant' => $reportAssistant,
        ]);
    }

    #[Route('/demande/{id}/edit', name: 'app_demande_aide_edit', methods: ['GET', 'POST'])]
    public function edit(DemandeAide $demandeAide, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];
        
        // Vérifier que la demande est modifiable
        if (!in_array($demandeAide->getStatut(), ['EN_ATTENTE', 'A_REASSIGNER'], true)) {
            $this->addFlash('error', 'Vous ne pouvez modifier que les demandes en attente ou à réassigner.');
            return $this->redirectToRoute('app_demandes_index');
        }

        if ($request->isMethod('POST')) {
            try {
                // Récupérer les données brutes du formulaire
                $data = $request->request->all();
                $demandeAideData = $data['demande_aide'] ?? [];
                
                // Vérifier les coordonnées
                if (empty($demandeAideData['latitude']) || empty($demandeAideData['longitude'])) {
                    $this->addFlash('error', 'Veuillez sélectionner une localisation sur la carte !');
                    return $this->render('demande_aide/edit.html.twig', [
                        'demande' => $demandeAide,
                        'navigation' => $navigation,
                    ]);
                }
                
                // Mettre à jour les données
                $demandeAide->setTitreD($demandeAideData['TitreD'] ?? '');
                $demandeAide->setTypeDemande($demandeAideData['typeDemande'] ?? null);
                $demandeAide->setDescriptionBesoin($demandeAideData['descriptionBesoin'] ?? null);
                $demandeAide->setTypePatient($demandeAideData['typePatient'] ?? null);
                $demandeAide->setSexe($demandeAideData['sexe'] ?? null);
                $demandeAide->setBesoinCertifie(isset($demandeAideData['besoinCertifie']) ? true : false);
                $demandeAide->setLieu($demandeAideData['lieu'] ?? null);
                
                // Dates
                if (!empty($demandeAideData['dateDebutSouhaitee'])) {
                    $demandeAide->setDateDebutSouhaitee(new \DateTime($demandeAideData['dateDebutSouhaitee']));
                }
                if (!empty($demandeAideData['dateFinSouhaitee'])) {
                    $demandeAide->setDateFinSouhaitee(new \DateTime($demandeAideData['dateFinSouhaitee']));
                }
                
                // Budget
                if (!empty($demandeAideData['budgetMax'])) {
                    $demandeAide->setBudgetMax((int)$demandeAideData['budgetMax']);
                }
                
                // Coordonnées
                $demandeAide->setLatitude((float)$demandeAideData['latitude']);
                $demandeAide->setLongitude((float)$demandeAideData['longitude']);
                
                // Valider l'entité
                $errors = $validator->validate($demandeAide);
                
                if (count($errors) > 0) {
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                    }
                    $this->addFlash('error', 'Validation échouée: ' . implode(', ', $errorMessages));
                    return $this->render('demande_aide/edit.html.twig', [
                        'demande' => $demandeAide,
                        'navigation' => $navigation,
                    ]);
                }
                
                // Enregistrer les modifications
                $entityManager->flush();
                
                $this->addFlash('success', 'Votre demande a été modifiée avec succès !');
                return $this->redirectToRoute('app_demandes_index');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed: ' . $e->getMessage());
                return $this->render('demande_aide/edit.html.twig', [
                    'demande' => $demandeAide,
                    'navigation' => $navigation,
                ]);
            }
        }

        return $this->render('demande_aide/edit.html.twig', [
            'demande' => $demandeAide,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/demande/{id}/select-aide', name: 'app_demande_select_aide', methods: ['GET'])]
    public function selectAide(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
    {
        // Si l'aide a déjà été choisi, rediriger vers la demande
        if ($demandeAide->getAideChoisie() !== null) {
            return $this->redirectToRoute('app_demande_aide_show', ['id' => $demandeAide->getId()]);
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
        ];

        // Récupérer les aides-soignants compatibles
        $qb = $entityManager->getRepository(AideSoignant::class)->createQueryBuilder('a')
            ->andWhere('a.isValidated = :validated')
            ->setParameter('validated', true);

        $demandeSexe = $demandeAide->getSexe();
        if ($demandeSexe === 'M') {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['HOMME']);
        } elseif ($demandeSexe === 'F') {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['FEMME']);
        } else {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['HOMME', 'FEMME']);
        }

        $aidesSoignantsCompatibles = $qb
            ->orderBy('a.disponible', 'DESC')
            ->addOrderBy('a.niveauExperience', 'DESC')
            ->getQuery()
            ->getResult();

        $aidesRanking = $this->buildAidesRanking($aidesSoignantsCompatibles, $demandeAide, $entityManager);
        $aidesSoignantsCompatibles = array_values(array_filter($aidesSoignantsCompatibles, function($aide) use ($aidesRanking) {
            return (bool) ($aidesRanking[$aide->getId()]['available'] ?? false);
        }));

        usort($aidesSoignantsCompatibles, function($a, $b) use ($aidesRanking) {
            $scoreA = $aidesRanking[$a->getId()]['score'] ?? 0;
            $scoreB = $aidesRanking[$b->getId()]['score'] ?? 0;
            return $scoreB <=> $scoreA;
        });

        return $this->render('demande_aide/select_aide.html.twig', [
            'demande' => $demandeAide,
            'aidesSoignantsCompatibles' => $aidesSoignantsCompatibles,
            'aidesRanking' => $aidesRanking,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/demande/{id}/select-aide/{aideId}', name: 'app_demande_select_aide_post', methods: ['POST'])]
    public function selectAidePost(DemandeAide $demandeAide, int $aideId, EntityManagerInterface $entityManager): Response
    {
        // Récupérer l'aide-soignant choisi
        $aideSoignant = $entityManager->getRepository(AideSoignant::class)->find($aideId);
        if (!$aideSoignant) {
            $this->addFlash('error', 'Aide-soignant non trouvé.');
            return $this->redirectToRoute('app_demande_select_aide', ['id' => $demandeAide->getId()]);
        }

        // Binding de l'aide-soignant à la demande
        $demandeAide->setAideChoisie($aideSoignant);
        $demandeAide->setStatut('EN_ATTENTE');

        // Récupérer ou créer la mission active associée
        $missions = $demandeAide->getMissions();
        $mission = null;

        foreach ($missions as $existingMission) {
            if (!$existingMission->getFinalStatus()) {
                $mission = $existingMission;
                break;
            }
        }

        if (!$mission) {
            // Créer une nouvelle mission
            $mission = new Mission();
            $mission->setDemandeAide($demandeAide);
            $mission->setTitreM($demandeAide->getTitreD());
            $mission->setStatutMission('EN_ATTENTE');
            $mission->setPrixFinal(0);
            $mission->setDateDebut($demandeAide->getDateDebutSouhaitee());
            $mission->setDateFin($demandeAide->getDateFinSouhaitee());
            $entityManager->persist($mission);
        }

        if (!$this->isAideAvailableForDemande($aideSoignant, $demandeAide, $entityManager, $mission->getId())) {
            $this->addFlash('error', 'Cet aide-soignant n\'est plus disponible sur ce créneau. Veuillez en choisir un autre.');
            return $this->redirectToRoute('app_demande_select_aide', ['id' => $demandeAide->getId()]);
        }

        // Assigner l'aide-soignant à la mission
        $mission->setAideSoignant($aideSoignant);

        $entityManager->flush();

        $this->transitionNotificationService->notifyCriticalTransition('DEMANDE_ASSIGNED', $demandeAide, $mission, $aideSoignant);

        $this->addFlash('success', 'Vous avez sélectionné ' . $aideSoignant->getNom() . ' comme aide-soignant !');
        return $this->redirectToRoute('app_demande_aide_show', ['id' => $demandeAide->getId()]);
    }

    #[Route('/demande/{id}/delete', name: 'app_demande_aide_delete', methods: ['POST'])]
    public function delete(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
    {
        try {
            // Physically delete all associated missions
            foreach ($demandeAide->getMissions() as $mission) {
                $entityManager->remove($mission);
            }

            // Physically delete the demande itself
            $entityManager->remove($demandeAide);
            $entityManager->flush();
            
            $this->addFlash('success', 'La demande a été supprimée avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_demandes_index');
    }

    #[Route('/aidesoingnant/missions/accept/{id}', name: 'aidesoingnant_accept_mission')]
    public function acceptMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to accept missions');
        }

        $missions = $demande->getMissions();
        $mission = null;
        foreach ($missions as $existingMission) {
            if (!$existingMission->getFinalStatus()) {
                $mission = $existingMission;
                break;
            }
        }

        if (!$mission) {
            // Si pas de mission trouvée, en créer une (defensif: au cas où elle aurait été supprimée)
            $mission = new Mission();
            $mission->setDemandeAide($demande);
            $mission->setTitreM($demande->getTitreD());
            $mission->setStatutMission('EN_ATTENTE');
            $mission->setPrixFinal(0);
            $mission->setDateDebut($demande->getDateDebutSouhaitee());
            $mission->setDateFin($demande->getDateFinSouhaitee());
            $entityManager->persist($mission);
        }

        if (!$this->isAideAvailableForDemande($aideSoignant, $demande, $entityManager, $mission->getId())) {
            $this->addFlash('error', 'Vous avez déjà une mission sur ce créneau.');
            return $this->redirectToRoute('aidesoingnant_demandes');
        }

        $mission->setAideSoignant($aideSoignant);
        $mission->setTitreM($demande->getTitreD());
        $mission->setStatutMission('ACCEPTÉE');
        $mission->setPrixFinal(0);

        $demande->setStatut('ACCEPTÉE');

        $entityManager->flush();

        $this->transitionNotificationService->notifyCriticalTransition('MISSION_ACCEPTED', $demande, $mission, $aideSoignant);

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

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to refuse missions');
        }

        // Réassignation: la demande reste vivante pour un nouveau choix patient
        $demande->setStatut('A_REASSIGNER');
        $demande->setAideChoisie(null);

        $missions = $demande->getMissions();
        foreach ($missions as $mission) {
            if (!$mission->getFinalStatus()) {
                $mission->setAideSoignant(null);
                $mission->setStatutMission('EN_ATTENTE');
                $mission->setPrixFinal(0);
            }
        }

        $entityManager->flush();

        $this->transitionNotificationService->notifyCriticalTransition('DEMANDE_REASSIGNED', $demande, null, $aideSoignant);

        $this->addFlash('success', 'Demande refusée. La demande est marquée à réassigner côté patient.');
        return $this->redirectToRoute('aidesoingnant_demandes');
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

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to view demandes');
        }

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

        $demandes = [];
        foreach ($demandesData as $demande) {
            $missions = $demande->getMissions();
            $activeMission = null;
            foreach ($missions as $candidateMission) {
                if (!$candidateMission->getFinalStatus() && $candidateMission->getStatutMission() === 'EN_ATTENTE') {
                    $activeMission = $candidateMission;
                    break;
                }
            }

            if ($activeMission) {
                $demandes[] = $activeMission;
            } else {
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

    #[Route('/aidesoingnant/demande/details/{id}', name: 'aidesoingnant_demande_details')]
    public function showDemandeDetails(int $id, DemandeAideRepository $demandeAideRepository): Response
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

    #[Route('/demandes/historique', name: 'app_demandes_history')]
    public function history(Request $request, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $email = $user ? $user->getEmail() : '';

        if (!$email) {
            throw $this->createAccessDeniedException('User not found');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        // Récupérer toutes les demandes d'aide archivées de ce patient
        $allDemandes = $demandeAideRepository->findAll();
        $userDemandes = array_filter($allDemandes, function($d) use ($email) {
            $de = strtolower((string) $d->getEmail());
            return $de !== '' && strcasecmp($de, $email) === 0;
        });

        // Filtrer par statuts archivés
        $archivedDemandes = array_filter($userDemandes, function($d) {
            return in_array($d->getStatut(), ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'], true);
        });

        // Trier par date décroissante
        usort($archivedDemandes, function($a, $b) {
            return $b->getDateCreation()->getTimestamp() - $a->getDateCreation()->getTimestamp();
        });

        $totalCount = count($archivedDemandes);
        $totalPages = ceil($totalCount / $limit);

        $demandes = array_slice($archivedDemandes, ($page - 1) * $limit, $limit);

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('consultation_index'), 'icon' => '👨‍⚕️'],
            ['name' => 'Historique', 'path' => $this->generateUrl('app_demandes_history'), 'icon' => '📚'],
        ];

        return $this->render('demande_aide/history.html.twig', [
            'demandes' => $demandes,
            'navigation' => $navigation,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/demandes/historique/delete/{id}', name: 'app_demande_aide_history_delete', methods: ['POST'])]
    public function deleteFromHistory(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $email = $user ? $user->getEmail() : '';
        if (!$email || strcasecmp((string) $demandeAide->getEmail(), (string) $email) !== 0) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette demande.');
            return $this->redirectToRoute('app_demandes_history');
        }

        if (!in_array($demandeAide->getStatut(), ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'], true)) {
            $this->addFlash('error', 'Seules les demandes archivées peuvent être supprimées définitivement.');
            return $this->redirectToRoute('app_demandes_history');
        }

        try {
            foreach ($demandeAide->getMissions() as $mission) {
                $entityManager->remove($mission);
            }

            $entityManager->remove($demandeAide);
            $entityManager->flush();

            $this->addFlash('success', 'Demande supprimée définitivement de l\'historique.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_demandes_history');
    }

    private function buildAidesRanking(array $aides, DemandeAide $demande, EntityManagerInterface $entityManager): array
    {
        $ranking = [];
        foreach ($aides as $aide) {
            $available = $this->isAideAvailableForDemande($aide, $demande, $entityManager);
            $score = $this->computeCompatibilityScore($aide, $demande, $entityManager, $available);

            $ranking[$aide->getId()] = [
                'available' => $available,
                'score' => $score,
            ];
        }

        return $ranking;
    }

    private function isAideAvailableForDemande(AideSoignant $aide, DemandeAide $demande, EntityManagerInterface $entityManager, ?int $ignoreMissionId = null): bool
    {
        $start = $demande->getDateDebutSouhaitee();
        $end = $demande->getDateFinSouhaitee() ?? $start;

        if (!$start || !$end || !$aide->isDisponible()) {
            return false;
        }

        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->andWhere('m.aideSoignant = :aide')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('m.StatutMission IN (:statuses)')
            ->setParameter('aide', $aide)
            ->setParameter('statuses', ['EN_ATTENTE', 'ACCEPTÉE'])
            ->andWhere('m.dateDebut <= :end')
            ->andWhere('m.dateFin >= :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($ignoreMissionId !== null) {
            $qb->andWhere('m.id != :ignoreMissionId')->setParameter('ignoreMissionId', $ignoreMissionId);
        }

        return count($qb->getQuery()->getResult()) === 0;
    }

    private function computeCompatibilityScore(AideSoignant $aide, DemandeAide $demande, EntityManagerInterface $entityManager, bool $available): int
    {
        $score = 0;

        if ($available) {
            $score += 30;
        }

        $score += min(20, (int) ($aide->getNiveauExperience() ?? 0) * 2);

        $tarifMin = (float) ($aide->getTarifMin() ?? 0);
        $budget = (float) ($demande->getBudgetMax() ?? 0);
        if ($budget > 0 && $tarifMin > 0) {
            $ratio = $tarifMin <= $budget ? 1.0 : max(0.0, 1 - (($tarifMin - $budget) / $budget));
            $score += (int) round(20 * $ratio);
        }

        $typesAcceptes = strtoupper((string) $aide->getTypePatientsAcceptes());
        $typePatient = strtoupper((string) $demande->getTypePatient());
        if ($typePatient !== '' && str_contains($typesAcceptes, $typePatient)) {
            $score += 20;
        }

        $urgency = (int) ($demande->getUrgencyScore() ?? 0);
        $score += (int) round(min(10, $urgency / 10));

        $missions = $entityManager->getRepository(Mission::class)->findBy(['aideSoignant' => $aide]);
        if (count($missions) > 0) {
            $completed = 0;
            $failed = 0;
            foreach ($missions as $mission) {
                if ($mission->getFinalStatus() === 'TERMINÉE') {
                    $completed++;
                }
                if (in_array($mission->getFinalStatus(), ['ANNULÉE', 'EXPIRÉE'])) {
                    $failed++;
                }
            }
            $score += max(0, min(20, ($completed * 2) - $failed));
        }

        return max(0, min(100, $score));
    }
}

