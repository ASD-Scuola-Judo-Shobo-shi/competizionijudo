<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Model\Event;

final class EventPosterViewModel
{
    public readonly ?string $url;
    public readonly bool $isImage;
    public readonly bool $requiresLogin;

    public function __construct(Event $event, bool $canViewPrivate)
    {
        $usePrivate = $canViewPrivate && !empty($event->poster_file);
        $file = $usePrivate ? $event->poster_file : $event->public_poster_file;
        $endpoint = $usePrivate ? '/events/poster' : '/events/public-poster';
        $this->url = $file ? base_url($endpoint . '?event=' . (string) $event->id) : null;
        $this->isImage = $file && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);
        $this->requiresLogin = !$file && !$canViewPrivate && !empty($event->poster_file);
    }

    /**
     * @param list<Event> $events
     * @return array<int, self>
     */
    public static function forEvents(array $events, bool $canViewPrivate): array
    {
        $posters = [];
        foreach ($events as $event) {
            $posters[$event->id] = new self($event, $canViewPrivate);
        }

        return $posters;
    }
}
