<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'league')]
#[ORM\Index(name: 'idx_league_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class League
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    // ID de l'API externe (clé d’upsert)
    #[ORM\Column(name: 'external_id', type: 'integer', unique: true)]
    private int $externalId;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Country $country;

    #[ORM\Column(length: 128)]
    private string $name;

    // "league" | "cup"
    #[ORM\Column(length: 16)]
    private string $type;

    #[ORM\Column(type: 'integer')]
    private int $seasonCurrent;

    #[ORM\Column(nullable: true)]
    private ?string $logo = null; // URL

    #[ORM\Column(length: 128, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $showOnHome = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $homeSort = null;

    public function __construct(
        int $externalId = 0,
        Country $country = null,
        string $name = '',
        string $type = 'league',
        int $seasonCurrent = 0,
        ?string $logo = null,
        string $slug = ''
    ) {
        if ($country) { $this->country = $country; }
        $this->externalId = $externalId;
        $this->name = $name;
        $this->type = $type;
        $this->seasonCurrent = $seasonCurrent;
        $this->logo = $logo;
        $this->slug = $slug;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    // Getters / Setters
    public function getId(): ?int { return $this->id; }

    public function getExternalId(): int { return $this->externalId; }
    public function setExternalId(int $ext): self { $this->externalId = $ext; return $this; }

    public function getCountry(): Country { return $this->country; }
    public function setCountry(Country $country): self { $this->country = $country; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getSeasonCurrent(): int { return $this->seasonCurrent; }
    public function setSeasonCurrent(int $season): self { $this->seasonCurrent = $season; return $this; }

    public function getLogo(): ?string { return $this->logo; }
    public function setLogo(?string $logo): self { $this->logo = $logo; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function isShowOnHome(): bool { return $this->showOnHome; }
    public function setShowOnHome(bool $v): self { $this->showOnHome = $v; return $this; }

    public function getHomeSort(): ?int { return $this->homeSort; }
    public function setHomeSort(?int $v): self { $this->homeSort = $v; return $this; }
}
