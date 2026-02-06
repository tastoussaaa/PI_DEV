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
        return $this->render('Ordonnance/index.html.twig', [
            'Ordonnances' => $repository->findAll(),
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

            return $this->redirectToRoute('Ordonnance_index');
        }

        return $this->render('Ordonnance/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'Ordonnance_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Ordonnance $Ordonnance, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(OrdonnanceType::class, $Ordonnance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('Ordonnance_index');
        }

        return $this->render('Ordonnance/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'Ordonnance_delete', methods: ['POST'])]
    public function delete(Request $request, Ordonnance $Ordonnance, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$Ordonnance->getId(), $request->request->get('_token'))) {
            $em->remove($Ordonnance);
            $em->flush();
        }

        return $this->redirectToRoute('Ordonnance_index');
    }

    #[Route('/{id}', name: 'Ordonnance_show', methods: ['GET'])]
    public function show(Ordonnance $Ordonnance): Response
    {
        return $this->render('Ordonnance/show.html.twig', [
            'Ordonnance' => $Ordonnance,
        ]);
    }
}