<?php

namespace rikmeijer\Transpher;

use rikmeijer\Transpher\Nostr\Message\Factory;
use rikmeijer\Transpher\Relay\Sender;
use rikmeijer\Transpher\Relay\Store;

/**
 * Description of Server
 *
 * @author Rik Meijer <hello@rikmeijer.nl>
 */
class Relay {
    
    static function boot(string $address, array $env) : Process {
        $cmd = [PHP_BINARY, ROOT_DIR . DIRECTORY_SEPARATOR . 'relay.php', $address];
        list($ip, $port) = explode(':', $address);
        return new Process('relay-' . $port, $cmd, $env, fn(string $line) => str_contains($line, 'Listening on http://127.0.0.1:'.$port.'/'));
    }
    
    
    public function __construct(private Store $events) {
        
    }
    
    public function __invoke(string $payload, Sender $relay) : \Generator {
        $message = \rikmeijer\Transpher\Nostr::decode($payload);
        if (is_null($message)) {
            yield Factory::notice('Invalid message');
        } else {
            try {
                $incoming = Relay\Incoming\Factory::fromMessage($message, $this->events, $relay);
                yield from $incoming();
            } catch (\InvalidArgumentException $ex) {
                yield Factory::notice($ex->getMessage());
            }

        }
    }
}
