<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProduitController extends AbstractController
{
    #[Route('/produit', name: 'produit_list', methods: ['GET'])]
    public function list(Request $request, ProduitRepository $produitRepo): Response
    {
        $categorie = $request->query->get('categorie', '');
        $tri = $request->query->get('tri', 'nom');
        $ordre = $request->query->get('ordre', 'ASC');
        $recherche = $request->query->get('recherche', '');

        // Ensure valid sort and order values
        $tri = in_array($tri, ['nom', 'prix', 'categorie']) ? $tri : 'nom';
        $ordre = in_array($ordre, ['ASC', 'DESC']) ? $ordre : 'ASC';

        $produits = $produitRepo->findForShop($categorie ?: null, $tri, $ordre);

        if ($recherche !== null && $recherche !== '') {
            $recherche = trim($recherche);
            $produits = array_filter($produits, function ($p) use ($recherche) {
                return stripos($p->getNom(), $recherche) !== false
                    || stripos($p->getCategorie() ?? '', $recherche) !== false;
            });
        }

        $categories = $produitRepo->findDistinctCategories();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋'],
        ];

        return $this->render('produit/list.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'categorie_filtre' => $categorie,
            'tri' => $tri,
            'ordre' => $ordre,
            'recherche' => $recherche,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/produit/add', name: 'produit_add')]
    public function add(Request $request, EntityManagerInterface $em, ProduitRepository $produitRepo, SluggerInterface $slugger): Response
    {
        $produit = new Produit();

        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/produits';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $produit->setImageName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléversement de l\'image.');
                    return $this->redirectToRoute('produit_add');
                }
            }
            $em->persist($produit);
            $em->flush();

            $this->addFlash('success', 'Produit ajouté avec succès !');

            return $this->redirectToRoute('produit_add');
        }

        $produits = $produitRepo->findBy([], ['id' => 'DESC']);
        $categories = $produitRepo->findDistinctCategories();

        //
        return $this->render('produit/add.html.twig', [
            'form' => $form->createView(),
            'produits' => $produits,
            'categories' => $categories,
        ]);
    }

    #[Route('/produit/{id}', name: 'produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/produit/{id}/edit', name: 'produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $em, ProduitRepository $produitRepo, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/produits';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $produit->setImageName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléversement de l\'image.');
                    return $this->redirectToRoute('produit_edit', ['id' => $produit->getId()]);
                }
            }
            $em->flush();
            $this->addFlash('success', 'Produit mis à jour.');
            return $this->redirectToRoute('produit_add');
        }

        $categories = $produitRepo->findDistinctCategories();

        return $this->render('produit/edit.html.twig', [
            'form' => $form->createView(),
            'produit' => $produit,
            'categories' => $categories,
        ]);
    }

    #[Route('/produit/{id}', name: 'produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $produit->getId(), $token)) {
            $em->remove($produit);
            $em->flush();
            $this->addFlash('success', 'Produit supprimé.');
        }
        return $this->redirectToRoute('produit_add');
    }
}
