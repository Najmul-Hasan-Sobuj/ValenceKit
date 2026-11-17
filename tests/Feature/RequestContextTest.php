<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('assigns server-generated correlation ids to responses and context without trusting client headers', function () {
    Route::get('/qa-context', fn () => response()->json(['request_id' => Context::get('request_id')]));
    $response = $this->withHeader('X-Request-ID', 'untrusted-client-value')->getJson('/qa-context')->assertOk();
    $id = $response->headers->get('X-Request-ID');
    expect(Str::isUuid($id))->toBeTrue();
    $response->assertJsonPath('request_id', $id);
    expect($this->getJson('/qa-context')->headers->get('X-Request-ID'))->not->toBe($id);
});

it('includes correlation ids on exception responses', function () {
    Route::get('/qa-context-error', fn () => abort(403));
    $response = $this->getJson('/qa-context-error')->assertForbidden()->assertHeader('X-Request-ID');
    expect(Str::isUuid($response->headers->get('X-Request-ID')))->toBeTrue();
});
