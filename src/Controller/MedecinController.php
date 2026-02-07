<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Formation;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\Response;




class MedecinController extends AbstractController
{
    #[Route('/medecin/dashboard', name: 'medecin_dashboard')]
    public function dashboard()
    {
        return $this->render('medecin/dashboard.html.twig');
    }

     #[Route('/medecin/formations', name: 'medecin_formations')]
    public function formations(Request $request, FormationRepository $formationRepository): Response
    {
        // Get selected category from query parameter (e.g., ?category=Urgence)
        $selectedCategory = $request->query->get('category');

        // Get formations filtered by category (or all if none selected)
        $formations = $formationRepository->findValidatedByCategory($selectedCategory);

        // Get all categories for dropdown
        $categories = $formationRepository->findAllCategories();

        return $this->render('formation/formations.html.twig', [
            'formations' => $formations,          // filtered list
            'categories' => $categories,          // list of all categories
            'selectedCategory' => $selectedCategory // currently selected category
        ]);
    }



    #[Route('/medecin/consultations', name: 'medecin_consultations')]
    public function consultations()
    {
        return $this->render('consultation/consultations.html.twig');
    }


    #[Route('/medecin/formations/new', name: 'medecin_formation_new')]
    public function newFormation(
        Request $request,
        EntityManagerInterface $em
    ) {
        $formation = new Formation();

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
}
