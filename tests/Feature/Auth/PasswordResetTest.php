<?php

it('does not expose removed Auth PasswordResetTest endpoints', function () {
    foreach (['/forgot-password', '/reset-password/token', '/reset-password'] as $url) {
        foreach (['get', 'post'] as $method) {
            $this->$method($url)->assertNotFound();
        }
    }
});
