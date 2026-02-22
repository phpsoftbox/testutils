<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Traits;

use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\TestUtils\Traits\InertiaTestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class InertiaTestTraitTest extends TestCase
{
    use InertiaTestTrait;

    /**
     * Проверяет, что assertInertiaArea читает area из props app.area.
     */
    #[Test]
    public function assertInertiaAreaChecksSharedAreaProp(): void
    {
        $response = new Response(200, body: json_encode([
            'component' => 'Admin/Dashboard',
            'props'     => [
                'app' => [
                    'area' => 'admin',
                ],
            ],
            'url'     => '/dashboard',
            'version' => null,
        ], JSON_THROW_ON_ERROR));

        $this->assertInertiaArea($response, 'admin');
    }

    /**
     * Проверяет, что assertInertiaProp умеет читать вложенные props.
     */
    #[Test]
    public function assertInertiaPropChecksNestedProp(): void
    {
        $response = new Response(200, body: json_encode([
            'component' => 'Web/Home',
            'props'     => [
                'user' => [
                    'name' => 'Anton',
                ],
            ],
            'url'     => '/',
            'version' => null,
        ], JSON_THROW_ON_ERROR));

        $this->assertInertiaProp($response, 'user.name', 'Anton');
    }
}
