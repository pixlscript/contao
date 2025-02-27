<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Contao;

use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\Database;
use Contao\Database\Result;
use Contao\Database\Statement;
use Contao\Model\Collection;
use Contao\Model\Registry;
use Contao\PageModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Filesystem\Filesystem;

class PageModelTest extends ContaoTestCase
{
    use ExpectDeprecationTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->method('getIdentifierQuoteCharacter')
            ->willReturn('\'')
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('getDatabasePlatform')
            ->willReturn($platform)
        ;

        $connection
            ->method('quoteIdentifier')
            ->willReturnArgument(0)
        ;

        $container = $this->getContainerWithContaoConfiguration();
        $container->set('database_connection', $connection);
        $container->set('contao.security.token_checker', $this->createMock(TokenChecker::class));
        $container->setParameter('contao.resources_paths', $this->getTempDir());

        (new Filesystem())->mkdir($this->getTempDir().'/languages/en');

        System::setContainer($container);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Registry::getInstance()->reset();

        // Reset database instance
        $property = (new \ReflectionClass(Database::class))->getProperty('arrInstances');
        $property->setAccessible(true);
        $property->setValue([]);
    }

    public function testCreatingEmptyPageModel(): void
    {
        $pageModel = new PageModel();

        $this->assertNull($pageModel->id);
        $this->assertNull($pageModel->alias);
    }

    public function testCreatingPageModelFromArray(): void
    {
        $pageModel = new PageModel(['id' => '1', 'alias' => 'alias']);

        $this->assertSame('1', $pageModel->id);
        $this->assertSame('alias', $pageModel->alias);
    }

    public function testCreatingPageModelFromDatabaseResult(): void
    {
        $pageModel = new PageModel(new Result([['id' => '1', 'alias' => 'alias']], 'SELECT * FROM tl_page WHERE id = 1'));

        $this->assertSame('1', $pageModel->id);
        $this->assertSame('alias', $pageModel->alias);
    }

    public function testFindByPk(): void
    {
        $statement = $this->createMock(Statement::class);
        $statement
            ->method('execute')
            ->willReturn(new Result([['id' => '1', 'alias' => 'alias']], ''))
        ;

        $database = $this->createMock(Database::class);
        $database
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($statement)
        ;

        $this->mockDatabase($database);

        $pageModel = PageModel::findByPk(1);

        $this->assertSame('1', $pageModel->id);
        $this->assertSame('alias', $pageModel->alias);
    }

    /**
     * @group legacy
     */
    public function testFindPublishedSubpagesWithoutGuestsByPid(): void
    {
        $this->expectDeprecation('Since contao/core-bundle 4.9: %sfindPublishedSubpagesWithoutGuestsByPid() has been deprecated%s');

        $databaseResultData = [
            ['id' => '1', 'alias' => 'alias1', 'subpages' => '0'],
            ['id' => '2', 'alias' => 'alias2', 'subpages' => '3'],
            ['id' => '3', 'alias' => 'alias3', 'subpages' => '42'],
        ];

        $statement = $this->createMock(Statement::class);
        $statement
            ->method('execute')
            ->willReturn(new Result($databaseResultData, ''))
        ;

        $database = $this->createMock(Database::class);
        $database
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($statement)
        ;

        $this->mockDatabase($database);

        $pages = PageModel::findPublishedSubpagesWithoutGuestsByPid(1);

        $this->assertInstanceOf(Collection::class, $pages);
        $this->assertCount(3, $pages);

        $this->assertSame($databaseResultData[0]['id'], $pages->offsetGet(0)->id);
        $this->assertSame($databaseResultData[0]['alias'], $pages->offsetGet(0)->alias);
        $this->assertSame($databaseResultData[0]['subpages'], $pages->offsetGet(0)->subpages);
        $this->assertSame($databaseResultData[1]['id'], $pages->offsetGet(1)->id);
        $this->assertSame($databaseResultData[1]['alias'], $pages->offsetGet(1)->alias);
        $this->assertSame($databaseResultData[1]['subpages'], $pages->offsetGet(1)->subpages);
        $this->assertSame($databaseResultData[2]['id'], $pages->offsetGet(2)->id);
        $this->assertSame($databaseResultData[2]['alias'], $pages->offsetGet(2)->alias);
        $this->assertSame($databaseResultData[2]['subpages'], $pages->offsetGet(2)->subpages);
    }

    /**
     * @group legacy
     * @dataProvider similarAliasProvider
     */
    public function testFindSimilarByAlias(array $page, string $alias, array $rootData): void
    {
        PageModel::reset();

        $database = $this->createMock(Database::class);
        $database
            ->expects($this->once())
            ->method('execute')
            ->with("SELECT urlPrefix, urlSuffix FROM tl_page WHERE type='root'")
            ->willReturn(new Result($rootData, ''))
        ;

        $aliasStatement = $this->createMock(Statement::class);
        $aliasStatement
            ->expects($this->once())
            ->method('execute')
            ->with('%'.$alias.'%', $page['id'])
            ->willReturn(new Result([['id' => 42]], ''))
        ;

        $database
            ->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM tl_page WHERE tl_page.alias LIKE ? AND tl_page.id!=?')
            ->willReturn($aliasStatement)
        ;

        $this->mockDatabase($database);

        $sourcePage = $this->mockClassWithProperties(PageModel::class, $page);
        $result = PageModel::findSimilarByAlias($sourcePage);

        $this->assertInstanceOf(Collection::class, $result);

        /** @var PageModel $pageModel */
        $pageModel = $result->first();

        $this->assertSame(42, $pageModel->id);
    }

    public function similarAliasProvider(): \Generator
    {
        yield 'Use original alias without prefix and suffix' => [
            [
                'id' => 1,
                'alias' => 'foo',
                'urlPrefix' => '',
                'urlSuffix' => '',
            ],
            'foo',
            [],
        ];

        yield 'Strips prefix' => [
            [
                'id' => 1,
                'alias' => 'de/foo',
                'urlPrefix' => '',
                'urlSuffix' => '',
            ],
            'foo',
            [
                ['urlPrefix' => 'de', 'urlSuffix' => ''],
            ],
        ];

        yield 'Strips multiple prefixes' => [
            [
                'id' => 1,
                'alias' => 'switzerland/german/foo',
                'urlPrefix' => '',
                'urlSuffix' => '',
            ],
            'foo',
            [
                ['urlPrefix' => 'switzerland', 'urlSuffix' => ''],
                ['urlPrefix' => 'switzerland/german', 'urlSuffix' => ''],
            ],
        ];

        yield 'Strips the current prefix' => [
            [
                'id' => 1,
                'alias' => 'de/foo',
                'urlPrefix' => 'de',
                'urlSuffix' => '',
            ],
            'foo',
            [
                ['urlPrefix' => 'en', 'urlSuffix' => ''],
            ],
        ];

        yield 'Strips suffix' => [
            [
                'id' => 1,
                'alias' => 'foo.html',
                'urlPrefix' => '',
                'urlSuffix' => '',
            ],
            'foo',
            [
                ['urlPrefix' => '', 'urlSuffix' => '.html'],
            ],
        ];

        yield 'Strips multiple suffixes' => [
            [
                'id' => 1,
                'alias' => 'foo.php',
                'urlPrefix' => '',
                'urlSuffix' => '',
            ],
            'foo',
            [
                ['urlPrefix' => '', 'urlSuffix' => '.html'],
                ['urlPrefix' => '', 'urlSuffix' => '.php'],
            ],
        ];

        yield 'Strips the current suffix' => [
            [
                'id' => 1,
                'alias' => 'foo.html',
                'urlPrefix' => '',
                'urlSuffix' => '.html',
            ],
            'foo',
            [
                ['urlPrefix' => '', 'urlSuffix' => '.php'],
            ],
        ];
    }

    private function mockDatabase(Database $database): void
    {
        $property = (new \ReflectionClass($database))->getProperty('arrInstances');
        $property->setAccessible(true);
        $property->setValue([md5(implode('', [])) => $database]);

        $this->assertSame($database, Database::getInstance());
    }
}
