<?php

it('returns 404 for the public registration screen', function () {
    $this->get('/register')->assertNotFound();
});

it('returns 404 for public registration submissions', function () {
    $this->post('/register', [
        'name' => 'Unapproved User',
        'email' => 'unapproved@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'unapproved@example.com']);
});
