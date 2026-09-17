<?php

it('does not expose removed Auth PasswordConfirmationTest endpoints', function () {
    foreach (['/confirm-password'] as $url) {
        foreach (['get', 'post'] as $method) {
            $this->$method($url)->assertNotFound();
        }
    }
});
