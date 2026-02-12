<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/admin/login-check', name: 'admin_login_check')]
    public function loginCheck(): never
    {
        throw new \LogicException('This should be handled by the firewall.');
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): never
    {
        throw new \LogicException('This should be handled by the firewall.');
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $showTopDetailProducts = false;
        $topDetailProducts = [];

        $user = $this->getUser();
        if ($user instanceof User) {
            $restaurant = $user->getRestaurant();
            if ($restaurant !== null && $restaurant->isCountProductDetailViews()) {
                $showTopDetailProducts = true;
                $topDetailProducts = $this->productRepository->findMostDetailViewedByRestaurant($restaurant);
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'showTopDetailProducts' => $showTopDetailProducts,
            'topDetailProducts' => $topDetailProducts,
        ]);
    }
}
