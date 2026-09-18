<?php

namespace App\Services;

use App\Models\Url;
use App\Models\User;
use App\Traits\GeneratesShortCode;
use Illuminate\Support\Facades\Gate;

class UrlService
{
    use GeneratesShortCode;

    public function create(User $user, array $attributes): array
    {
        // Gate::authorize('create', Url::class);

        $url = $user->urls()->create([
            'original_url' => $attributes['url'],
            'short_code' => $attributes['custom_code'] ?? $this->generateShortCode(),
            'click_count' => 0,
        ]);

        return $this->urlData($url);
    }

    public function listUrls(User $user, int $page, int $perPage): array
    {
        // Gate::authorize('viewAny', Url::class);

        $urls = $user->urls()->latest('id')->paginate($perPage, ['*'], 'page', $page);

        return [
            'urls' => array_map($this->urlData(...), $urls->items()),
            'pagination' => [
                'page' => $urls->currentPage(),
                'per_page' => $urls->perPage(),
                'total' => $urls->total(),
                'last_page' => $urls->lastPage(),
            ],
        ];
    }

    public function details(User $user, int $id): array
    {
        return $this->urlData($this->authorizedUrl($user, $id, 'view'));
    }

    public function delete(User $user, int $id): void
    {
        $this->authorizedUrl($user, $id, 'delete')->delete();
    }

    public function redirect(string $shortCode): string
    {
        $url = Url::query()->where('short_code', $shortCode)->firstOrFail();
        $url->increment('click_count');

        return $url->original_url;
    }

    public function stats(User $user, int $id): array
    {
        $url = $this->authorizedUrl($user, $id, 'view');

        return [
            'url' => $url->original_url,
            'short_code' => $url->short_code,
            'click_count' => $url->click_count,
        ];
    }

    private function authorizedUrl(User $user, int $id, string $ability): Url
    {
        $url = Url::query()->findOrFail($id);
        Gate::forUser($user)->authorize($ability, $url);

        return $url;
    }

    private function urlData(Url $url): array
    {
        return [
            'id' => $url->id,
            'original_url' => $url->original_url,
            'short_code' => $url->short_code,
            'click_count' => $url->click_count,
        ];
    }
}
