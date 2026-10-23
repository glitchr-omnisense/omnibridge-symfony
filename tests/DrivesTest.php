<?php

namespace Omnibridge\Symfony\Tests;

use League\Flysystem\Filesystem;
use Omnibridge\Symfony\Tests\Fixtures\TestKernel;
use Omnidrive\Drives;
use Omnidrive\Flysystem\FlysystemDrive;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The host's storages as omnidrive's drives: Omnidrive\Drives over the
 * services tagged flysystem.storage (what league/flysystem-bundle tags each
 * storage of its configuration with), by their storage names; a file written
 * through the drive is in the host's own storage.
 */
final class DrivesTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheTaggedStoragesAreTheDrivesByTheirNames(): void
    {
        if (!interface_exists(Drives::class)) {
            self::markTestSkipped('omniscient/omnidrive is not installed.');
        }
        $container = self::bootKernel()->getContainer();

        $drives = $container->get(Drives::class);
        self::assertInstanceOf(Drives::class, $drives);
        self::assertSame(['memory.uploads'], $drives->names());
        $drive = $drives->get('memory.uploads');
        self::assertInstanceOf(FlysystemDrive::class, $drive);
        self::assertSame('memory.uploads', $drive->name());

        $drive->write('avatars/1.txt', 'ringo');
        $operator = self::getContainer()->get('test.storage.uploads');
        self::assertInstanceOf(Filesystem::class, $operator);
        self::assertSame('ringo', $operator->read('avatars/1.txt'), 'the host\'s own storage');
        self::assertFalse($drives->has('s3.uploads'));
    }
}
