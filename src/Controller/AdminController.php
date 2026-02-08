<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use App\Repository\MedecinRepository;
use App\Repository\AideSoignantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Formation;
use App\Entity\Medecin;
use App\Entity\AideSoignant;
use Doctrine\ORM\EntityManagerInterface;

final class AdminController extends AbstractController
{
    #[Route('/admin/formations', name: 'admin_formations')]
    public function formations(FormationRepository $formationRepository)
    {
        $formations = $formationRepository->findAll();

        return $this->render('formation/admin_formations.html.twig', [
            'formations' => $formations
        ]);
    }

    #[Route('/admin', name: 'app_admin_dashboard')]
    public function dashboard(MedecinRepository $medecinRepository, AideSoignantRepository $aideRepo)
    {
        $pendingMedecins = $medecinRepository->findBy(['isValidated' => false]);
        $validatedMedecins = $medecinRepository->findBy(['isValidated' => true]);

        $pendingAideSoignants = $aideRepo->findBy(['isValidated' => false]);
        $validatedAideSoignants = $aideRepo->findBy(['isValidated' => true]);

        $navigation = [
            ['name' => 'Validation des Comptes', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '✓'],
            ['name' => 'Formations', 'path' => $this->generateUrl('admin_formations'), 'icon' => '📚'],
        ];

        return $this->render('admin/dashboard.html.twig', [
            'pendingMedecins' => $pendingMedecins,
            'validatedMedecins' => $validatedMedecins,
            'pendingAideSoignants' => $pendingAideSoignants,
            'validatedAideSoignants' => $validatedAideSoignants,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/admin/medecin/{id}/validate', name: 'admin_medecin_validate', methods: ['POST'])]
    public function validateMedecin(Medecin $medecin, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('validate-medecin' . $medecin->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        $medecin->setIsValidated(true);
        $em->flush();

        $this->addFlash('success', "Le médecin '{$medecin->getFullName()}' a été validé.");

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/medecin/{id}/reject', name: 'admin_medecin_reject', methods: ['POST'])]
    public function rejectMedecin(Medecin $medecin, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reject-medecin' . $medecin->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        // If there's a linked user, remove it as well
        $user = $medecin->getUser();
        if ($user) {
            $em->remove($user);
        }

        $em->remove($medecin);
        $em->flush();

        $this->addFlash('success', "Le compte du médecin '{$medecin->getFullName()}' a été rejeté et supprimé.");

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/aide-soignant/{id}/validate', name: 'admin_aide_soignant_validate', methods: ['POST'])]
    public function validateAideSoignant(AideSoignant $aide, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('validate-aide' . $aide->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        $aide->setIsValidated(true);
        $em->flush();

        $this->addFlash('success', "L'aide-soignant '{$aide->getNom()} {$aide->getPrenom()}' a été validé.");

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/aide-soignant/{id}/reject', name: 'admin_aide_soignant_reject', methods: ['POST'])]
    public function rejectAideSoignant(AideSoignant $aide, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reject-aide' . $aide->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        $user = $aide->getUser();
        if ($user) {
            $em->remove($user);
        }

        $em->remove($aide);
        $em->flush();

        $this->addFlash('success', "Le compte de l'aide-soignant '{$aide->getNom()} {$aide->getPrenom()}' a été rejeté et supprimé.");

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/formation/{id}/status', name: 'admin_formation_update_status', methods: ['POST'])]
    public function updateStatus(Formation $formation, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update-status-' . $formation->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        $statut = $request->request->get('statut');

        if (!in_array($statut, Formation::STATUTS)) {
            throw $this->createNotFoundException('Statut invalide.');
        }

        $formation->setStatut($statut);
        $em->flush();

        $this->addFlash('success', "Le statut de la formation '{$formation->getTitle()}' a été mis à jour.");

        return $this->redirectToRoute('admin_formations');
    }
}
