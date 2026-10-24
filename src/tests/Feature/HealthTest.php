<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expone un endpoint de salud para la aplicación web', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('service', 'scitrace-web')
        ->assertJsonPath('checks.database', 'ok');
});

it('expone la sonda de salud de Laravel', function () {
    $this->get('/up')->assertOk();
});
