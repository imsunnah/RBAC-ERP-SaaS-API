<?php

use Laravel\Passport\Passport;

test('passport tokens expire in 6 hours', function () {
    $tokensExpireIn = Passport::tokensExpireIn();
    $personalAccessTokensExpireIn = Passport::personalAccessTokensExpireIn();

    expect($tokensExpireIn->h)->toBe(6);
    expect($tokensExpireIn->d)->toBe(0);
    expect($tokensExpireIn->m)->toBe(0);
    expect($tokensExpireIn->y)->toBe(0);

    expect($personalAccessTokensExpireIn->h)->toBe(6);
    expect($personalAccessTokensExpireIn->d)->toBe(0);
    expect($personalAccessTokensExpireIn->m)->toBe(0);
    expect($personalAccessTokensExpireIn->y)->toBe(0);
});
