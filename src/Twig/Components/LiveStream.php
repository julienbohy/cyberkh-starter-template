<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\RealTime\LiveTopic;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The single private Mercure stream of a page. The hub has one cookie, which every tab rewrites: the
 * page subscribes to its own topics, but the cookie grants every topic the user may follow, so a tab
 * opened later never narrows the grants of a tab still listening.
 */
#[AsTwigComponent]
final class LiveStream
{
    /**
     * @var list<string>
     */
    private array $live = [];

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    /**
     * @param list<string> $live the `LiveTopic` values the page declares with `{% set live = [...] %}`
     */
    public function mount(array $live = []): void
    {
        $this->live = $live;
    }

    /**
     * What the page subscribes to: its topics, when the user may follow them.
     *
     * @return list<string>
     */
    public function topics(): array
    {
        $topics = array_map(static fn(string $value): string => LiveTopic::from($value)->uri(), $this->live);

        return array_values(array_intersect($topics, $this->grants()));
    }

    /**
     * What the cookie grants: the same on every page, so that tabs do not overwrite each other's grants.
     *
     * @return list<string>
     */
    public function grants(): array
    {
        $grants = [];
        foreach (LiveTopic::cases() as $topic) {
            if ($this->authorizationChecker->isGranted($topic->role()->value)) {
                $grants[] = $topic->uri();
            }
        }

        return $grants;
    }
}
