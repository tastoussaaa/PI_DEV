<?php

namespace App\Controller;

use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Entity\AideSoignant;
use App\Form\DemandeAideType;
use App\Repository\DemandeAideRepository;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DemandeAideController extends BaseController
{
    public function __construct(UserService $userService)
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
                    return $de !== '' && strcasecmp($de, $email) === 0;
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
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
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
    public function show(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
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
        
        return $this->render('demande_aide/show.html.twig', [
            'demande' => $demandeAide,
            'navigation' => $navigation,
            'aidesSoignantsCompatibles' => $aidesSoignantsCompatibles,
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
        
        // Vérifier que la demande est EN_ATTENTE
        if ($demandeAide->getStatut() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Vous ne pouvez modifier que les demandes en attente.');
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

        return $this->render('demande_aide/select_aide.html.twig', [
            'demande' => $demandeAide,
            'aidesSoignantsCompatibles' => $aidesSoignantsCompatibles,
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

        // Récupérer ou créer la mission associée
        $missions = $demandeAide->getMissions();
        $mission = null;
        
        if ($missions->count() > 0) {
            // La mission existe déjà (créée lors de la demande)
            $mission = $missions->first();
        } else {
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

        // Assigner l'aide-soignant à la mission
        $mission->setAideSoignant($aideSoignant);

        $entityManager->flush();

        $this->addFlash('success', 'Vous avez sélectionné ' . $aideSoignant->getNom() . ' comme aide-soignant !');
        return $this->redirectToRoute('app_demande_aide_show', ['id' => $demandeAide->getId()]);
    }

    #[Route('/demande/{id}/delete', name: 'app_demande_aide_delete', methods: ['POST'])]
    public function delete(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($demandeAide);
        $entityManager->flush();
        
        $this->addFlash('success', 'La demande a été supprimée avec succès !');
        return $this->redirectToRoute('app_demandes_index');
    }
}

