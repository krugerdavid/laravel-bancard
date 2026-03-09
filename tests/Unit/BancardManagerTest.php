<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use KrugerDavid\Bancard\BancardManager;
use KrugerDavid\Bancard\Tests\TestCase;

beforeEach(function () {
    $this->config = [
        'private_key' => 'test_private_key_40_chars_xxxxxxxxxxxx',
        'public_key' => 'test_public_key_32_chars_xxxx',
        'staging' => true,
        'timeout' => 30,
    ];
});

function injectBancardClient(BancardManager $manager, Client $client): void
{
    $ref = new ReflectionObject($manager);
    $prop = $ref->getProperty('client');
    $prop->setAccessible(true);
    $prop->setValue($manager, $client);
}

it('single buy returns process_id', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 'success',
            'process_id' => 'i5fn*lx6niQel0QzWK1g',
        ])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    $result = $manager->singleBuy(54322, '10330.00', 'Ejemplo de pago', 'https://example.com/finish');

    expect($result['status'])->toBe('success');
    expect($result['process_id'])->toBe('i5fn*lx6niQel0QzWK1g');
});

it('rollback returns success', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 'success',
            'messages' => [['key' => 'RollbackSuccessful', 'level' => 'info', 'dsc' => 'Rollback correcto.']],
        ])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    $result = $manager->rollback(12313);

    expect($result['status'])->toBe('success');
});

it('get confirmation returns confirmation data', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 'success',
            'confirmation' => [
                'shop_process_id' => '12313',
                'response' => 'S',
                'amount' => '10100.00',
                'response_code' => '00',
            ],
        ])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    $result = $manager->getConfirmation(12313);

    expect($result['status'])->toBe('success');
    expect($result['confirmation']['response_code'])->toBe('00');
});

it('cards new returns process_id', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 'success',
            'process_id' => 'abc123process',
        ])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    $result = $manager->cardsNew(1, 966389, '0919876543', 'test@mail.com', 'https://example.com/result');

    expect($result['status'])->toBe('success');
    expect($result['process_id'])->toBe('abc123process');
});

it('users cards returns cards array', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 'success',
            'cards' => [
                [
                    'alias_token' => 'c8996fb92427ae41e4649b934ca495991b7852b855',
                    'card_masked_number' => '5418********0014',
                    'card_id' => 1,
                ],
            ],
        ])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    $result = $manager->usersCards(966389);

    expect($result['status'])->toBe('success');
    expect($result['cards'])->toHaveCount(1);
    expect($result['cards'][0]['alias_token'])->toBe('c8996fb92427ae41e4649b934ca495991b7852b855');
});

it('charge returns operation response', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'operation' => [
                'response' => 'S',
                'response_code' => '00',
                'amount' => 10100,
            ],
        ])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    $result = $manager->charge(60361, '723215.00', 'c8996fb92427ae41e4649b934ca495991b7852b855', [
        'return_url' => 'https://example.com/result',
    ]);

    expect($result['operation']['response'])->toBe('S');
    expect($result['operation']['response_code'])->toBe('00');
});

it('delete card returns success', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 'success',
        ])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    $result = $manager->deleteCard(966389, 'c8996fb92427ae41e4649b934ca495991b7852b855');

    expect($result['status'])->toBe('success');
});

it('formats amount correctly', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode(['status' => 'success', 'process_id' => 'x'])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    $manager->singleBuy(1, 10000, 'Test', 'https://x.com');

    $lastRequest = $mock->getLastRequest();
    $body = json_decode($lastRequest->getBody()->getContents(), true);
    expect($body['operation']['amount'])->toBe('10000.00');
});

it('verify confirm token validates correctly', function () {
    $manager = new BancardManager($this->config);

    $amount = '10100.00';
    $expectedToken = md5($this->config['private_key'] . '12313' . 'confirm' . $amount . 'PYG');

    expect($manager->verifyConfirmToken(12313, $amount, $expectedToken))->toBeTrue();
    expect($manager->verifyConfirmToken(12313, $amount, 'wrong_token'))->toBeFalse();
});

it('throws on API error', function () {
    $mock = new MockHandler([
        new Response(400, [], json_encode([
            'status' => 'error',
            'messages' => [['key' => 'InvalidTokenError', 'dsc' => 'Invalid token']],
        ])),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $manager = new BancardManager($this->config);
    injectBancardClient($manager, $client);

    expect(fn () => $manager->singleBuy(1, '100', 'Test', 'https://x.com'))
        ->toThrow(\Exception::class, 'Bancard API error');
});
