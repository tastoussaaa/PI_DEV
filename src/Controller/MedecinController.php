<?php

namespace App\Controller;
use App\Entity\Consultation;
use App\Repository\ConsultationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class MedecinController extends AbstractController
{
    
    #[Route('/medecin/dashboard', name: 'medecin_dashboard')]
    public function dashboard()
    {
        return $this->render('medecin/dashboard.html.twig');
    }

    #[Route('/medecin/formations', name: 'medecin_formations')]
    public function formations()
    {
        return $this->render('formation/formations.html.twig');
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
public function accept(Consultation $consultation, EntityManagerInterface $em): Response
{
    $consultation->setStatus('accepted');
    $em->flush();

    return $this->redirectToRoute('medecin_consultations');
}

#[Route('/medecin/consultation/{id}/decline', name: 'consultation_decline', methods: ['POST'])]
public function decline(Consultation $consultation, EntityManagerInterface $em): Response
{
    $consultation->setStatus('declined');
    $em->flush();

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

