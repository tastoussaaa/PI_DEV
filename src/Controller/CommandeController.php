<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/commande')]
class CommandeController extends AbstractController
{
    #[Route('/', name: 'commande_index', methods: ['GET'])]
    public function index(CommandeRepository $commandeRepo): Response
    {
        $user = $this->getUser();
        $commandes = $user
            ? $commandeRepo->findByDemandeur($user)
            : $commandeRepo->findBy([], ['dateCommande' => 'DESC']);

        return $this->render('commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/new', name: 'commande_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ProduitRepository $produitRepo): Response
    {
        $commande = new Commande();
        $produitId = $request->query->get('produitId');
        if ($produitId) {
            $produit = $produitRepo->find($produitId);
            if ($produit) {
                $commande->setProduit($produit);
                $commande->setQuantite(1);
            }
        }
        if ($this->getUser()) {
            $commande->setDemandeur($this->getUser());
        }

        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $produit = $commande->getProduit();
            $quantite = $commande->getQuantite();
            
            // Check if enough stock available
            if ($produit->getStock() < $quantite) {
                $this->addFlash('error', 'Stock insuffisant. Stock disponible: ' . $produit->getStock());
                return $this->render('commande/new.html.twig', [
                    'commande' => $commande,
                    'form' => $form->createView(),
                ]);
            }
            
            // Decrease stock
            $produit->setStock($produit->getStock() - $quantite);
            $commande->setMontantTotal($produit->getPrix() * $quantite);
            $em->persist($commande);
            $em->flush();
            $this->addFlash('success', 'Commande enregistrée.');
            return $this->redirectToRoute('commande_index');
        }

        return $this->render('commande/new.html.twig', [
            'commande' => $commande,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'commande_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/{id}/edit', name: 'commande_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        // Store original quantity for stock adjustment
        $originalQuantite = $commande->getQuantite();
        
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $produit = $commande->getProduit();
            $newQuantite = $commande->getQuantite();
            $quantiteDifference = $newQuantite - $originalQuantite;
            
            // Check if enough stock available for the difference
            if ($quantiteDifference > 0 && $produit->getStock() < $quantiteDifference) {
                $this->addFlash('error', 'Stock insuffisant. Stock disponible: ' . $produit->getStock());
                return $this->render('commande/edit.html.twig', [
                    'commande' => $commande,
                    'form' => $form->createView(),
                ]);
            }
            
            // Adjust stock based on quantity change
            $produit->setStock($produit->getStock() - $quantiteDifference);
            $commande->setMontantTotal($produit->getPrix() * $newQuantite);
            $em->flush();
            $this->addFlash('success', 'Commande mise à jour.');
            return $this->redirectToRoute('commande_index');
        }

        return $this->render('commande/edit.html.twig', [
            'commande' => $commande,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'commande_delete', methods: ['POST'])]
    public function delete(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $commande->getId(), $token)) {
            // Restore stock when command is deleted
            $produit = $commande->getProduit();
            $produit->setStock($produit->getStock() + $commande->getQuantite());
            
            $em->remove($commande);
            $em->flush();
            $this->addFlash('success', 'Commande supprimée.');
        }
        return $this->redirectToRoute('commande_index');
    }
}
