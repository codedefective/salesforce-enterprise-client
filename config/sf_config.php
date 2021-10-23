<?php

return
[
    'log_directory' => 'logs/Salesforce/Connections',
    'wsdl_path' => 'enterprise.xml',
    'current' =>
    [
        'username' => env('SALESFORCE_USERNAME'),
        'password' => env('SALESFORCE_PASSWORD'),
        'token' => env('SALESFORCE_TOKEN'),
        'user_type' => env('SALESFORCE_USER_TYPE') ?? 'current',
    ]
];
