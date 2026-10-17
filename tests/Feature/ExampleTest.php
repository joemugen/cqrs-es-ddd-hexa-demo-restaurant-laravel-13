<?php

test('the health check responds', function () {
    $this->get('/up')->assertOk();
});
