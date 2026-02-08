<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\Formation;
use App\Form\FormationType;
use App\Repository\ConsultationRepository;
use App\Repository\FormationRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MedecinController extends BaseController
{
    public function __construct(UserService $userService)
    {
        parent::__construct($userService);
    }

    #[Route('/medecin/dashboard', name: 'app_medecin_dashboard')]
    public function dashboard(): Response
    {
        // Ensure user is authenticated
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Ensure only medecins can access this dashboard
        if (!$this->isCurrentUserMedecin()) {
            $userType = $this->getCurrentUserType();
            return match ($userType) {
                'patient' => $this->redirectToRoute('app_patient_dashboard'),
                'aidesoignant' => $this->redirectToRoute('app_aide_soignant_dashboard'),
                'admin' => $this->redirectToRoute('app_admin_dashboard'),
                default => $this->redirectToRoute('app_login'),
            };
        }
        
        $medecin = $this->getCurrentMedecin();
        $userId = $this->getCurrentUserId();
        
        return $this->render('medecin/dashboard.html.twig', [
            'medecin' => $medecin,
            'userId' => $userId,
        ]);
    }

    #[Route('/medecin/formations', name: 'medecin_formations')]
    public function formations(Request $request, FormationRepository $formationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $userId = $this->getCurrentUserId();
        $medecin = $this->getCurrentMedecin();

        // Get selected category from query parameter (e.g., ?category=Urgence)
        $selectedCategory = $request->query->get('category');

        // Get formations filtered by category (or all if none selected)
        $formations = $formationRepository->findValidatedByCategory($selectedCategory);

        // Get all categories for dropdown
        $categories = $formationRepository->findAllCategories();

        return $this->render('formation/formations.html.twig', [
            'formations' => $formations,          // filtered list
            'categories' => $categories,          // list of all categories
            'selectedCategory' => $selectedCategory, // currently selected category
            'userId' => $userId,
            'medecin' => $medecin,
        ]);
    }

    #[Route('/medecin/consultations', name: 'medecin_consultations')]
    public function consultations(Request $request, ConsultationRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $userId = $this->getCurrentUserId();
        $medecin = $this->getCurrentMedecin();

        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'date');
        
        $consultations = $repository->findAll();
        
        // Filter by search term
        if ($search) {
            $consultations = array_filter($consultations, function($c) use ($search) {
                return stripos($c->getMotif(), $search) !== false || 
                       stripos($c->getName() ?? '', $search) !== false ||
                       stripos($c->getFamilyName() ?? '', $search) !== false;
            });
        }
        
        // Sort
        if ($sort === 'motif') {
            usort($consultations, fn($a, $b) => strcmp($a->getMotif(), $b->getMotif()));
        } elseif ($sort === 'date') {
            usort($consultations, fn($a, $b) => $b->getDateConsultation() <=> $a->getDateConsultation());
        }

        return $this->render('consultation/consultations.html.twig', [
            'consultations' => $consultations,
            'search' => $search,
            'sort' => $sort,
            'userId' => $userId,
            'medecin' => $medecin,
            'navigation' => [
                ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
                ['name' => 'Consultations', 'path' => $this->generateUrl('medecin_consultations'), 'icon' => '🩺'],
                ['name' => 'Formations', 'path' => $this->generateUrl('medecin_formations'), 'icon' => '📚'],
                ['name' => 'Ordonnances', 'path' => $this->generateUrl('Ordonnance_new'), 'icon' => '💊']
            ],
            'context' => 'medecin'
        ]);
    }

    #[Route('/medecin/formations/new', name: 'medecin_formation_new')]
    public function newFormation(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $medecin = $this->getCurrentMedecin();
        if (!$medecin) {
            throw $this->createAccessDeniedException('You must be a medecin to create formations');
        }

        $formation = new Formation();
        $formation->setMedecin($medecin);

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($formation);
            $em->flush();

            return $this->redirectToRoute('medecin_formations');
        }

        return $this->render('formation/formation_new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/medecin/consultation/{id}/accept', name: 'medecin_consultation_accept', methods: ['POST'])]
    public function accept(
        Consultation $consultation,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $consultation->setStatus('accepted');
        $em->flush();

        $this->addFlash('success', 'Consultation accepted successfully!');
        return $this->redirectToRoute('medecin_consultations');
    }

    #[Route('/medecin/consultation/{id}/decline', name: 'medecin_consultation_decline', methods: ['POST'])]
    public function decline(
        Consultation $consultation,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $consultation->setStatus('declined');
        $em->flush();

        $this->addFlash('success', 'Consultation declined successfully!');
        return $this->redirectToRoute('medecin_consultations');
    }

    #[Route('/medecin/consultation/{id}/delete', name: 'consultation_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Consultation $consultation,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($this->isCsrfTokenValid('delete' . $consultation->getId(), $request->request->get('_token'))) {
            $em->remove($consultation);
            $em->flush();
        }

        return $this->redirectToRoute('medecin_consultations');
    }
}

