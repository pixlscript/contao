<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\DependencyInjection\Compiler;

use Contao\CoreBundle\DependencyInjection\Compiler\AddPackagesPass;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Util\PackageUtil;
use PackageVersions\Versions;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddPackagesPassTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testAddsThePackages(): void
    {
        $this->expectDeprecation('Since contao/core-bundle 4.13: Using the PackageUtil::getVersion() method has been deprecated %s.');

        $container = new ContainerBuilder();

        $pass = new AddPackagesPass();
        $pass->process($container);

        $this->assertTrue($container->hasParameter('kernel.packages'));

        $keys = array_keys(Versions::VERSIONS);
        $packages = $container->getParameter('kernel.packages');

        $this->assertIsArray($packages);
        $this->assertArrayHasKey($keys[0], $packages);
        $this->assertArrayHasKey($keys[1], $packages);
        $this->assertArrayHasKey($keys[2], $packages);
        $this->assertArrayNotHasKey('contao/test-bundle4', $packages);

        $this->assertSame(PackageUtil::getVersion($keys[0]), $packages[$keys[0]]);
        $this->assertSame(PackageUtil::getVersion($keys[1]), $packages[$keys[1]]);
        $this->assertSame(PackageUtil::getVersion($keys[2]), $packages[$keys[2]]);
    }
}
