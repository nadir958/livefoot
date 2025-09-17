<?php
declare(strict_types=1);

namespace App\Entity;

use App\Enum\MatchStatus;
use App\Repository\FixtureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FixtureRepository::class)]
#[ORM\Table(
    name: 'matches',
    indexes: [new ORM\Index(name: 'idx_fixture_date_status', columns: ['date_utc','status'])]
)]
class Fixture
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    // ID de l'API externe (clé d’upsert)
    #[ORM\Column(name: 'external_id', type: 'integer', unique: true)]
    private int $externalId;

    #[ORM\ManyToOne(targetEntity: League::class)]
    #[ORM\JoinColumn(nullable: false)]
    private League $league;

    #[ORM\Column(type: 'integer')]
    private int $season;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $round = null;

    #[ORM\Column(name: 'date_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateUtc;

    #[ORM\Column(type: 'string', enumType: MatchStatus::class, length: 16)]
    private MatchStatus $status = MatchStatus::SCHEDULED;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $homeTeam;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $awayTeam;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $homeScore = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $awayScore = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $minute = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $stage = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $venue = null;

    public function __construct(
        int $externalId = 0,
        League $league = null,
        int $season = 0,
        \DateTimeImmutable $dateUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        MatchStatus $status = MatchStatus::SCHEDULED,
        Team $homeTeam = null,
        Team $awayTeam = null
    ) {
        $this->externalId = $externalId;
        if ($league)   { $this->league = $league; }
        if ($homeTeam) { $this->homeTeam = $homeTeam; }
        if ($awayTeam) { $this->awayTeam = $awayTeam; }
        $this->season  = $season;
        $this->dateUtc = $dateUtc;
        $this->status  = $status;
    }

    // Getters / Setters
    public function getId(): ?int { return $this->id; }

    public function getExternalId(): int { return $this->externalId; }
    public function setExternalId(int $ext): self { $this->externalId = $ext; return $this; }

    public function getLeague(): League { return $this->league; }
    public function setLeague(League $league): self { $this->league = $league; return $this; }

    public function getSeason(): int { return $this->season; }
    public function setSeason(int $season): self { $this->season = $season; return $this; }

    public function getRound(): ?string { return $this->round; }
    public function setRound(?string $round): self { $this->round = $round; return $this; }

    public function getDateUtc(): \DateTimeImmutable { return $this->dateUtc; }
    public function setDateUtc(\DateTimeImmutable $dt): self { $this->dateUtc = $dt; return $this; }

    public function getStatus(): MatchStatus { return $this->status; }
    public function setStatus(MatchStatus $status): self { $this->status = $status; return $this; }

    public function getHomeTeam(): Team { return $this->homeTeam; }
    public function setHomeTeam(Team $t): self { $this->homeTeam = $t; return $this; }

    public function getAwayTeam(): Team { return $this->awayTeam; }
    public function setAwayTeam(Team $t): self { $this->awayTeam = $t; return $this; }

    public function getHomeScore(): ?int { return $this->homeScore; }
    public function setHomeScore(?int $s): self { $this->homeScore = $s; return $this; }

    public function getAwayScore(): ?int { return $this->awayScore; }
    public function setAwayScore(?int $s): self { $this->awayScore = $s; return $this; }

    public function getMinute(): ?int { return $this->minute; }
    public function setMinute(?int $m): self { $this->minute = $m; return $this; }

    public function getStage(): ?string { return $this->stage; }
    public function setStage(?string $stage): self { $this->stage = $stage; return $this; }

    public function getVenue(): ?string { return $this->venue; }
    public function setVenue(?string $venue): self { $this->venue = $venue; return $this; }
}
