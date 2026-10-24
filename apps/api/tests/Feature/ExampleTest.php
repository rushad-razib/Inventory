<?php

it('health endpoint responds', function () {
    $this->get('/up')->assertOk();
});
