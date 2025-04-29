<?php

it('returns a successful response', function () {
    $response = $this->getJson('/');

    $response->assertStatus(200);
})->skip();
