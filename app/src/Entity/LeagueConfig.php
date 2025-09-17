<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\LeagueConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeagueConfigRepository::class)]
class LeagueConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // External provider ID (API-Football league id)
    #[ORM\Column]
    private int $providerLeagueId;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $seasonsActive = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(length: 120, unique: true)]
    private string $slug = '';

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getId(): ?int { return $this->id; }

    public function getProviderLeagueId(): int { return $this->providerLeagueId; }
    public function setProviderLeagueId(int $v): self { $this->providerLeagueId = $v; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $v): self { $this->country = $v; return $this; }

    public function getSeasonsActive(): array { return $this->seasonsActive ?? []; }
    public function setSeasonsActive(?array $v): self { $this->seasonsActive = $v ?? []; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $v): self { $this->enabled = $v; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): self { $this->sortOrder = $v; return $this; }
}
