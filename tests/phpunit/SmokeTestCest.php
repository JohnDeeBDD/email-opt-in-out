<?php

/**
 * @group Smoke
 */
class SmokeTestCest
{
    public function suiteIsOperational(PhpunitTester $I): void
    {
        $I->assertTrue(true, 'PHPUnit suite can execute assertions');
    }
}