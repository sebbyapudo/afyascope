<?php

test('redirects the application root to the protected dashboard', function () {
    $this->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
