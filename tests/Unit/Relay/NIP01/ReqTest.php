<?php

use rikmeijer\Transpher\Relay;
use rikmeijer\Transpher\Nostr\Key;
use rikmeijer\Transpher\Nostr\Message\Factory;
use rikmeijer\Transpher\Nostr\Subscription\Filter;
use rikmeijer\TranspherTests\Unit\Client;
use function Pest\context;

describe('REQ', function () {
    it('replies NOTICE Invalid message on non-existing filters', function () {
        $context = context();

        Relay::handle(json_encode(['REQ']), $context);

        expect($context->relay)->toHaveReceived(
                ['NOTICE', 'Invalid message']
        );
    });
    it('replies CLOSED on empty filters', function () {
        $context = context();

        Relay::handle(json_encode(['REQ', $id = uniqid(), []]), $context);

        expect($context->relay)->toHaveReceived(
                ['CLOSED', $id, 'Subscription filters are empty']
        );
    });
    it('can handle a subscription request, for non existing events', function () {
        $context = context();

        Relay::handle(json_encode(['REQ', $id = uniqid(), ['ids' => ['abdcd']]]), $context);

        expect($context->relay)->toHaveReceived(
                ['EOSE', $id]
        );
    });

    it('can handle a subscription request, for existing events', function () {
        $context = context();

        $sender_key = Key::generate();
        $event = \rikmeijer\Transpher\Nostr\Message\Factory::event($sender_key, 1, 'Hello World');
        Relay::handle($event, $context);

        Relay::handle(json_encode(['REQ', $id = uniqid(), ['authors' => [$sender_key(Key::public())]]]), $context);

        expect($context->relay)->toHaveReceived(
                ['OK'],
                ['EVENT', $id, function (array $event) {
                        expect($event['content'])->toBe('Hello World');
                    }],
                ['EOSE', $id]
        );
    });

    it('sends events to all clients subscribed on event id', function () {
        $alice = Client::generic_client();
        $bob = Client::generic_client();

        $alice_key = Key::generate();
        $alice->sendSignedMessage(Factory::event($alice_key, 1, 'Hello worlda!'));

        $key_charlie = Key::generate();
        $note2 = Factory::event($key_charlie, 1, 'Hello worldi!');
        $alice->sendSignedMessage($note2);

        $subscription = Factory::subscribe(
                new Filter(ids: [$note2()[1]['id']])
        );

        $bob->expectNostrEvent($subscription()[1], 'Hello worldi!');
        $bob->expectNostrEose($subscription()[1]);
        $bob->json($subscription());
        $bob->start();
    });

    it('sends events to all clients subscribed on author (pubkey)', function () {
        $alice = Client::generic_client();
        $bob = Client::generic_client();

        $alice_key = Key::generate();
        $alice->sendSignedMessage(Factory::event($alice_key, 1, 'Hello world!'));
        $subscription = Factory::subscribe(
                new Filter(authors: [$alice_key(Key::public())])
        );

        $bob->expectNostrEvent($subscription()[1], 'Hello world!');
        $bob->expectNostrEose($subscription()[1]);

        $bob->json($subscription());
        $bob->start();
    });

    it('sends events to Charly who uses two filters in their subscription', function () {
        $alice = Client::generic_client();
        $bob = Client::generic_client();
        $charlie = Client::generic_client();

        $alice_key = Key::generate();
        $alice->sendSignedMessage(Factory::event($alice_key, 1, 'Hello world, from Alice!'));

        $bob_key = Key::generate();
        $alice->sendSignedMessage(Factory::event($bob_key, 1, 'Hello world, from Bob!'));

        $subscription = Factory::subscribe(
                new Filter(authors: [$alice_key(Key::public())]),
                new Filter(authors: [$bob_key(Key::public())])
        );
        $charlie->expectNostrEvent($subscription()[1], 'Hello world, from Alice!');
        $charlie->expectNostrEvent($subscription()[1], 'Hello world, from Bob!');
        $charlie->expectNostrEose($subscription()[1]);

        $charlie->json($subscription());
        $charlie->start();
    });

    it('sends events to all clients subscribed on p-tag', function () {
        $alice = Client::generic_client();
        $bob = Client::generic_client();
        $alice_key = Key::generate();

        $alice->sendSignedMessage(Factory::event($alice_key, 1, 'Hello world!', ['p', 'randomPTag']));
        $subscription = Factory::subscribe(
                new Filter(tags: ['#p' => ['randomPTag']])
        );

        $bob->expectNostrEvent($subscription()[1], 'Hello world!');
        $bob->expectNostrEose($subscription()[1]);

        $bob->json($subscription());
        $bob->start();
    });

    it('closes subscription and stop sending events to subscribers', function () {
        $alice = Client::generic_client();
        $bob = Client::generic_client();
        $alice_key = Key::generate();

        $alice->sendSignedMessage(Factory::event($alice_key, 1, 'Hello world!'));

        $subscription = Factory::subscribe(
                new Filter(authors: [$alice_key(Key::public())])
        );
        $bob->expectNostrEvent($subscription()[1], 'Hello world!');
        $bob->expectNostrEose($subscription()[1]);

        $bob->json($subscription());
        $bob->start();

        $bob->expectNostrClosed($subscription()[1], '');

        $request = Factory::close($subscription()[1]);
        $bob->send($request);
        $bob->start();
    });

    it('sends events to all clients subscribed on kind', function () {
        $alice = Client::generic_client();
        $bob = Client::generic_client();
        $alice_key = Key::generate();

        $alice->sendSignedMessage(Factory::event($alice_key, 3, 'Hello world!'));

        $subscription = Factory::subscribe(
                new Filter(kinds: [3])
        );

        $bob->expectNostrEvent($subscription()[1], 'Hello world!');
        $bob->expectNostrEose($subscription()[1]);

        $bob->json($subscription());
        $bob->start();
    });

    it('relays events to Bob, sent after they subscribed on Alices messages', function () {
        $alice = Client::generic_client();
        $bob = Client::generic_client();
        $alice_key = Key::generate();

        $subscription = Factory::subscribe(
                new Filter(authors: [$alice_key(Key::public())])
        );

        $bob->expectNostrEose($subscription()[1]);
        $bob->json($subscription());
        $bob->start();

        $alice->sendSignedMessage(Factory::event($alice_key, 1, 'Relayable Hello worlda!'));

        $key_charlie = Key::generate();
        $alice->sendSignedMessage(Factory::event($key_charlie, 1, 'Hello worldi!'));

        $bob->expectNostrEvent($subscription()[1], 'Relayable Hello worlda!');
        $bob->expectNostrEose($subscription()[1]);
        $bob->start();
    });

    it('sends events to all clients subscribed on author (pubkey), even after restarting the server', function () {
        $transpher_store = ROOT_DIR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . uniqid();
        mkdir($transpher_store);

        $alice = Client::persistent_client($transpher_store);

        $alice_key = Key::generate();
        $alice->sendSignedMessage($alice_event = Factory::event($alice_key, 1, 'Hello wirld!'));

        $event_file = $transpher_store . DIRECTORY_SEPARATOR . $alice_event()[1]['id'] . '.php';
        expect(is_file($event_file))->toBeTrue($event_file);

        $bob = Client::persistent_client($transpher_store);
        $subscription = Factory::subscribe(
                new Filter(authors: [$alice_key(Key::public())])
        );

        $bob->expectNostrEvent($subscription()[1], 'Hello wirld!');
        $bob->expectNostrEose($subscription()[1]);

        $bob->json($subscription());
        $bob->start();

        unlink($event_file);
        rmdir($transpher_store);
    });
});
