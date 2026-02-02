<?php

declare(strict_types=1);

namespace MoySklad\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for SDK tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Get path to fixtures directory
     */
    protected function getFixturesPath(): string
    {
        return __DIR__ . '/../fixtures';
    }

    /**
     * Load JSON fixture file
     */
    protected function loadFixture(string $filename): array
    {
        $path = $this->getFixturesPath() . '/' . $filename;
        
        if (!file_exists($path)) {
            $this->fail("Fixture file not found: {$path}");
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail("Failed to parse JSON fixture: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Get Prism base URL from environment
     */
    protected function getPrismBaseUrl(): string
    {
        return $_ENV['PRISM_BASE_URL'] ?? 'http://localhost:4010';
    }
}
