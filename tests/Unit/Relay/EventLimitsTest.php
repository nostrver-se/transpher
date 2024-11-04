<?php

use nostriphant\Transpher\Relay\Incoming\Event\Limits;
use nostriphant\Transpher\Relay\Incoming\Constraint\Result;

it('can be configured for event kinds to always allow. Leave empty to allow any.', function () {
    $limits = new Limits(
            kind_whitelist: [1]
    );

    $limit = $limits(\Pest\rumor(kind: 1, pubkey: \Pest\pubkey_sender())(\Pest\key_sender()));
    expect($limit->result)->toBe(Result::ACCEPTED, $limit->reason ?? '');

    $limit = $limits(\Pest\rumor(kind: 2, pubkey: \Pest\pubkey_sender())(\Pest\key_sender()));
    expect($limit->result)->toBe(Result::REJECTED, $limit->reason ?? 'event kind is not whitelisted');
});

it('can be configured for event kinds to always deny. Leave empty to allow any.', function () {
    $limits = new Limits(
            kind_blacklist: [1]
    );

    $limit = $limits(\Pest\rumor(kind: 1, pubkey: \Pest\pubkey_sender())(\Pest\key_sender()));
    expect($limit->result)->toBe(Result::REJECTED, $limit->reason ?? 'event kind is blacklisted');

    $limit = $limits(\Pest\rumor(kind: 2, pubkey: \Pest\pubkey_sender())(\Pest\key_sender()));
    expect($limit->result)->toBe(Result::ACCEPTED, $limit->reason ?? '');
});


it('can be configured for event content max limit.', function () {
    $limits = new Limits(
            content_maxlength: 10
    );

    $limit = $limits(\Pest\rumor(kind: 1, content: str_repeat('a', 11), pubkey: \Pest\pubkey_sender())(\Pest\key_sender()));
    expect($limit->result)->toBe(Result::REJECTED, $limit->reason ?? 'content is longer than 10 bytes');

    $limit = $limits(\Pest\rumor(kind: 1, pubkey: \Pest\pubkey_sender())(\Pest\key_sender()));
    expect($limit->result)->toBe(Result::ACCEPTED, $limit->reason ?? '');
});
