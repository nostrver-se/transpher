<?php

namespace nostriphant\Transpher\Relay;

use nostriphant\Transpher\Relay\Store;
use nostriphant\Transpher\Relay\Subscriptions;
use nostriphant\Transpher\Nostr\Message;

readonly class Incoming {

    public function __construct(private Store $events, private string $files) {
        
    }

    public function __invoke(Subscriptions $subscriptions, Message $message): \Generator {
        yield from (match (strtoupper($message->type)) {
                    'EVENT' => new Incoming\Event(new Incoming\Event\Accepted($this->events, $this->files, $subscriptions), Incoming\Event\Limits::fromEnv()),
                    'CLOSE' => new Incoming\Close($subscriptions),
                    'REQ' => new Incoming\Req(new Incoming\Req\Accepted($this->events, $subscriptions), Incoming\Req\Limits::fromEnv()),
                    'COUNT' => new Incoming\Count($this->events, Incoming\Count\Limits::fromEnv()),
                    default => new Incoming\Unknown($message->type)
                })($message->payload);
    }
}
