<?php

namespace rikmeijer\Transpher\Relay;

use function \Functional\if_else, \Functional\first;
use rikmeijer\Transpher\Nostr\MessageFactory;
use rikmeijer\Transpher\Nostr\Event;

/**
 * Description of Subscriptions
 *
 * @author Rik Meijer <hello@rikmeijer.nl>
 */
 class Subscriptions {
    
    private static array $subscriptions = [];

    static function apply(Event $event): bool {
        if (empty(self::$subscriptions)) {
            return false;
        }
        return null !== first(self::$subscriptions, function(callable $subscription, string $subscriptionId) use ($event) {
            $to = $subscription($event);
            if ($to === false) {
                return false;
            }
            $to(MessageFactory::requestedEvent($subscriptionId, $event));
            $to(MessageFactory::eose($subscriptionId));
            return true;
        });
    }
    static function subscribe(Sender $relay, string $subscriptionId, array ...$prototypes) : Subscription {
        $matcher = new Subscription(...$prototypes);
        self::$subscriptions[$subscriptionId] = if_else($matcher, fn() => $relay, fn() => false);
        return $matcher;
    }
    static function unsubscribe(string $subscriptionId) : void {
        unset(self::$subscriptions[$subscriptionId]);
    }
    
}