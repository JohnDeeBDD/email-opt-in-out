<?php

/**
 * The shared action-code library (library/).
 *
 * These checks need no WordPress and no database: the library is plain PHP, so
 * that the Gmail Campaign Manager can call it from outside WordPress and build
 * codes this plugin will decode. The check list lives in src/library/selftest.php
 * so the same assertions can be run on the server with `php
 * src/library/selftest.php`, without a test framework.
 *
 * If any of these fail, links already in recipients' inboxes have stopped
 * decoding. There is no migration for that.
 *
 * @group Library
 */
class LibraryCest
{
    /** @var array[] */
    private $results;

    public function _before(): void
    {
        require_once __DIR__ . '/../../src/library/selftest.php';

        $this->results = aiplugin5055_library_selftest();
    }

    public function theLibraryLoadsWithoutWordPress(PhpunitTester $I): void
    {
        $I->assertTrue(defined('AIPLUGIN5055_LIBRARY_VERSION'), 'The library declares its contract version');

        foreach (
            [
                'aiplugin5055\Library\ActionCode',
                'aiplugin5055\Library\ActionUrls',
                'aiplugin5055\Library\CampaignCode',
                'aiplugin5055\Library\EmailCodec',
                'aiplugin5055\Library\SiteSettings',
            ] as $class
        ) {
            $I->assertTrue(class_exists($class), $class . ' is defined');
        }
    }

    public function everyCheckInTheSelfTestPasses(PhpunitTester $I): void
    {
        $I->assertNotEmpty($this->results, 'The self-test ran');

        foreach ($this->results as $result) {
            $I->assertTrue(
                $result['ok'],
                $result['name'] . ('' === $result['detail'] ? '' : ' -- ' . $result['detail'])
            );
        }
    }

    /**
     * The vectors are the point of the whole file: they were captured before
     * the plugin and the campaign manager were merged onto this library, and
     * the campaign manager pins the same values from the other side.
     */
    public function theCommittedVectorsAreCovered(PhpunitTester $I): void
    {
        $vectors = array_filter(
            $this->results,
            static function ($result) {
                return 0 === strpos($result['name'], 'vector ');
            }
        );

        $I->assertGreaterThan(20, count($vectors), 'The vector table is still being checked');
    }
}
