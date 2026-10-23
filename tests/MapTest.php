<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\Tests\Fixtures\MapKernel;
use Omnibridge\Twig\MapExtension;
use Omnimap\MapPrinter;
use Omnimap\Registry;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * omnimap under Symfony through Omnibridge\Symfony\Map\ (omnimap.yaml): the
 * services, the page's map made by the server from its provider's tiles and
 * kept in the cache, a tile served as WebP and written in the public folder,
 * the metadata, and the capture and deletion of a picture - an
 * administrator's, with the page's token; refused to a visitor.
 */
final class MapTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return MapKernel::class;
    }

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD is not installed.');
        }
        $root = \dirname(__DIR__).'/var';
        foreach (['omnimap-test', 'omnimap-public'] as $dir) {
            (new DirectoryStorage($root))->deleteDirectory($dir);
        }
        MapKernel::$requests = 0;
        MapKernel::$userAgents = [];
        $this->client = static::createClient();
        $this->client->disableReboot();
        static::getContainer()->get('cache.app')->clear();
    }

    public function testTheServices(): void
    {
        $container = static::getContainer();

        self::assertInstanceOf(Registry::class, $container->get(Registry::class));
        self::assertSame(['openstreetmap'], $container->get(Registry::class)->names());
        self::assertInstanceOf(Snapshots::class, $container->get(Snapshots::class));
        self::assertTrue($container->get(Snapshots::class)->canMake(), 'OpenStreetMap makes its pictures: auto');
        self::assertInstanceOf(MapPrinter::class, $container->get(MapPrinter::class));
        self::assertTrue($container->get('twig')->hasExtension(MapExtension::class));
        $router = $container->get('router');
        self::assertSame('/google/abc/3.webp', $router->generate('gm_show', ['signature' => 'abc', 'id' => 3]));
        self::assertSame('/google/maps/abc/export', $router->generate('gm_export', ['signature' => 'abc']));
    }

    public function testAVisitorGetsThePictureTheServerMadeAndItsTiles(): void
    {
        $page = (string) $this->client->request('GET', '/map')->html();
        self::assertResponseIsSuccessful();
        $signature = (string) static::getContainer()->get(Snapshots::class)->signature(MapKernel::view());

        self::assertStringContainsString('<div class="google-tilemap" id="myMap_html2canvas" data-omnimap="snapshot" data-src="/google/%7Bsignature%7D/%7Bid%7D.webp" data-signature="'.$signature.'" data-tilesize="512" data-xtiles="2" data-ytiles="1" data-missing="" width="600" height="400">', $page);
        self::assertStringContainsString('OpenStreetMap</a> contributors</div>', $page, 'the attribution');
        self::assertStringNotContainsString('data-omnimap-export', $page, 'no button for a visitor');
        self::assertGreaterThan(0, MapKernel::$requests);
        self::assertSame(['User-Agent: omnibridge-tests/1.0 (+https://github.com/glitchr-studio/omnibridge-symfony)'], array_values(array_unique(MapKernel::$userAgents)));

        $requests = MapKernel::$requests;
        $this->client->request('GET', '/map');
        self::assertSame($requests, MapKernel::$requests, 'kept: made once');

        $this->client->request('GET', "/google/$signature/1.webp");
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'image/webp');
        $tile = (string) $this->client->getInternalResponse()->getContent();
        self::assertSame([500, 500, \IMAGETYPE_WEBP], \array_slice((array) getimagesizefromstring($tile), 0, 3), 'fitted within public_size');
        self::assertStringEqualsFile(\dirname(__DIR__)."/var/omnimap-public/google/$signature/1.webp", $tile, 'written where the web server serves it');
        self::assertStringContainsString('max-age=86400', (string) $this->client->getInternalResponse()->getHeader('cache-control'));

        $this->client->request('GET', "/google/$signature/9.webp");
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', "/google/maps/$signature");
        self::assertSame(['status' => 'OK', 'image_width' => 600, 'image_height' => 400, 'image_tilesize' => '512', 'image_xtiles' => 2, 'image_ytiles' => 1], json_decode((string) $this->client->getInternalResponse()->getContent(), true));

        $this->client->request('POST', "/google/maps/$signature/suppress", ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403, 'a visitor deletes nothing');
    }

    public function testAnAdministratorCapturesAndDeletes(): void
    {
        $this->client->loginUser(new InMemoryUser('ada', 'x', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/map');
        $signature = (string) static::getContainer()->get(Snapshots::class)->signature(MapKernel::view());
        $export = $crawler->filterXPath('//*[@data-omnimap-export]');
        $suppress = $crawler->filterXPath('//*[@data-omnimap-suppress]');
        self::assertSame("/google/maps/$signature/export", $export->attr('data-omnimap-export'));
        self::assertSame("/google/maps/$signature/suppress", $suppress->attr('data-omnimap-suppress'), 'a picture is kept (the server made it): it can be deleted');

        $this->client->request('GET', "/google/$signature/0.webp");
        self::assertFileExists(\dirname(__DIR__)."/var/omnimap-public/google/$signature/0.webp");

        $image = imagecreatetruecolor(1100, 700);
        ob_start();
        imagepng($image);
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
        $this->client->request('POST', "/google/maps/$signature/export", ['image' => $png, 'tilesize' => '512', '_token' => 'wrong']);
        self::assertResponseStatusCodeSame(403, 'the token is checked');
        $this->client->request('POST', "/google/maps/$signature/export", ['image' => $png, 'tilesize' => '512', '_token' => (string) $export->attr('data-omnimap-token')]);
        self::assertResponseIsSuccessful();
        self::assertSame([1100, 700, 3, 2], array_values(array_intersect_key((array) json_decode((string) $this->client->getInternalResponse()->getContent(), true), array_flip(['image_width', 'image_height', 'image_xtiles', 'image_ytiles']))));
        self::assertFileDoesNotExist(\dirname(__DIR__)."/var/omnimap-public/google/$signature/0.webp", 'the former tiles no longer served');

        $this->client->request('POST', "/google/maps/$signature/suppress", ['_token' => (string) $suppress->attr('data-omnimap-token')]);
        self::assertResponseIsSuccessful();
        self::assertNull(static::getContainer()->get(Snapshots::class)->store()->find($signature));
    }
}
