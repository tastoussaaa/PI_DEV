<?php

namespace App\Controller;

use App\Entity\DemandeAide;
use App\Form\DemandeAideType;
use App\Repository\DemandeAideRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DemandeAideController extends AbstractController
{
    #[Route('/demandes', name: 'app_demandes_index', methods: ['GET'])]
    public function list(DemandeAideRepository $demandeAideRepository): Response
    {
        $demandesAide = $demandeAideRepository->findAll();
        
        return $this->render('demande_aide/index.html.twig', [
            'demandesAide' => $demandesAide,
        ]);
    }

    #[Route('/demande/aide', name: 'app_demande_aide', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $demandeAide = new DemandeAide();
        
        if ($request->isMethod('POST')) {
            try {
                // Récupérer les données brutes du formulaire
                $data = $request->request->all();
                $demandeAideData = $data['demande_aide'] ?? [];
                
                // Vérifier les coordonnées
                if (empty($demandeAideData['latitude']) || empty($demandeAideData['longitude'])) {
                    $this->addFlash('error', 'Veuillez sélectionner une localisation sur la carte !');
                    return $this->render('demande_aide/demandeForm.html.twig');
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
                
                // Valider l'entité
                $errors = $validator->validate($demandeAide);
                
                if (count($errors) > 0) {
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                    }
                    $this->addFlash('error', 'Validation échouée: ' . implode(', ', $errorMessages));
                    return $this->render('demande_aide/demandeForm.html.twig');
                }
                
                // Enregistrer en base de données
                $entityManager->persist($demandeAide);
                $entityManager->flush();
                
                $this->addFlash('success', 'Votre demande d\'aide a été enregistrée avec succès !');
                return $this->redirectToRoute('app_demandes_index');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed: ' . $e->getMessage());
                return $this->render('demande_aide/demandeForm.html.twig');
            }
        }

        return $this->render('demande_aide/demandeForm.html.twig');
    }

    #[Route('/demande/{id}', name: 'app_demande_aide_show', methods: ['GET'])]
    public function show(DemandeAide $demandeAide): Response
    {
        return $this->render('demande_aide/show.html.twig', [
            'demande' => $demandeAide,
        ]);
    }

    #[Route('/demande/{id}/edit', name: 'app_demande_aide_edit', methods: ['GET', 'POST'])]
    public function edit(DemandeAide $demandeAide, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
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
                ]);
            }
        }

        return $this->render('demande_aide/edit.html.twig', [
            'demande' => $demandeAide,
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

