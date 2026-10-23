<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Storage\AvatarThumbnails;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the photos of the users, from their storage (local or S3): to the signed-in users only.
 */
final class AvatarController extends AbstractController
{
    #[Route(path: '/users/{id}/avatar/{size}', name: 'app_avatar', requirements: [
        'id' => Requirement::UUID,
        'size' => 'small|large',
    ], methods: ['GET'])]
    #[IsGranted(attribute: AuthenticatedVoter::IS_AUTHENTICATED)]
    public function show(User $user, string $size, AvatarThumbnails $thumbnails): Response
    {
        $photo = $user->getAvatarName();
        if ($photo === null) {
            throw $this->createNotFoundException('The user has no photo.');
        }

        $stream = $thumbnails->read($thumbnails->thumbnail($photo, $size));

        // The URL changes with the photo (avatar_url()): the browser may keep it for long.
        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
