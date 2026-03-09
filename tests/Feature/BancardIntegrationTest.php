<?php

use KrugerDavid\Bancard\BancardManager;
use KrugerDavid\Bancard\Facades\Bancard;

it('resolves BancardManager from container', function () {
    $manager = app(BancardManager::class);

    expect($manager)->toBeInstanceOf(BancardManager::class);
});

it('resolves Bancard facade to manager', function () {
    expect(Bancard::getFacadeRoot())->toBeInstanceOf(BancardManager::class);
});

it('returns staging checkout url when staging', function () {
    config()->set('bancard.staging', true);
    $manager = app(BancardManager::class);

    expect($manager->getCheckoutScriptUrl())->toContain('8888');
});

it('returns production checkout url when not staging', function () {
    $config = config('bancard');
    $config['staging'] = false;
    $manager = new BancardManager($config);

    expect($manager->getCheckoutScriptUrl())->not->toContain('8888');
});
