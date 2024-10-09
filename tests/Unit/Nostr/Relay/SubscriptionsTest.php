<?php

use Transpher\Nostr\Relay\Subscriptions;

it('adds and  removes a subscription from the subscriptions-closure', function() {
    $subscriptions = new Subscriptions();
    $refl_subscriptions = (new ReflectionObject($subscriptions));
    expect($refl_subscriptions->getStaticPropertyValue('subscriptions'))->toHaveCount(0);
    
    $true = Mockery::mock(\Transpher\Nostr\Relay\Filter::class);
    $true->shouldReceive('__invoke')->andReturnTrue();
    
    Subscriptions::subscribe('my-awesome-subscription', $true, function(string $subscriptionId, array $event) : bool {
        return true;
    });
    expect($refl_subscriptions->getStaticPropertyValue('subscriptions'))->toHaveCount(1);
    
    Subscriptions::unsubscribe('my-missing-subscription');
    expect($refl_subscriptions->getStaticPropertyValue('subscriptions'))->toHaveCount(1);
    
    Subscriptions::unsubscribe('my-awesome-subscription');
    expect($refl_subscriptions->getStaticPropertyValue('subscriptions'))->toHaveCount(0);
    
});