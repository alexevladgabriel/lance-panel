<?php

namespace App\Services\Helpers;

use Exception;
use GuzzleHttp\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use App\Exceptions\Service\Helper\CdnVersionFetchingException;

class SoftwareVersionService
{
    public const VERSION_CACHE_KEY = 'panel:versioning_data';

    private static array $result;

    /**
     * SoftwareVersionService constructor.
     */
    public function __construct(
        protected CacheRepository $cache,
        protected Client $client
    ) {
        self::$result = $this->cacheVersionData();
    }

    /**
     * Get the latest version of the panel from the CDN servers.
     */
    public function getPanel(): string
    {
        return Arr::get(self::$result, 'panel') ?? 'error';
    }

    /**
     * Get the latest version of the daemon from the CDN servers.
     */
    public function getDaemon(): string
    {
        return Arr::get(self::$result, 'daemon') ?? 'error';
    }

    /**
     * Get the URL to the discord server.
     */
    public function getDiscord(): string
    {
        return Arr::get(self::$result, 'discord') ?? 'https://example.com/discord';
    }

    /**
     * Determine if the current version of the panel is the latest.
     */
    public function isLatestPanel(): bool
    {
        if (config('app.version') === 'canary') {
            return true;
        }

        return version_compare(config('app.version'), $this->getPanel()) >= 0;
    }

    /**
     * Determine if a passed daemon version string is the latest.
     */
    public function isLatestDaemon(string $version): bool
    {
        if ($version === 'develop') {
            return true;
        }

        return version_compare($version, $this->getDaemon()) >= 0;
    }

    /**
     * Keeps the versioning cache up-to-date with the latest results from the CDN.
     */
    protected function cacheVersionData(): array
    {
        return $this->cache->remember(self::VERSION_CACHE_KEY, CarbonImmutable::now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                $response = $this->client->request('GET', config('panel.cdn.url'));

                if ($response->getStatusCode() === 200) {
                    return json_decode($response->getBody(), true);
                }

                throw new CdnVersionFetchingException();
            } catch (Exception) {
                return [];
            }
        });
    }

    public function getDonations(): string
    {
        return 'https://github.com';
    }
}
