<?php

return
[
    'log_directory' => 'logs/Salesforce/Connections',
    'wsdl_path' => 'enterprise.xml',
    'username' => env('SALESFORCE_USERNAME'),
    'password' => env('SALESFORCE_PASSWORD'),
    'token' => env('SALESFORCE_TOKEN'),
];
