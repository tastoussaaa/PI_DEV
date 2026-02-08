<?php

namespace App\Controller;
use App\Entity\Consultation;
use App\Repository\ConsultationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

use App\Service\UserService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Formation;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\Response;

class MedecinController extends BaseController
{
    
    #[Route('/medecin/dashboard', name: 'medecin_dashboard')]
    public function dashboard()
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
    $search = $request->query->get('search', '');
    $sort = $request->query->get('sort', 'date');
    
    $consultations = $repository->findAll();
    
    // Filter by search term
    if ($search) {
        $consultations = array_filter($consultations, function($c) use ($search) {
            return stripos($c->getMotif(), $search) !== false || 
                   stripos($c->getName(), $search) !== false ||
                   stripos($c->getFamilyName(), $search) !== false;
        });
    #[Route('/medecin/consultations', name: 'medecin_consultations')]
    public function consultations(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $userId = $this->getCurrentUserId();
        $medecin = $this->getCurrentMedecin();

        return $this->render('consultation/consultations.html.twig', [
            'userId' => $userId,
            'medecin' => $medecin,
        ]);
    }


    #[Route('/medecin/formations/new', name: 'medecin_formation_new')]
    public function newFormation(
        Request $request,
        EntityManagerInterface $em
    ) {
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
    
    // Sort
    if ($sort === 'motif') {
        usort($consultations, fn($a, $b) => strcmp($a->getMotif(), $b->getMotif()));
    } elseif ($sort === 'date') {
        usort($consultations, fn($a, $b) => $b->getDateConsultation() <=> $a->getDateConsultation());
    }

    return $this->render('medecin/index.html.twig', [
        'consultations' => $consultations,
        'search' => $search,
        'sort' => $sort,
    ]);
}
#[Route('/medecin/consultation/{id}/accept', name: 'consultation_accept', methods: ['POST'])]
public function accept(
    Consultation $consultation,
    EntityManagerInterface $em,
    MailerInterface $mailer
): Response {
    $consultation->setStatus('accepted');
    $em->flush();

    $email = (new TemplatedEmail())
        ->from('noreply@aidora.com')
        ->to($consultation->getEmail())
        ->subject('Consultation Accepted')
        ->htmlTemplate('email/consultation_status.html.twig')
        ->context([
            'patientName' => $consultation->getName().' '.$consultation->getFamilyName(),
            'consultationDate' => $consultation->getDateConsultation(),
            'status' => 'accepted',
        ]);

    $mailer->send($email);

    return $this->redirectToRoute('medecin_consultations');
}

#[Route('/medecin/consultation/{id}/decline', name: 'consultation_decline', methods: ['POST'])]
public function decline(
    Consultation $consultation,
    EntityManagerInterface $em,
    MailerInterface $mailer
): Response {
    $consultation->setStatus('declined');
    $em->flush();

    $email = (new TemplatedEmail())
        ->from('noreply@aidora.com')
        ->to($consultation->getEmail())
        ->subject('Consultation Declined')
        ->htmlTemplate('email/consultation_status.html.twig')
        ->context([
            'patientName' => $consultation->getName().' '.$consultation->getFamilyName(),
            'consultationDate' => $consultation->getDateConsultation(),
            'status' => 'declined',
        ]);

    $mailer->send($email);

    return $this->redirectToRoute('medecin_consultations');
}
#[Route('/medecin/consultation/{id}/delete', name: 'consultation_delete', methods: ['POST'])]
public function delete(Request $request, Consultation $consultation, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('delete'.$consultation->getId(), $request->request->get('_token'))) {
        $em->remove($consultation);
        $em->flush();
    }

    return $this->redirectToRoute('medecin_consultations');
}

}

