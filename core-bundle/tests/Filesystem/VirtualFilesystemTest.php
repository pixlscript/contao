<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Filesystem;

use Contao\CoreBundle\Filesystem\Dbafs\DbafsManager;
use Contao\CoreBundle\Filesystem\Dbafs\UnableToResolveUuidException;
use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\MountManager;
use Contao\CoreBundle\Filesystem\VirtualFilesystem;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Tests\TestCase;
use Symfony\Component\Uid\Uuid;

class VirtualFilesystemTest extends TestCase
{
    private static Uuid $defaultUuid;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$defaultUuid = Uuid::v1();
    }

    public function testGetPrefix(): void
    {
        $virtualFilesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $this->createMock(DbafsManager::class),
            'some/prefix'
        );

        $this->assertSame('some/prefix', $virtualFilesystem->getPrefix());
    }

    public function testDefaultsToEmptyPrefix(): void
    {
        $mountManager = $this->createMock(MountManager::class);
        $mountManager
            ->expects($this->once())
            ->method('write')
            ->with('foo', 'bar')
        ;

        $virtualFilesystem = new VirtualFilesystem(
            $mountManager,
            $this->createMock(DbafsManager::class),
        );

        $this->assertSame('', $virtualFilesystem->getPrefix());

        $virtualFilesystem->write('foo', 'bar');
    }

    public function testRetReadOnly(): void
    {
        $filesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $this->createMock(DbafsManager::class),
            '',
            false
        );

        $readOnlyFilesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $this->createMock(DbafsManager::class),
            '',
            true
        );

        $this->assertFalse($filesystem->isReadOnly());
        $this->assertTrue($readOnlyFilesystem->isReadOnly());
    }

    /**
     * @dataProvider provideInvalidPaths
     */
    public function testPreventsEscapingBounds(string $path, string $message): void
    {
        $filesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $this->createMock(DbafsManager::class),
            'prefix'
        );

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage($message);

        $filesystem->read($path);
    }

    /**
     * @dataProvider provideInvalidPaths
     */
    public function testPreventsEscapingBoundsViaUUIDs(string $path, string $message): void
    {
        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->method('resolveUuid')
            ->with(self::$defaultUuid)
            ->willReturn($path)
        ;

        $filesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $dbafsManager,
            'prefix'
        );

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage($message);

        $filesystem->read(self::$defaultUuid);
    }

    public function provideInvalidPaths(): \Generator
    {
        yield 'relative path up' => [
            '../other/resource',
            'Virtual filesystem path \'../other/resource\' must not escape the filesystem boundary.',
        ];

        yield 'relative path with .. in the middle' => [
            'some/../../other/resource',
            'Virtual filesystem path \'../other/resource\' must not escape the filesystem boundary.',
        ];

        yield 'absolute path' => [
            '/some/place',
            'Virtual filesystem path \'/some/place\' cannot be absolute.',
        ];
    }

    /**
     * @dataProvider provideResourceExistsResults
     */
    public function testFileExistsWithUuid(bool $resourceExists): void
    {
        $uuid = self::$defaultUuid;

        $dbafsManager = $this->createMock(DbafsManager::class);
        $invocationMocker = $dbafsManager
            ->expects($this->once())
            ->method('resolveUuid')
            ->with($uuid, 'prefix')
        ;

        if (!$resourceExists) {
            $invocationMocker->willThrowException(new UnableToResolveUuidException($uuid));
        }

        $filesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $dbafsManager,
            'prefix'
        );

        $this->assertSame($resourceExists, $filesystem->fileExists($uuid));
    }

    public function provideResourceExistsResults(): \Generator
    {
        yield 'resource found' => [true];
        yield 'resource not found' => [false];
    }

    /**
     * @dataProvider provideInvalidAccessFlags
     */
    public function testFileExistsThrowsWithUuidAndInvalidAccessFlags(int $invalidAccessFlags): void
    {
        $filesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $this->createMock(DbafsManager::class)
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot use a UUID in combination with VirtualFilesystem::BYPASS_DBAFS to check if a file exists.');

        $filesystem->fileExists(Uuid::v1(), $invalidAccessFlags);
    }

    public function provideInvalidAccessFlags(): \Generator
    {
        yield 'bypass DBAFS' => [VirtualFilesystemInterface::BYPASS_DBAFS];
        yield 'bypass DBAFS, but still sync' => [VirtualFilesystemInterface::FORCE_SYNC | VirtualFilesystemInterface::BYPASS_DBAFS];
    }

    /**
     * @dataProvider provideResourceExistsResults
     */
    public function testFileExistsReadsFromMountManagerAndSyncsDbafs(bool $resourceExists): void
    {
        $mountManager = $this->mockMountManagerWithCall('fileExists', [], $resourceExists);

        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->expects($this->exactly(2))
            ->method('sync')
            ->with('prefix/path')
        ;

        $dbafsManager
            ->method('match')
            ->with('prefix/path')
            ->willReturn(false)
        ;

        $filesystem = new VirtualFilesystem($mountManager, $dbafsManager, 'prefix');

        $this->assertSame(
            $resourceExists,
            $filesystem->fileExists('path', VirtualFilesystemInterface::FORCE_SYNC)
        );

        $this->assertSame(
            $resourceExists,
            $filesystem->fileExists('path', VirtualFilesystemInterface::FORCE_SYNC | VirtualFilesystemInterface::BYPASS_DBAFS)
        );
    }

    /**
     * @dataProvider provideResourceExistsResults
     */
    public function testFileExistsReadsFromDbafsManager(bool $resourceExists): void
    {
        $mountManager = $this->createMock(MountManager::class);
        $mountManager
            ->expects($this->never())
            ->method('fileExists')
        ;

        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->method('match')
            ->with('prefix/path')
            ->willReturn(true)
        ;

        $dbafsManager
            ->method('resourceExists')
            ->with('prefix/path')
            ->willReturn($resourceExists)
        ;

        $filesystem = new VirtualFilesystem($mountManager, $dbafsManager, 'prefix');

        $this->assertSame($resourceExists, $filesystem->fileExists('path'));
    }

    public function testRead(): void
    {
        $mountManager = $this->mockMountManagerWithCall('read', [], 'foo');
        $filesystem = $this->getVirtualFilesystem($mountManager);

        $this->assertSame('foo', $filesystem->read('path'));
        $this->assertSame('foo', $filesystem->read(self::$defaultUuid));
    }

    public function testReadStream(): void
    {
        $resource = tmpfile();

        $mountManager = $this->mockMountManagerWithCall('readStream', [], $resource);
        $filesystem = $this->getVirtualFilesystem($mountManager);

        $this->assertSame($resource, $filesystem->readStream('path'));
        $this->assertSame($resource, $filesystem->readStream(self::$defaultUuid));

        fclose($resource);
    }

    public function testWrite(): void
    {
        $mountManager = $this->mockMountManagerWithCall('write', ['foo', ['some' => 'option']]);
        $filesystem = $this->getVirtualFilesystem($mountManager, ['prefix/path']);

        $filesystem->write('path', 'foo', ['some' => 'option']);
        $filesystem->write(self::$defaultUuid, 'foo', ['some' => 'option']);
    }

    public function testWriteStream(): void
    {
        $resource = tmpfile();

        $mountManager = $this->mockMountManagerWithCall('writeStream', [$resource, ['some' => 'option']]);
        $filesystem = $this->getVirtualFilesystem($mountManager, ['prefix/path']);

        $filesystem->writeStream('path', $resource, ['some' => 'option']);
        $filesystem->writeStream(self::$defaultUuid, $resource, ['some' => 'option']);

        fclose($resource);
    }

    public function testDelete(): void
    {
        $mountManager = $this->mockMountManagerWithCall('delete');
        $filesystem = $this->getVirtualFilesystem($mountManager, ['prefix/path']);

        $filesystem->delete('path');
        $filesystem->delete(self::$defaultUuid);
    }

    public function testDeleteDirectory(): void
    {
        $mountManager = $this->mockMountManagerWithCall('deleteDirectory');
        $filesystem = $this->getVirtualFilesystem($mountManager, ['prefix/path']);

        $filesystem->deleteDirectory('path');
        $filesystem->deleteDirectory(self::$defaultUuid);
    }

    public function testCreateDirectory(): void
    {
        $mountManager = $this->mockMountManagerWithCall('createDirectory', [['some' => 'option']]);
        $filesystem = $this->getVirtualFilesystem($mountManager, ['prefix/path']);

        $filesystem->createDirectory('path', ['some' => 'option']);
        $filesystem->createDirectory(self::$defaultUuid, ['some' => 'option']);
    }

    public function testCopy(): void
    {
        $mountManager = $this->mockMountManagerWithCall('copy', ['prefix/to/path', ['some' => 'option']]);
        $filesystem = $this->getVirtualFilesystem($mountManager, ['prefix/path', 'prefix/to/path']);

        $filesystem->copy('path', 'to/path', ['some' => 'option']);
        $filesystem->copy(self::$defaultUuid, 'to/path', ['some' => 'option']);
    }

    public function testMove(): void
    {
        $mountManager = $this->mockMountManagerWithCall('move', ['prefix/to/path', ['some' => 'option']]);
        $filesystem = $this->getVirtualFilesystem($mountManager, ['prefix/path', 'prefix/to/path']);

        $filesystem->move('path', 'to/path', ['some' => 'option']);
        $filesystem->move(self::$defaultUuid, 'to/path', ['some' => 'option']);
    }

    /**
     * @dataProvider provideMountManagerListings
     */
    public function testListContentsYieldsFromMountManager(bool $deep, array $listing, array $expected): void
    {
        $mountManager = $this->createMock(MountManager::class);
        $mountManager
            ->method('listContents')
            ->with('prefix/foo/bar', $deep)
            ->willReturn($this->getGenerator($listing))
        ;

        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->expects($this->never())
            ->method('listContents')
        ;

        $dbafsManager
            ->expects($this->once())
            ->method('getExtraMetadata')
            ->with('prefix/foo/bar/file')
            ->willReturn(['extra' => 'data'])
        ;

        $filesystem = new VirtualFilesystem($mountManager, $dbafsManager, 'prefix');

        /** @var array<FilesystemItem> $listedContents */
        $listedContents = [...$filesystem->listContents('foo/bar', $deep, VirtualFilesystemInterface::BYPASS_DBAFS)];

        $this->assertSame(
            ['extra' => 'data'],
            $listedContents[0]->getExtraMetadata()
        );

        // Normalize listing for comparison
        $listing = array_map(
            static fn (FilesystemItem $i): string => sprintf('%s (%s)', $i->getPath(), $i->isFile() ? 'file' : 'dir'),
            $listedContents
        );

        sort($listing);

        $this->assertSame($expected, $listing);
    }

    public function provideMountManagerListings(): \Generator
    {
        yield 'shallow' => [
            false,
            [
                new FilesystemItem(true, 'prefix/foo/bar/file'),
                new FilesystemItem(false, 'prefix/foo/bar/things'),
            ],
            [
                'foo/bar/file (file)',
                'foo/bar/things (dir)',
            ],
        ];

        yield 'deep' => [
            true,
            [
                new FilesystemItem(true, 'prefix/foo/bar/file'),
                new FilesystemItem(false, 'prefix/foo/bar/things'),
                new FilesystemItem(true, 'prefix/foo/bar/things/a'),
                new FilesystemItem(true, 'prefix/foo/bar/things/b'),
                new FilesystemItem(false, 'prefix/foo/bar/things/more'),
            ],
            [
                'foo/bar/file (file)',
                'foo/bar/things (dir)',
                'foo/bar/things/a (file)',
                'foo/bar/things/b (file)',
                'foo/bar/things/more (dir)',
            ],
        ];
    }

    /**
     * @dataProvider provideDbafsManagerListings
     */
    public function testListContentsYieldsFromDbafsManager(bool $deep, array $listing, array $expected): void
    {
        $mountManager = $this->createMock(MountManager::class);
        $mountManager
            ->expects($this->never())
            ->method('listContents')
        ;

        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->expects($this->once())
            ->method('match')
            ->with('prefix/foo/bar')
            ->willReturn(true)
        ;

        $dbafsManager
            ->method('listContents')
            ->with('prefix/foo/bar', $deep)
            ->willReturn($this->getGenerator($listing))
        ;

        $filesystem = new VirtualFilesystem($mountManager, $dbafsManager, 'prefix');

        /** @var array<FilesystemItem> $listedContents */
        $listedContents = [...$filesystem->listContents('foo/bar', $deep)];

        $this->assertSame(
            ['extra' => 'data'],
            $listedContents[0]->getExtraMetadata()
        );

        // Normalize listing for comparison
        $listing = array_map(
            static fn (FilesystemItem $i): string => sprintf('%s (%s)', $i->getPath(), $i->isFile() ? 'file' : 'dir'),
            $listedContents
        );

        sort($listing);

        $this->assertSame($expected, $listing);
    }

    public function provideDbafsManagerListings(): \Generator
    {
        yield 'shallow' => [
            false,
            [
                new FilesystemItem(
                    true,
                    'prefix/foo/bar/file',
                    0,
                    0,
                    '',
                    ['extra' => 'data']
                ),
                new FilesystemItem(false, 'prefix/foo/bar/things'),
            ],
            [
                'foo/bar/file (file)',
                'foo/bar/things (dir)',
            ],
        ];

        yield 'deep' => [
            true,
            [
                new FilesystemItem(
                    true,
                    'prefix/foo/bar/file',
                    0,
                    0,
                    '',
                    ['extra' => 'data']
                ),
                new FilesystemItem(false, 'prefix/foo/bar/things'),
                new FilesystemItem(true, 'prefix/foo/bar/things/a'),
                new FilesystemItem(true, 'prefix/foo/bar/things/b'),
                new FilesystemItem(false, 'prefix/foo/bar/things/more'),
            ],
            [
                'foo/bar/file (file)',
                'foo/bar/things (dir)',
                'foo/bar/things/a (file)',
                'foo/bar/things/b (file)',
                'foo/bar/things/more (dir)',
            ],
        ];
    }

    public function testListContentsSyncsDbafs(): void
    {
        $mountManager = $this->createMock(MountManager::class);

        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->method('match')
            ->with('prefix/foo/bar')
            ->willReturn(true)
        ;

        $dbafsManager
            ->expects($this->once())
            ->method('sync')
            ->with('prefix/foo/bar')
        ;

        $dbafsManager
            ->method('listContents')
            ->with('prefix/foo/bar', false)
            ->willReturn($this->getGenerator([]))
        ;

        $filesystem = new VirtualFilesystem($mountManager, $dbafsManager, 'prefix');
        $filesystem->listContents('foo/bar', false, VirtualFilesystemInterface::FORCE_SYNC);
    }

    /**
     * @dataProvider provideAccessFlags
     */
    public function testGetLastModified(int $accessFlags, bool $shouldSync, bool $shouldReadFromDbafs): void
    {
        $this->doTestGetMetadata(
            'lastModified',
            123450,
            $accessFlags,
            $shouldSync,
            $shouldReadFromDbafs
        );
    }

    /**
     * @dataProvider provideAccessFlags
     */
    public function testGetFileSize(int $accessFlags, bool $shouldSync, bool $shouldReadFromDbafs): void
    {
        $this->doTestGetMetadata(
            'fileSize',
            1024,
            $accessFlags,
            $shouldSync,
            $shouldReadFromDbafs
        );
    }

    /**
     * @dataProvider provideAccessFlags
     */
    public function testGetMimeType(int $accessFlags, bool $shouldSync, bool $shouldReadFromDbafs): void
    {
        $this->doTestGetMetadata(
            'mimeType',
            'image/png',
            $accessFlags,
            $shouldSync,
            $shouldReadFromDbafs
        );
    }

    /**
     * @dataProvider provideAccessFlags
     */
    public function testGetExtraMetadata(int $accessFlags, bool $shouldSync, bool $shouldReadFromDbafs): void
    {
        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->expects($this->once())
            ->method('resolveUuid')
            ->with(self::$defaultUuid, 'prefix')
            ->willReturn('path')
        ;

        $dbafsManager
            ->expects($shouldSync ? $this->exactly(2) : $this->never())
            ->method('sync')
            ->with('prefix/path')
        ;

        $dbafsManager
            ->expects($shouldReadFromDbafs ? $this->exactly(2) : $this->never())
            ->method('getExtraMetadata')
            ->with('prefix/path')
            ->willReturn(['extra' => 'data'])
        ;

        $filesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $dbafsManager,
            'prefix'
        );

        $expected = $shouldReadFromDbafs ? ['extra' => 'data'] : [];

        $this->assertSame($expected, $filesystem->getExtraMetadata('path', $accessFlags));
        $this->assertSame($expected, $filesystem->getExtraMetadata(self::$defaultUuid, $accessFlags));
    }

    public function provideAccessFlags(): \Generator
    {
        yield 'use DBAFS' => [
            VirtualFilesystemInterface::NONE, false, true,
        ];

        yield 'force sync' => [
            VirtualFilesystemInterface::FORCE_SYNC, true, true,
        ];

        yield 'bypass DBAFS' => [
            VirtualFilesystemInterface::BYPASS_DBAFS, false, false,
        ];

        yield 'bypass DBAFS, but still sync' => [
            VirtualFilesystemInterface::FORCE_SYNC | VirtualFilesystemInterface::BYPASS_DBAFS, true, false,
        ];
    }

    public function testSetExtraMetadata(): void
    {
        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->expects($this->once())
            ->method('resolveUuid')
            ->with(self::$defaultUuid, 'prefix')
            ->willReturn('path')
        ;

        $dbafsManager
            ->expects($this->exactly(2))
            ->method('setExtraMetadata')
            ->with('prefix/path', ['extra' => 'data'])
        ;

        $filesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $dbafsManager,
            'prefix'
        );

        $filesystem->setExtraMetadata('path', ['extra' => 'data']);
        $filesystem->setExtraMetadata(self::$defaultUuid, ['extra' => 'data']);
    }

    /**
     * @dataProvider provideReadOnlyMethods
     *
     * @param mixed ...$arguments
     */
    public function testDisallowsMutatingAReadOnlyFilesystem(...$arguments): void
    {
        $method = array_shift($arguments);

        $readOnlyFilesystem = new VirtualFilesystem(
            $this->createMock(MountManager::class),
            $this->createMock(DbafsManager::class),
            '',
            true
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Tried to mutate a readonly filesystem instance.');

        $readOnlyFilesystem->$method(...$arguments);
    }

    public function provideReadOnlyMethods(): \Generator
    {
        yield 'write' => [
            'write', 'foo/bar', 'content',
        ];

        yield 'writeStream' => [
            'write', 'foo/bar', 'stream-content',
        ];

        yield 'delete' => [
            'delete', 'foo/bar',
        ];

        yield 'delete directory' => [
            'deleteDirectory', 'foo/bar',
        ];

        yield 'create directory' => [
            'createDirectory', 'foo/bar',
        ];

        yield 'copy' => [
            'copy', 'foo/bar', 'foo/baz',
        ];

        yield 'move' => [
            'move', 'foo/bar', 'foo/baz',
        ];

        yield 'set extra metadata' => [
            'setExtraMetadata', 'foo/bar', ['some' => 'data'],
        ];
    }

    /**
     * @param mixed $value
     */
    private function doTestGetMetadata(string $property, $value, int $accessFlags, bool $shouldSync, bool $shouldReadFromDbafs): void
    {
        $method = sprintf('get%s', ucfirst($property));

        $mountManager = $this->createMock(MountManager::class);
        $mountManager
            ->method($method)
            ->willReturn($value)
        ;

        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->expects($this->once())
            ->method('resolveUuid')
            ->with(self::$defaultUuid, 'prefix')
            ->willReturn('path1')
        ;

        $dbafsManager
            ->expects($shouldSync ? $this->exactly(3) : $this->never())
            ->method('sync')
            ->with($this->callback(
                static fn (string $path) => \in_array($path, ['prefix/path1', 'prefix/path2'], true)
            ))
        ;

        $dbafsManager
            ->expects($shouldReadFromDbafs ? $this->exactly(3) : $this->never())
            ->method($method)
            ->willReturnMap([
                ['prefix/path1', $value],
                ['prefix/path2', null],
            ])
        ;

        $filesystem = new VirtualFilesystem($mountManager, $dbafsManager, 'prefix');

        $this->assertSame($value, $filesystem->$method(self::$defaultUuid, $accessFlags));
        $this->assertSame($value, $filesystem->$method('path1', $accessFlags));
        $this->assertSame($value, $filesystem->$method('path2', $accessFlags));
    }

    /**
     * @param mixed $return
     */
    private function mockMountManagerWithCall(string $method, array $additionalArguments = [], $return = null): MountManager
    {
        $mountManager = $this->createMock(MountManager::class);

        $invocationMocker = $mountManager
            ->expects($this->exactly(2))
            ->method($method)
            ->with('prefix/path', ...$additionalArguments)
        ;

        if (null !== $return) {
            $invocationMocker->willReturn($return);
        }

        return $mountManager;
    }

    private function getVirtualFilesystem(MountManager $mountManager, array $sync = null): VirtualFilesystem
    {
        $dbafsManager = $this->createMock(DbafsManager::class);
        $dbafsManager
            ->expects($this->once())
            ->method('resolveUuid')
            ->with(self::$defaultUuid, 'prefix')
            ->willReturn('path')
        ;

        $dbafsManager
            ->expects(null !== $sync ? $this->exactly(2) : $this->never())
            ->method('sync')
            ->with(...($sync ?? []))
        ;

        return new VirtualFilesystem($mountManager, $dbafsManager, 'prefix');
    }

    /**
     * @template T
     *
     * @param array<T> $array
     *
     * @return \Generator<T>
     */
    private function getGenerator(array $array): \Generator
    {
        foreach ($array as $item) {
            yield $item;
        }
    }
}
