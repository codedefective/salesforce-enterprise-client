<?php

return
[
    'log_directory' => 'logs/Salesforce/Connections',
    'wsdl_path' => 'enterprise.xml',
    'location' => env('SALESFORCE_LOCATION'),
    'username' => env('SALESFORCE_USERNAME'),
    'password' => env('SALESFORCE_PASSWORD'),
    'token' => env('SALESFORCE_TOKEN'),
];
