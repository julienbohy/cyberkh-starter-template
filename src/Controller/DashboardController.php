<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    #[Route(path: '/', name: 'app_home')]
    #[IsGranted(attribute: AuthenticatedVoter::IS_AUTHENTICATED)]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig');
    }
}
