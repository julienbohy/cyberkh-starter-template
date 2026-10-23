<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\User;
use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;

/**
 * The thumbnails of the photos of the users (the filter sets of config/packages/liip_imagine.yaml):
 * made on the first request, kept in their storage, and removed with the photo.
 */
final readonly class AvatarThumbnails
{
    /**
     * The size in the URL => the filter set.
     */
    public const array FILTERS = [
        'small' => 'avatar_small',
        'large' => 'avatar_large',
    ];

    public function __construct(
        private DataManager $dataManager,
        private FilterManager $filterManager,
        #[Autowire(service: 'avatars_cache.storage')]
        private FilesystemOperator $cache,
    ) {}

    /**
     * The path of the thumbnail of $size in the cache storage, made if it is not there yet.
     */
    public function thumbnail(string $photo, string $size): string
    {
        $filter = self::FILTERS[$size] ?? throw new \InvalidArgumentException(\sprintf('No thumbnail of size "%s".', $size));
        $path = $filter . '/' . $photo . '.webp';

        if (! $this->cache->fileExists($path)) {
            $content = $this->filterManager->applyFilter($this->dataManager->find($filter, $photo), $filter)->getContent();
            if (! \is_string($content)) {
                throw new \RuntimeException(\sprintf('The thumbnail "%s" of the photo "%s" could not be made.', $filter, $photo));
            }

            $this->cache->write($path, $content);
        }

        return $path;
    }

    /**
     * @return resource
     */
    public function read(string $path)
    {
        return $this->cache->readStream($path);
    }

    /**
     * Before VichUploader removes a photo (replaced, deleted, or with its user): its thumbnails go too.
     */
    #[AsEventListener(event: Events::PRE_REMOVE)]
    public function onPhotoRemoved(Event $event): void
    {
        $photo = $event->getMapping()->getFileName($event->getObject());
        if (! $event->getObject() instanceof User || $photo === null) {
            return;
        }

        foreach (self::FILTERS as $filter) {
            $this->cache->delete($filter . '/' . $photo . '.webp');
        }
    }
}
