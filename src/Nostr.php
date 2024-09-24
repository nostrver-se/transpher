<?php

namespace Transpher;
use \Transpher\Key;

/**
 * Description of Nostr
 *
 * @author Rik Meijer <hello@rikmeijer.nl>
 */
class Nostr {
    
    static function encode(mixed $json) : string {
        return json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    static function eose(string $subscriptionId) : array {
        return ['EOSE', $subscriptionId];
    }
    static function ok(string $eventId, bool $accepted, string $message = '') : array {
        return ['OK', $eventId, $accepted, $message];
    }
    static function accept(string $eventId, string $message = '') : array {
        return self::ok($eventId, true, $message);
    }
    static function closed(string $subscriptionId, string $message = '') : array {
        return ['CLOSED', $subscriptionId, $message];
    }
    static function notice(string $message) : array {
        return ['NOTICE', $message];
    }
    static function event(Key $private_key, int $created_at, int $kind, array $tags, string $content) : array {
        $id = hash('sha256', self::encode([0, $private_key(Key::public()), $created_at, $kind, $tags, $content]));
        return ['EVENT', [
            "id" => $id,
            "pubkey" => $private_key(Key::public()),
            "created_at" => $created_at,
            "kind" => $kind,
            "tags" => $tags,
            "content" => $content,
            "sig" => $private_key(Key::signer($id))
        ]];
    }
    static function subscribedEvent(string $subscriptionId, array $event) {
        return ['EVENT', $subscriptionId, $event];
    }
    
    static function seal(Key $sender_private_key, string $recipient_pubkey, array $event) {
        $conversation_key = Nostr\NIP44::getConversationKey($sender_private_key, hex2bin($recipient_pubkey));
        $encrypted_direct_message = Nostr\NIP44::encrypt(json_encode($event), $conversation_key, random_bytes(32));
        return self::event($sender_private_key, mktime(rand(0,23), rand(0,59), rand(0,59)), 1059, [], $encrypted_direct_message);
    }
}