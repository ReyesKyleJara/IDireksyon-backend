<?php

it('does not expose removed Auth RegistrationTest endpoints', function () {
    foreach (['/register'] as $url) {
        foreach (['get', 'post'] as $method) {
            $this->$method($url)->assertNotFound();
        }
    }
});
