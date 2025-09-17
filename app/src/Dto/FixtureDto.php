<?php
namespace App\Dto;

final class FixtureDto
{
    public function __construct(
        public readonly int $id,
        public readonly int $leagueId,
        public readonly string $leagueName,
        public readonly \DateTimeImmutable $kickoff,
        public readonly string $status,  // NS|LIVE|HT|FT...
        public readonly int $homeId,
        public readonly string $homeName,
        public readonly ?int $homeGoals,
        public readonly int $awayId,
        public readonly string $awayName,
        public readonly ?int $awayGoals,
        public readonly ?int $minute
    ) {}

    public static function fromApi(array $j): self {
        $f=$j['fixture']; $l=$j['league']; $t=$j['teams']; $g=$j['goals'];
        return new self(
            (int)$f['id'], (int)$l['id'], (string)$l['name'],
            new \DateTimeImmutable($f['date']), (string)$f['status']['short'],
            (int)$t['home']['id'], (string)$t['home']['name'], $g['home']===null?null:(int)$g['home'],
            (int)$t['away']['id'], (string)$t['away']['name'], $g['away']===null?null:(int)$g['away'],
            isset($f['status']['elapsed']) ? (int)$f['status']['elapsed'] : null
        );
    }
}
