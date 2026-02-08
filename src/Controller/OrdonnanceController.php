<?php

namespace App\Controller;

use App\Entity\Ordonnance;
use App\Form\OrdonnanceType;
use App\Repository\OrdonnanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/Ordonnance')]
class OrdonnanceController extends AbstractController
{
    #[Route('/', name: 'Ordonnance_index', methods: ['GET'])]
    public function index(OrdonnanceRepository $repository): Response
    {
        return $this->render('ordonnance/index.html.twig', [
            'Ordonnances' => $repository->findAll(),
        ]);
    }

    #[Route('/medecin', name: 'medecin_ordonnances', methods: ['GET'])]
    public function medecinOrdonnances(OrdonnanceRepository $repository): Response
    {
        $ordonnances = $repository->findAll();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('medecin_consultations'), 'icon' => '🩺'],
            ['name' => 'Ordonnances', 'path' => $this->generateUrl('medecin_ordonnances'), 'icon' => '💊'],
        ];

        return $this->render('ordonnance/medecinOrdonnance.html.twig', [
            'ordonnances' => $ordonnances,
            'navigation' => $navigation,
        ]);
    }

 #[Route('/show', name: 'Ordonnance_show_all', methods: ['GET'])]
public function showAll(Request $request, OrdonnanceRepository $repository): Response
{
    $search = $request->query->get('search', '');
    $sort = $request->query->get('sort', 'date');
    
    $ordonnances = $repository->findAll();
    
    // Filter by search term (medicament, consultation name, createdAt)
    if ($search) {
        $ordonnances = array_filter($ordonnances, function($o) use ($search) {
            $consultation = $o->getConsultation();
            $consultationName = $consultation ? ($consultation->getName() . ' ' . $consultation->getFamilyName()) : '';
            $createdAt = $o->getCreatedAt() ? $o->getCreatedAt()->format('Y-m-d') : '';

            return stripos($o->getMedicament(), $search) !== false ||
                   stripos($consultationName, $search) !== false ||
                   stripos($createdAt, $search) !== false;
        });
    }
    
    // Sort
    if ($sort === 'medicament') {
        usort($ordonnances, fn($a, $b) => strcmp($a->getMedicament(), $b->getMedicament()));
    } elseif ($sort === 'consultation') {
        usort($ordonnances, fn($a, $b) => strcmp(
            $a->getConsultation() ? ($a->getConsultation()->getName() . ' ' . $a->getConsultation()->getFamilyName()) : '',
            $b->getConsultation() ? ($b->getConsultation()->getName() . ' ' . $b->getConsultation()->getFamilyName()) : ''
        ));
    } elseif ($sort === 'date') {
        usort($ordonnances, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
    }

    $navigation = [
        ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
        ['name' => 'Consultations', 'path' => $this->generateUrl('medecin_consultations'), 'icon' => '🩺'],
        ['name' => 'Ordonnances', 'path' => $this->generateUrl('Ordonnance_show_all'), 'icon' => '💊'],
    ];

    return $this->render('ordonnance/show.html.twig', [
        'ordonnances' => $ordonnances,
        'search' => $search,
        'sort' => $sort,
        'navigation' => $navigation,
    ]);
}
    #[Route('/new', name: 'Ordonnance_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $Ordonnance = new Ordonnance();
        $form = $this->createForm(OrdonnanceType::class, $Ordonnance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($Ordonnance);
            $em->flush();

            return $this->redirectToRoute('Ordonnance_show_all');
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('medecin_consultations'), 'icon' => '🩺'],
            ['name' => 'Ordonnances', 'path' => $this->generateUrl('Ordonnance_show_all'), 'icon' => '💊'],
        ];

        return $this->render('ordonnance/new.html.twig', [
            'form' => $form->createView(),
            'navigation' => $navigation,
        ]);
    }

    #[Route('/{id}/edit', name: 'Ordonnance_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Ordonnance $Ordonnance, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(OrdonnanceType::class, $Ordonnance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('Ordonnance_show_all');
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('medecin_consultations'), 'icon' => '🩺'],
            ['name' => 'Ordonnances', 'path' => $this->generateUrl('Ordonnance_show_all'), 'icon' => '💊'],
        ];

        return $this->render('Ordonnance/edit.html.twig', [
            'form' => $form->createView(),
            'navigation' => $navigation,
        ]);
    }

    #[Route('/{id}', name: 'Ordonnance_delete', methods: ['POST'])]
    public function delete(Request $request, Ordonnance $Ordonnance, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$Ordonnance->getId(), $request->request->get('_token'))) {
            $em->remove($Ordonnance);
            $em->flush();
        }

        return $this->redirectToRoute('Ordonnance_show_all');
    }

    #[Route('/{id}', name: 'Ordonnance_show', methods: ['GET'])]
    public function show(Ordonnance $Ordonnance): Response
    {
        return $this->render('ordonnance/detail.html.twig', [
            'ordonnance' => $Ordonnance,
        ]);
    }
}