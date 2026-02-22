<?php

namespace App\Controller;

use App\Entity\DemandeAide;
use App\Entity\Mission;
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
    public function list(DemandeAideRepository $demandeAideRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
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
                
                // Sort by dateCreation desc
                usort($demandesAide, fn($a, $b) => $b->getDateCreation() <=> $a->getDateCreation());
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
        
        return $this->render('demande_aide/index.html.twig', [
            'demandesAide' => $demandesAide,
            'navigation' => $navigation,
        ]);
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
                
                // Enregistrer en base de données
                $entityManager->persist($demandeAide);
                $entityManager->flush();
                
                // Create a Mission for aide soignants
                $mission = new Mission();
                $mission->setDemandeAide($demandeAide);
                if (!empty($demandeAideData['dateDebutSouhaitee'])) {
                    $mission->setDateDebut(new \DateTime($demandeAideData['dateDebutSouhaitee']));
                }
                if (!empty($demandeAideData['dateFinSouhaitee'])) {
                    $mission->setDateFin(new \DateTime($demandeAideData['dateFinSouhaitee']));
                }
                $mission->setStatutMission('EN_ATTENTE');
                $mission->setPrixFinal(0); // Default price, will be proposed by aide soignant
                
                $entityManager->persist($mission);
                $entityManager->flush();
                
                $this->addFlash('success', 'Votre demande d\'aide a été enregistrée avec succès !');
                return $this->redirectToRoute('app_demandes_index');
                
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
    public function show(DemandeAide $demandeAide): Response
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
        
        return $this->render('demande_aide/show.html.twig', [
            'demande' => $demandeAide,
            'navigation' => $navigation,
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

    #[Route('/demande/{id}/delete', name: 'app_demande_aide_delete', methods: ['POST'])]
    public function delete(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($demandeAide);
        $entityManager->flush();
        
        $this->addFlash('success', 'La demande a été supprimée avec succès !');
        return $this->redirectToRoute('app_demandes_index');
    }
}

