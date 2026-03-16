<?php

use App\ConfigRepository;

beforeEach(function () {
    $this->config = app(ConfigRepository::class);

    // Start with a clean slate
    $this->config->set('api_tokens', []);
});

// === Issue #22: Duplicate organizations ===
// https://github.com/laravel/cloud-cli/issues/22
//
// Root cause: addApiToken() used ->push() without deduplication.
// Running `cloud auth` multiple times appended the same token repeatedly.
// Each duplicate triggered a separate API call to fetch the org,
// so the org picker showed the same organization multiple times.

it('does not accumulate duplicate tokens when addApiToken is called repeatedly', function () {
    $token = 'test-token-abc123';

    // Simulate running `cloud auth` 5 times with the same token
    $this->config->addApiToken($token);
    $this->config->addApiToken($token);
    $this->config->addApiToken($token);
    $this->config->addApiToken($token);
    $this->config->addApiToken($token);

    $tokens = $this->config->apiTokens();

    // BEFORE fix: count would be 5 (each push appended without dedup)
    // AFTER fix: count is 1 (unique()->values() deduplicates)
    expect($tokens)->toHaveCount(1);
    expect($tokens->first())->toBe($token);
});

it('deduplicates tokens that already exist in config on read', function () {
    // Simulate a config.json that already has duplicates from before the fix
    $this->config->set('api_tokens', [
        'token-abc',
        'token-abc',
        'token-abc',
        'token-def',
        'token-def',
    ]);

    $tokens = $this->config->apiTokens();

    // BEFORE fix: count would be 5 (raw read, no dedup)
    // AFTER fix: count is 2 (unique()->values() on read)
    expect($tokens)->toHaveCount(2);
    expect($tokens->toArray())->toBe(['token-abc', 'token-def']);
});

// === Issue #23: Stale token accumulation ===
// https://github.com/laravel/cloud-cli/issues/23
//
// Root cause: Auth.php appended tokens on each re-auth without removing old ones.
// Over time, config.json accumulated expired tokens.
// When resolveApiToken() iterated multiple tokens and hit an expired one,
// the AlwaysThrowOnErrors trait threw an unhandled RequestException.

it('replaces all tokens atomically with setApiTokens', function () {
    // Simulate first auth session
    $this->config->setApiTokens(collect(['token-A']));
    expect($this->config->apiTokens()->toArray())->toBe(['token-A']);

    // Simulate re-auth: should REPLACE, not append
    $this->config->setApiTokens(collect(['token-B']));
    expect($this->config->apiTokens()->toArray())->toBe(['token-B']);

    // token-A should be gone entirely
    expect($this->config->apiTokens())->toHaveCount(1);
    expect($this->config->apiTokens()->contains('token-A'))->toBeFalse();
});

it('setApiTokens deduplicates the input', function () {
    $this->config->setApiTokens(collect(['token-A', 'token-A', 'token-B']));

    expect($this->config->apiTokens())->toHaveCount(2);
    expect($this->config->apiTokens()->toArray())->toBe(['token-A', 'token-B']);
});

it('removes a specific token with removeApiToken', function () {
    $this->config->setApiTokens(collect(['token-A', 'token-B', 'token-C']));
    $this->config->removeApiToken('token-B');

    expect($this->config->apiTokens()->toArray())->toBe(['token-A', 'token-C']);
});

// === Multi-organization support ===
// Users with multiple orgs get one token per org from the auth session.
// setApiTokens must preserve all of them, and re-auth must replace the
// full set without losing tokens for other orgs.

it('preserves multiple tokens for users with multiple organizations', function () {
    // Auth session returns one token per org
    $this->config->setApiTokens(collect(['token-org-A', 'token-org-B']));

    expect($this->config->apiTokens())->toHaveCount(2);
    expect($this->config->apiTokens()->toArray())->toBe(['token-org-A', 'token-org-B']);
});

it('replaces all org tokens atomically on re-auth', function () {
    // First auth: 2 orgs
    $this->config->setApiTokens(collect(['token-org-A-v1', 'token-org-B-v1']));
    expect($this->config->apiTokens())->toHaveCount(2);

    // Re-auth: same 2 orgs, fresh tokens
    $this->config->setApiTokens(collect(['token-org-A-v2', 'token-org-B-v2']));

    // Should have exactly 2 fresh tokens, no stale ones
    expect($this->config->apiTokens())->toHaveCount(2);
    expect($this->config->apiTokens()->toArray())->toBe(['token-org-A-v2', 'token-org-B-v2']);
    expect($this->config->apiTokens()->contains('token-org-A-v1'))->toBeFalse();
    expect($this->config->apiTokens()->contains('token-org-B-v1'))->toBeFalse();
});

it('handles org count changing between auth sessions', function () {
    // First auth: user has 2 orgs
    $this->config->setApiTokens(collect(['token-org-A', 'token-org-B']));
    expect($this->config->apiTokens())->toHaveCount(2);

    // Re-auth: user was removed from org B, now only 1 org
    $this->config->setApiTokens(collect(['token-org-A-new']));
    expect($this->config->apiTokens())->toHaveCount(1);
    expect($this->config->apiTokens()->toArray())->toBe(['token-org-A-new']);

    // Re-auth: user joined org C, now 2 orgs again
    $this->config->setApiTokens(collect(['token-org-A-new2', 'token-org-C']));
    expect($this->config->apiTokens())->toHaveCount(2);
    expect($this->config->apiTokens()->toArray())->toBe(['token-org-A-new2', 'token-org-C']);
});

it('returns empty collection when no tokens exist', function () {
    expect($this->config->apiTokens())->toHaveCount(0);
    expect($this->config->apiTokens()->isEmpty())->toBeTrue();
});
