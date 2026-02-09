<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
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
        return new Response('<h1>Admin Dashboard</h1><p>Welcome, ' . $this->getUser()->getUserIdentifier() . '</p>', 200);
    }
}
