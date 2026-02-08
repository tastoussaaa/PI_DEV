<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(private UserService $userService) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Get current user and user ID
        $user = $this->userService->getCurrentUser();
        $userId = $this->userService->getCurrentUserId();
        $userType = $this->userService->getCurrentUserType();
        $userEntity = $this->userService->getCurrentUserEntity();

        return $this->render('home/index.html.twig', [
            'user' => $user,
            'userId' => $userId,
            'userType' => $userType,
            'userEntity' => $userEntity,
            'isAuthenticated' => $this->userService->isAuthenticated(),
        ]);
    }
}
