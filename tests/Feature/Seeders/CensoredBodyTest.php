<?php

use Database\Seeders\FilterData;
use Database\Seeders\RickAndMortyDialogue;

it('builds non-empty censored dialogue with no un-censored bad words', function (): void {
    foreach (range(1, 25) as $ignored) {
        $body = RickAndMortyDialogue::censoredBody(3, 6);

        expect($body)->toBeString()->not->toBeEmpty()
            ->and(FilterData::hasBadWords($body))->toBeFalse();
    }
});

it('produces a single line for a one-line body', function (): void {
    $body = RickAndMortyDialogue::censoredBody(1, 1);

    expect($body)->not->toContain("\n\n");
});

it('censors bad words regardless of case', function (): void {
    expect(FilterData::censor('That is SHIT and shit'))
        ->not->toContain('SHIT')
        ->not->toContain('shit');
});
