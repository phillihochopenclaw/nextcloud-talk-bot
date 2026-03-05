<?php

declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Basic unit test to verify test setup
 */
class BasicTest extends TestCase
{
    public function testTrue(): void
    {
        $this->assertTrue(true);
    }

    public function testPhpVersion(): void
    {
        $this->assertGreaterThanOrEqual(8.1, (float) PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
    }

    public function testStringFunctions(): void
    {
        $this->assertTrue(str_starts_with('hello world', 'hello'));
        $this->assertTrue(str_ends_with('hello world', 'world'));
        $this->assertEquals('hello', str_contains('hello world', 'hello') ? 'hello' : 'no');
    }
}