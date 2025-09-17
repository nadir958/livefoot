<?php

namespace App\Tests\Controller;

use App\Entity\LeagueConfig;
use App\Repository\LeagueConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LeagueConfigControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $leagueConfigRepository;
    private string $path = '/league/config/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->leagueConfigRepository = $this->manager->getRepository(LeagueConfig::class);

        foreach ($this->leagueConfigRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('LeagueConfig index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'league_config[providerLeagueId]' => 'Testing',
            'league_config[name]' => 'Testing',
            'league_config[country]' => 'Testing',
            'league_config[seasonsActive]' => 'Testing',
            'league_config[enabled]' => 'Testing',
            'league_config[sortOrder]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->leagueConfigRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new LeagueConfig();
        $fixture->setProviderLeagueId('My Title');
        $fixture->setName('My Title');
        $fixture->setCountry('My Title');
        $fixture->setSeasonsActive('My Title');
        $fixture->setEnabled('My Title');
        $fixture->setSortOrder('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('LeagueConfig');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new LeagueConfig();
        $fixture->setProviderLeagueId('Value');
        $fixture->setName('Value');
        $fixture->setCountry('Value');
        $fixture->setSeasonsActive('Value');
        $fixture->setEnabled('Value');
        $fixture->setSortOrder('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'league_config[providerLeagueId]' => 'Something New',
            'league_config[name]' => 'Something New',
            'league_config[country]' => 'Something New',
            'league_config[seasonsActive]' => 'Something New',
            'league_config[enabled]' => 'Something New',
            'league_config[sortOrder]' => 'Something New',
        ]);

        self::assertResponseRedirects('/league/config/');

        $fixture = $this->leagueConfigRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getProviderLeagueId());
        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('Something New', $fixture[0]->getCountry());
        self::assertSame('Something New', $fixture[0]->getSeasonsActive());
        self::assertSame('Something New', $fixture[0]->getEnabled());
        self::assertSame('Something New', $fixture[0]->getSortOrder());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new LeagueConfig();
        $fixture->setProviderLeagueId('Value');
        $fixture->setName('Value');
        $fixture->setCountry('Value');
        $fixture->setSeasonsActive('Value');
        $fixture->setEnabled('Value');
        $fixture->setSortOrder('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/league/config/');
        self::assertSame(0, $this->leagueConfigRepository->count([]));
    }
}
