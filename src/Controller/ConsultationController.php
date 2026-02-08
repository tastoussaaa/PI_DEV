<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Form\ConsultationType;
use App\Repository\ConsultationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/consultation')]
class ConsultationController extends AbstractController
{
    #[Route('/', name: 'consultation_index', methods: ['GET'])]
    public function index(Request $request, ConsultationRepository $repository): Response
    {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'date');

        $consultations = $repository->findAll();

        // Filter by search term
        if ($search) {
            $consultations = array_filter($consultations, function ($c) use ($search) {
                return stripos($c->getMotif(), $search) !== false
                    || stripos($c->getName(), $search) !== false
                    || stripos($c->getFamilyName(), $search) !== false;
            });
        }

        // Sort
        if ($sort === 'motif') {
            usort($consultations, fn($a, $b) => strcmp($a->getMotif(), $b->getMotif()));
        } elseif ($sort === 'date') {
            usort($consultations, fn($a, $b) => $b->getDateConsultation() <=> $a->getDateConsultation());
        }

        return $this->render('consultation/index.html.twig', [
            'consultations' => $consultations,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    #[Route('/new', name: 'consultation_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $consultation = new Consultation();
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // If user is logged in, ensure consultation email is set to user's email
            $user = $this->getUser();
            if ($user && method_exists($user, 'getEmail') && !$consultation->getEmail()) {
                $consultation->setEmail($user->getEmail());
            }

            $em->persist($consultation);
            $em->flush();

            return $this->redirectToRoute('patient_consultations');
        }

        return $this->render('consultation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'consultation_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Consultation $consultation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('consultation_index');
        }

        return $this->render('consultation/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'consultation_delete', methods: ['POST'])]
    public function delete(Request $request, Consultation $consultation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $consultation->getId(), $request->request->get('_token'))) {
            $em->remove($consultation);
            $em->flush();
        }

        return $this->redirectToRoute('consultation_index');
    }

    #[Route('/{id}', name: 'consultation_show', methods: ['GET'])]
    public function show(Consultation $consultation): Response
    {
        return $this->render('consultation/show.html.twig', [
            'consultation' => $consultation,
        ]);
    }
}