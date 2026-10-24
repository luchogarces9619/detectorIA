<?php

it('redirige la raíz al panel principal', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
