<?php

$config = require __DIR__ . '/env.php';

return [
    'secret'     => $config['JWT_SECRET'],
    'issuer'     => $config['JWT_ISSUER'],
    'expires_in' => $config['JWT_EXPIRES_IN'],
];


