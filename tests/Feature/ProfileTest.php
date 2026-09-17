<?php

it('does not expose removed ProfileTest endpoints', function () {
    foreach (['/profile'] as $url) {
        foreach (['get', 'patch', 'delete'] as $method) {
            $this->$method($url)->assertNotFound();
        }
    }
});
