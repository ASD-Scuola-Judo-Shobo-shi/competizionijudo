<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Session;
use App\Security\CredentialFingerprint;
use App\Service\EventRegistrationSelection;
use PHPUnit\Framework\TestCase;

final class EventRegistrationSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        Session::destroy();
    }

    public function testSelectionsAndDeselectionsAccumulateAcrossPages(): void
    {
        $selection = new EventRegistrationSelection(201, 101);
        self::assertSame([], $selection->changes());
        self::assertNull($selection->optionId());
        self::assertSame(0, $selection->updatePage([301, 303], ['303'], [301, 400], null));
        self::assertSame(0, $selection->updatePage([400, 449], ['400', '449'], [301, 400], 501));

        $reloaded = new EventRegistrationSelection(201, 101);
        self::assertSame([301 => false, 303 => true, 449 => true], $reloaded->changes());
        self::assertSame([400, 303, 449], $reloaded->selectedAthleteIds([301, 400]));
        self::assertSame(501, $reloaded->optionId());

        // Returning to a page and reverting it leaves the other page's draft intact.
        $reloaded->updatePage([301, 303], ['301'], [301, 400], 501);
        self::assertSame([449 => true], $selection->changes());
        self::assertSame([301, 400, 449], $selection->selectedAthleteIds([301, 400]));
    }

    public function testOnlyServerResolvedPageIdsCanBeSelected(): void
    {
        $selection = new EventRegistrationSelection(201, 101);
        $rejected = $selection->updatePage(
            [301],
            ['301', '301', '302', '449', 'invalid', ['301']],
            [],
            501
        );

        self::assertSame(4, $rejected);
        self::assertSame([301], $selection->selectedAthleteIds([]));

        self::assertSame(0, $selection->updatePage([301], '301', [], null));
        self::assertSame([301], $selection->selectedAthleteIds([]));
        self::assertNull($selection->optionId());
    }

    public function testDraftsAreScopedToTheClubEventAndAuthenticationSession(): void
    {
        $selection = new EventRegistrationSelection(201, 101);
        $selection->updatePage([301], ['301'], [], 501);

        self::assertSame([], (new EventRegistrationSelection(202, 101))->changes());
        self::assertNull((new EventRegistrationSelection(202, 101))->optionId());
        self::assertSame([], (new EventRegistrationSelection(201, 102))->changes());
        self::assertNull((new EventRegistrationSelection(201, 102))->optionId());

        Session::authenticateClub(202, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));
        self::assertSame([], $selection->changes());
        self::assertNull($selection->optionId());
    }

    public function testClearingADraftRestoresPersistedSelections(): void
    {
        $selection = new EventRegistrationSelection(201, 101);
        $selection->updatePage([301, 303], ['303'], [301], 501);
        $selection->clear();

        self::assertSame([], $selection->changes());
        self::assertSame([301], $selection->selectedAthleteIds([301]));
        self::assertNull($selection->optionId());
    }
}
