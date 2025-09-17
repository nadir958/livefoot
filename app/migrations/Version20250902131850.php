<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250902131850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE country (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) NOT NULL, code VARCHAR(2) NOT NULL, flag VARCHAR(255) DEFAULT NULL, slug VARCHAR(96) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_5373C966989D9B62 (slug), INDEX idx_country_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE league (id INT AUTO_INCREMENT NOT NULL, country_id INT NOT NULL, external_id INT NOT NULL, name VARCHAR(128) NOT NULL, type VARCHAR(16) NOT NULL, season_current INT NOT NULL, logo VARCHAR(255) DEFAULT NULL, slug VARCHAR(128) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_3EB4C3189F75D7B0 (external_id), UNIQUE INDEX UNIQ_3EB4C318989D9B62 (slug), INDEX IDX_3EB4C318F92F3E70 (country_id), INDEX idx_league_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE matches (id INT AUTO_INCREMENT NOT NULL, league_id INT NOT NULL, home_team_id INT NOT NULL, away_team_id INT NOT NULL, external_id INT NOT NULL, season INT NOT NULL, round VARCHAR(64) DEFAULT NULL, date_utc DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(16) NOT NULL, home_score SMALLINT DEFAULT NULL, away_score SMALLINT DEFAULT NULL, minute SMALLINT DEFAULT NULL, stage VARCHAR(64) DEFAULT NULL, venue VARCHAR(128) DEFAULT NULL, UNIQUE INDEX UNIQ_62615BA9F75D7B0 (external_id), INDEX IDX_62615BA58AFC4DE (league_id), INDEX IDX_62615BA9C4C13F6 (home_team_id), INDEX IDX_62615BA45185D02 (away_team_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, country_id INT NOT NULL, external_id INT NOT NULL, name VARCHAR(128) NOT NULL, short_name VARCHAR(64) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, slug VARCHAR(128) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_C4E0A61F9F75D7B0 (external_id), UNIQUE INDEX UNIQ_C4E0A61F989D9B62 (slug), INDEX IDX_C4E0A61FF92F3E70 (country_id), INDEX idx_team_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE league ADD CONSTRAINT FK_3EB4C318F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA58AFC4DE FOREIGN KEY (league_id) REFERENCES league (id)');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA9C4C13F6 FOREIGN KEY (home_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA45185D02 FOREIGN KEY (away_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61FF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE league DROP FOREIGN KEY FK_3EB4C318F92F3E70');
        $this->addSql('ALTER TABLE matches DROP FOREIGN KEY FK_62615BA58AFC4DE');
        $this->addSql('ALTER TABLE matches DROP FOREIGN KEY FK_62615BA9C4C13F6');
        $this->addSql('ALTER TABLE matches DROP FOREIGN KEY FK_62615BA45185D02');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61FF92F3E70');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE league');
        $this->addSql('DROP TABLE matches');
        $this->addSql('DROP TABLE team');
    }
}
