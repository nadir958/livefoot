<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FootballProvider
{
    private string $base;
    private string $host;

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(env: 'FOOTBALL_API_BASE_URL')] string $base,
        #[Autowire(env: 'FOOTBALL_API_KEY')]      private string $apiKey,
    ) {
        // Normalize base (ensure /v3 present)
        $base = rtrim($base, '/');
        if (!str_ends_with($base, '/v3')) {
            $base .= '/v3';
        }
        $this->base = $base;

        // Derive RapidAPI host from base
        $parts = parse_url($this->base);
        $this->host = $parts['host'] ?? 'api-football-v1.p.rapidapi.com';
    }

    /**
     * Generic GET with RapidAPI headers (API-Football v3).
     * Enforces existence of "response" array.
     *
     * @return array<int, mixed> The 'response' array
     */
    private function getResponse(string $path, array $q = []): array
    {
        $url = $this->base . $path;

        $res = $this->client->request('GET', $url, [
            'headers' => [
                'Accept'            => 'application/json',
                'X-RapidAPI-Key'    => $this->apiKey,
                'X-RapidAPI-Host'   => $this->host,
            ],
            'query'   => array_filter($q, static fn($v) => $v !== null && $v !== ''),
            'timeout' => 20,
        ]);

        $status = $res->getStatusCode();
        $data   = $res->toArray(false);

        if ($status >= 400) {
            throw new \RuntimeException("API error {$status}: " . json_encode($data, JSON_UNESCAPED_SLASHES));
        }

        if (!\is_array($data) || !\array_key_exists('response', $data) || !\is_array($data['response'])) {
            $previewJson = json_encode($data, JSON_UNESCAPED_SLASHES);
            $preview = substr((string)$previewJson, 0, 400) . ((strlen((string)$previewJson) > 400) ? '…' : '');
            throw new \RuntimeException("Unexpected API shape (missing 'response'). Body preview: {$preview}");
        }

        return $data['response'];
    }

    private static function norm(?string $s): ?string
    {
        $s = $s !== null ? trim($s) : null;
        return ($s === '') ? null : $s;
    }

    private static function mapStatus(?string $short, ?string $long): string
    {
        $s = strtoupper((string)($short ?? ''));
        return match (true) {
            $s === 'FT' || $s === 'AET' || $s === 'PEN' => 'finished',
            $s === 'NS' || $s === 'PST' || $s === 'TBD' || $s === 'CANC' => 'scheduled',
            $s === '1H' || $s === '2H' || $s === 'ET' || $s === 'HT' || $s === 'P' => 'live',
            default => ($long && str_contains(strtolower($long), 'live')) ? 'live' : 'scheduled',
        };
    }

    /** ---------------------------
     * Internal: map one fixtures row
     * --------------------------- */
    private static function mapFixtureRow(array $row): array
    {
        $fx     = $row['fixture'] ?? [];
        $teams  = $row['teams']   ?? [];
        $home   = $teams['home']  ?? [];
        $away   = $teams['away']  ?? [];
        $goals  = $row['goals']   ?? [];
        $league = $row['league']  ?? [];
        $short  = $fx['status']['short'] ?? null;
        $long   = $fx['status']['long']  ?? null;

        return [
            'externalId' => (int)($fx['id'] ?? 0),
            'dateUtc'    => (string)($fx['date'] ?? ''),
            'status'     => self::mapStatus($short, $long),
            'round'      => self::norm($fx['round'] ?? null),         // sometimes present in fixture
            'stage'      => self::norm($league['round'] ?? null),     // often present here
            'venue'      => self::norm($fx['venue']['name'] ?? null),
            'home'       => [
                'id'    => (int)($home['id'] ?? 0),
                'name'  => (string)($home['name'] ?? ''),
                'logo'  => self::norm($home['logo'] ?? null),
                'goals' => array_key_exists('home', $goals) ? (int)$goals['home'] : null,
            ],
            'away'       => [
                'id'    => (int)($away['id'] ?? 0),
                'name'  => (string)($away['name'] ?? ''),
                'logo'  => self::norm($away['logo'] ?? null),
                'goals' => array_key_exists('away', $goals) ? (int)$goals['away'] : null,
            ],
        ];
    }

    /* =======================
       Public API mappers
       ======================= */

    /** GET /countries */
    public function getCountries(): array
    {
        $rows = $this->getResponse('/countries');

        return array_values(array_filter(array_map(static function ($row) {
            $code = (string)($row['code'] ?? '');
            $name = (string)($row['name'] ?? '');
            $flag = self::norm($row['flag'] ?? null);
            return [
                'code' => $code,
                'name' => $name,
                'flag' => $flag,
            ];
        }, $rows), static fn($x) => ($x['code'] ?? '') !== '' || ($x['name'] ?? '') !== ''));
    }

    /** GET /leagues?code=FR */
    public function getLeaguesByCountry(string $code): array
    {
        $rows = $this->getResponse('/leagues', ['code' => strtoupper($code)]);

        return array_values(array_map(static function ($row) {
            $league   = $row['league']  ?? [];
            $country  = $row['country'] ?? [];
            $seasons  = $row['seasons'] ?? [];

            $season = (int)date('Y');
            if (\is_array($seasons) && !empty($seasons)) {
                $years = array_map(static fn($s) => (int)($s['year'] ?? 0), $seasons);
                $season = max($years) ?: $season;
            }

            return [
                'externalId'  => (int)($league['id'] ?? 0),
                'name'        => (string)($league['name'] ?? ''),
                'type'        => strtolower((string)($league['type'] ?? 'league')), // "league" | "cup"
                'logo'        => self::norm($league['logo'] ?? null),
                'season'      => $season,
                'countryCode' => self::norm($country['code'] ?? null),
            ];
        }, $rows));
    }

    /** GET /teams?league={id}&season={year} */
    public function getTeamsByLeagueSeason(int $leagueId, int $season): array
    {
        $rows = $this->getResponse('/teams', ['league' => $leagueId, 'season' => $season]);

        return array_values(array_map(static function ($row) {
            $team = $row['team'] ?? [];
            return [
                'externalId'  => (int)($team['id'] ?? 0),
                'name'        => (string)($team['name'] ?? ''),
                'shortName'   => self::norm($team['code'] ?? null),
                'logo'        => self::norm($team['logo'] ?? null),
                'countryCode' => self::norm($team['country'] ?? null),
            ];
        }, $rows));
    }

    /**
     * GET /fixtures?league={id}&season={year}[&date=YYYY-MM-DD]
     * Response: { response: [ { fixture:{id,date,status,venue}, league:{round}, teams:{home,away}, goals:{home,away} }, ... ] }
     */
    public function getMatchesByLeagueSeason(int $leagueId, int $season, ?string $date = null): array
    {
        $q = ['league' => $leagueId, 'season' => $season];
        if ($date) { $q['date'] = $date; } // UTC date

        $rows = $this->getResponse('/fixtures', $q);

        return array_values(array_map(self::mapFixtureRow(...), $rows));
    }

    /**
     * NEW: GET /fixtures?id={fixtureId}
     * Returns a single mapped match or null if not found.
     */
    public function getMatchByExternalId(int $fixtureId): ?array
    {
        $rows = $this->getResponse('/fixtures', ['id' => $fixtureId]);
        if (!\is_array($rows) || count($rows) === 0) {
            return null;
        }
        // API returns one row in "response" when using ?id=...
        return self::mapFixtureRow($rows[0]);
    }

    /**
     * (Optional helper) GET /fixtures?date=YYYY-MM-DD
     * Useful as a wide fallback when league/season filters miss some events.
     */
    public function getMatchesByDate(string $dateYmd): array
    {
        $rows = $this->getResponse('/fixtures', ['date' => $dateYmd]);
        return array_values(array_map(self::mapFixtureRow(...), $rows));
    }
}
