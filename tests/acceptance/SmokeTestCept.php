<?php
/** @group Smoke */
$I = new AcceptanceTester($scenario);
$I->wantToTest('Acceptance suite can load the homepage');
$I->comment('Concept: The site answers a browser request and finishes rendering');
$I->amOnPage('/');
// `body` exists the moment parsing starts, so waiting on it proves
// nothing. Wait for the document to actually finish loading.
$I->waitForJS('return document.readyState === "complete"', 10);
$I->seeElement('body');