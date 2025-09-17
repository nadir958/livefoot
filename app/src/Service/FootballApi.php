<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FootballApi
{
    private HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        #[Autowire('%env(resolve:API_FOOTBALL_BASE)%')] string $baseUri,
        #[Autowire('%env(API_FOOTBALL_KEY)%')] string $apiKey,
    ) {
        // Build a preconfigured client without needing a named service
        $this->client = $client->withOptions([
            'base_uri' => $baseUri,
            'headers' => [
                'x-apisports-key' => $apiKey,
                'Accept' => 'application/json',
            ],
        ]);
    }

    /** @return array<mixed> response[] */
    public function get(string $path, array $query = []): array
    {
        $r = $this->client->request('GET', $path, ['query' => $query]);
        $data = $r->toArray(false);
        return $data['response'] ?? $data;
    }
}
