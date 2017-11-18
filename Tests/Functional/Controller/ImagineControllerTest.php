<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\Tests\Functional\Controller;

use Liip\ImagineBundle\Controller\ImagineController;
use Liip\ImagineBundle\Imagine\Cache\Signer;
use Liip\ImagineBundle\Tests\Functional\AbstractSetupWebTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @covers \Liip\ImagineBundle\Controller\ImagineController
 */
class ImagineControllerTest extends AbstractSetupWebTestCase
{
    public function testCouldBeGetFromContainer()
    {
        $this->assertInstanceOf(ImagineController::class, self::$kernel->getContainer()->get('liip_imagine.controller'));
    }

    public function testShouldResolvePopulatingCacheFirst()
    {
        //guard
        $this->assertFileNotExists($this->cacheRoot.'/profile_thumb_sm/images/cats.jpeg');

        $this->client->request('GET', '/media/cache/resolve/profile_thumb_sm/images/cats.jpeg');

        $response = $this->client->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('http://localhost/media/cache/thumbnail_web_path/images/cats.jpeg', $response->getTargetUrl());

        $this->assertFileExists($this->cacheRoot.'/profile_thumb_sm/images/cats.jpeg');
    }

    public function testShouldResolveFromCache()
    {
        $this->filesystem->dumpFile(
            $this->cacheRoot.'/profile_thumb_sm/images/cats.jpeg',
            'anImageContent'
        );

        $this->client->request('GET', '/media/cache/resolve/profile_thumb_sm/images/cats.jpeg');

        $response = $this->client->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('http://localhost/media/cache/thumbnail_web_path/images/cats.jpeg', $response->getTargetUrl());

        $this->assertFileExists($this->cacheRoot.'/profile_thumb_sm/images/cats.jpeg');
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage Signed url does not pass the sign check for path "images/cats.jpeg" and filter "profile_thumb_sm" and runtime config {"thumbnail":{"size":["50","50"]}}
     */
    public function testThrowBadRequestIfSignInvalidWhileUsingCustomFilters()
    {
        $this->client->request('GET', '/media/cache/resolve/profile_thumb_sm/rc/invalidHash/images/cats.jpeg?'.http_build_query(array(
            'filters' => array(
                'thumbnail' => array('size' => array(50, 50)),
            ),
            '_hash' => 'invalid',
        )));
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @expectedExceptionMessage Filters must be an array. Value was "some-string"
     */
    public function testShouldThrowNotFoundHttpExceptionIfFiltersNotArray()
    {
        $this->client->request('GET', '/media/cache/resolve/profile_thumb_sm/rc/invalidHash/images/cats.jpeg?'.http_build_query(array(
            'filters' => 'some-string',
            '_hash' => 'hash',
        )));
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @expectedExceptionMessage Source image could not be found
     */
    public function testShouldThrowNotFoundHttpExceptionIfFileNotExists()
    {
        $this->client->request('GET', '/media/cache/resolve/profile_thumb_sm/images/shrodinger_cats_which_not_exist.jpeg');
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testInvalidFilterShouldThrowNotFoundHttpException()
    {
        $this->client->request('GET', '/media/cache/resolve/invalid-filter/images/cats.jpeg');
    }

    public function testShouldResolveWithCustomFiltersPopulatingCacheFirst()
    {
        /** @var Signer $signer */
        $signer = self::$kernel->getContainer()->get('liip_imagine.cache.signer');

        $params = array(
            'filters' => array(
                'thumbnail' => array('size' => array(50, 50)),
            ),
        );

        $path = 'images/cats.jpeg';

        $hash = $signer->sign($path, $params['filters']);

        $expectedCachePath = 'profile_thumb_sm/rc/'.$hash.'/'.$path;

        $url = 'http://localhost/media/cache/resolve/'.$expectedCachePath.'?'.http_build_query($params);

        //guard
        $this->assertFileNotExists($this->cacheRoot.'/'.$expectedCachePath);

        $this->client->request('GET', $url);

        $response = $this->client->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('http://localhost/media/cache/'.$expectedCachePath, $response->getTargetUrl());

        $this->assertFileExists($this->cacheRoot.'/'.$expectedCachePath);
    }

    public function testShouldResolveWithCustomFiltersFromCache()
    {
        /** @var Signer $signer */
        $signer = self::$kernel->getContainer()->get('liip_imagine.cache.signer');

        $params = array(
            'filters' => array(
                'thumbnail' => array('size' => array(50, 50)),
            ),
        );

        $path = 'images/cats.jpeg';

        $hash = $signer->sign($path, $params['filters']);

        $expectedCachePath = 'profile_thumb_sm/rc/'.$hash.'/'.$path;

        $url = 'http://localhost/media/cache/resolve/'.$expectedCachePath.'?'.http_build_query($params);

        $this->filesystem->dumpFile(
            $this->cacheRoot.'/'.$expectedCachePath,
            'anImageContent'
        );

        $this->client->request('GET', $url);

        $response = $this->client->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('http://localhost/media/cache'.'/'.$expectedCachePath, $response->getTargetUrl());

        $this->assertFileExists($this->cacheRoot.'/'.$expectedCachePath);
    }

    public function testShouldResolvePathWithSpecialCharactersAndWhiteSpaces()
    {
        $this->filesystem->dumpFile(
            $this->cacheRoot.'/profile_thumb_sm/images/foo bar.jpeg',
            'anImageContent'
        );

        // we are calling url with encoded file name as it will be called by browser
        $urlEncodedFileName = 'foo+bar';
        $this->client->request('GET', '/media/cache/resolve/profile_thumb_sm/images/'.$urlEncodedFileName.'.jpeg');

        $response = $this->client->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('http://localhost/media/cache/thumbnail_web_path/images/foo bar.jpeg', $response->getTargetUrl());

        $this->assertFileExists($this->cacheRoot.'/profile_thumb_sm/images/foo bar.jpeg');
    }
}
