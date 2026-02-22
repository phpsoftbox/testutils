<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Traits;

use PhpSoftBox\TestUtils\Snapshot\JsonSnapshotAssert;
use PhpSoftBox\TestUtils\Snapshot\SnapshotConfig;
use Psr\Http\Message\ResponseInterface;

use function array_diff;
use function array_key_exists;
use function array_values;
use function ctype_digit;
use function explode;
use function in_array;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @mixin \PHPUnit\Framework\TestCase
 */
trait InertiaTestTrait
{
    protected function assertInertiaComponent(ResponseInterface $response, string $component): void
    {
        $payload = $this->inertiaPayload($response);

        $this->assertSame($component, $payload['component'] ?? null);
    }

    protected function assertInertiaArea(ResponseInterface $response, string $area, string $propPath = 'app.area'): void
    {
        $this->assertInertiaProp($response, $propPath, $area);
    }

    protected function assertInertiaProp(ResponseInterface $response, string $path, mixed $expected): void
    {
        $payload = $this->inertiaPayload($response);

        $this->assertSame($expected, $this->payloadValue($payload, 'props.' . $path));
    }

    /**
     * @param array<int, string> $excludedKeys
     */
    protected function assertInertiaSnapshot(
        ResponseInterface $response,
        string $snapshotName,
        string $basePath,
        array $excludedKeys = ['id'],
    ): void {
        $payload = $this->inertiaPayload($response);

        if (in_array('id', $excludedKeys, true)) {
            $payload      = $this->dropKeysRecursive($payload, ['id']);
            $excludedKeys = array_values(array_diff($excludedKeys, ['id']));
        }

        $config = SnapshotConfig::forTestClass(
            $basePath,
            static::class,
        )->withExcludedKeys($excludedKeys);

        new JsonSnapshotAssert()->assertMatchesSnapshot($payload, $snapshotName, $config);
    }

    /**
     * @return array<string, mixed>
     */
    protected function inertiaPayload(ResponseInterface $response): array
    {
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    private function dropKeysRecursive(array $data, array $keys): array
    {
        foreach ($data as $key => $value) {
            if (in_array((string) $key, $keys, true)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->dropKeysRecursive($value, $keys);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadValue(array $payload, string $path): mixed
    {
        $current = $payload;
        foreach (explode('.', $path) as $segment) {
            $key = $this->normalizePayloadSegment($segment);
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }

            $current = $current[$key];
        }

        return $current;
    }

    private function normalizePayloadSegment(string $segment): int|string
    {
        if ($segment !== '' && ctype_digit($segment)) {
            return (int) $segment;
        }

        return $segment;
    }
}
