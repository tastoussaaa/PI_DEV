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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Service\AiDescriptionService;
use Symfony\Component\HttpFoundation\JsonResponse;

class MedecinController extends BaseController
{
          private AiDescriptionService $aiService;

    public function __construct(UserService $userService, private MailerInterface $mailer, AiDescriptionService $aiService)
    {
        parent::__construct($userService);
        $this->aiService = $aiService;    // 👈 store it
    }



    

    #[Route('/medecin/dashboard', name: 'app_medecin_dashboard')]
    public function dashboard(ConsultationRepository $consultationRepository): Response
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

        $medecin = $this->getCurrentMedecin();
        $userId = $this->getCurrentUserId();
        
        // Get consultations for this medecin
        $consultations = [];
        if ($medecin) {
            $consultations = $medecin->getConsultations()->toArray();
            // Sort by date descending
            usort($consultations, fn($a, $b) => $b->getDateConsultation() <=> $a->getDateConsultation());
        }
        
        // Get upcoming consultations (next 7 days)
        $now = new \DateTime();
        $upcomingConsultations = array_filter($consultations, function($c) use ($now) {
            $consultationDate = $c->getDateConsultation();
            if (!$consultationDate) return false;
            $consultationDt = \DateTime::createFromInterface($consultationDate);
            return $consultationDt >= $now && $consultationDt < (clone $now)->modify('+7 days');
        });
        
        return $this->render('medecin/dashboard.html.twig', [
            'medecin' => $medecin,
            'userId' => $userId,
            'consultations' => $consultations,
            'upcomingConsultations' => $upcomingConsultations,
        ]);
    }

    #[Route('/medecin/formations', name: 'medecin_formations')]
    public function formations(Request $request, FormationRepository $formationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $userId = $this->getCurrentUserId();
        $medecin = $this->getCurrentMedecin();

        // Récupérer le filtre catégorie et le terme de recherche depuis l'URL
        $selectedCategory = $request->query->get('category');
        $searchTerm = $request->query->get('search');

        // Récupérer les formations filtrées par catégorie et par nom
        $formations = $formationRepository->findValidatedByCategory($selectedCategory, $searchTerm);

        // Récupérer toutes les catégories pour le dropdown
        $categories = $formationRepository->findAllCategories();

        return $this->render('formation/formations.html.twig', [
            'formations' => $formations,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'searchTerm' => $searchTerm,            // pour pré-remplir le champ de recherche
            'userId' => $userId,
            'medecin' => $medecin,
            'current_user_type' => 'medecin',      // nécessaire pour le template
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
            $consultations = array_filter($consultations, function ($c) use ($search) {
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
 #[Route('/medecin/generate-description', name: 'medecin_formation_generate_description', methods: ['POST'])]   
     public function generateDescription(Request $request): JsonResponse
    {
        $data = [
            'title' => $request->request->get('title', ''),
            'category' => $request->request->get('category', ''),
            'startDate' => $request->request->get('startDate', ''),
            'endDate' => $request->request->get('endDate', ''),
        ];

        // Generate AI description
       $description = $this->aiService->generateDescription($data);

        return new JsonResponse(['description' => $description]);
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

            $this->addFlash('formation_success', [
                'title' => $formation->getTitle(),
                'description' => $formation->getDescription(),
                'start' => $formation->getStartDate()->format('Ymd\THis') . 'Z',
                'end' => $formation->getEndDate()->format('Ymd\THis') . 'Z',
            ]);

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
        $consultation->setStatus('accepted');
        $em->flush();

        // Send email notification to patient
        try {
            $this->sendConsultationStatusEmail($consultation, 'accepted');
        } catch (\Exception $e) {
            // Log the error but don't fail the acceptance
            error_log('Failed to send acceptance email: ' . $e->getMessage());
        }

        $this->addFlash('success', 'Consultation accepted successfully!');
        return $this->redirectToRoute('medecin_consultations');
    }

    #[Route('/medecin/consultation/{id}/decline', name: 'medecin_consultation_decline', methods: ['POST'])]
    public function decline(
        Consultation $consultation,
        EntityManagerInterface $em
    ): Response {
        $consultation->setStatus('declined');
        $em->flush();

        // Send email notification to patient
        try {
            $this->sendConsultationStatusEmail($consultation, 'declined');
        } catch (\Exception $e) {
            // Log the error but don't fail the decline
            error_log('Failed to send decline email: ' . $e->getMessage());
        }

        $this->addFlash('success', 'Consultation declined successfully!');
        return $this->redirectToRoute('medecin_consultations');
    }

    #[Route('/medecin/consultation/{id}/delete', name: 'consultation_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Consultation $consultation,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $consultation->getId(), $request->request->get('_token'))) {
            $em->remove($consultation);
            $em->flush();
        }

        return $this->redirectToRoute('medecin_consultations');
    }


    /*     #[Route('/google/connect', name: 'google_connect')]
    public function connect(GoogleCalendarService $googleService)
    {
        $client = $googleService->getClient();
        return $this->redirect($client->createAuthUrl());
    }

    #[Route('/google/callback', name: 'google_callback')]
    public function callback(Request $request, GoogleCalendarService $googleService)
    {
        $client = $googleService->getClient();
        $token = $client->fetchAccessTokenWithAuthCode($request->get('code'));
        $client->setAccessToken($token);

        $this->get('session')->set('google_token', $token);

        return $this->redirectToRoute('dashboard');
    } */
}
