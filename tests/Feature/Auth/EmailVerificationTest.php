<?php

it('does not expose removed Auth EmailVerificationTest endpoints', function () {
    foreach (['/verify-email', '/verify-email/1/hash', '/email/verification-notification'] as $url) {
        foreach (['get', 'post'] as $method) {
            $this->$method($url)->assertNotFound();
        }
    }
});
