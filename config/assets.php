<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assets Version Number
    |--------------------------------------------------------------------------
    |
    | Assets version number that will append to each asset resource URL
    | as version 'v' GET parameter.
    |
    | Example: http://website.domain/path/to/asset.css?v=0.0.1
    |
    */

    'version' => '0.1.73',

    /*
    |--------------------------------------------------------------------------
    | Assets Secure Option
    |--------------------------------------------------------------------------
    |
    | If secure option is 'true' HTTPS will be used for each asset resource URL.
    | Example: https://website.domain/path/to/asset.css?v=0.0.1
    |
    | If secure option is 'false' HTTP will be used for each asset resource URL.
    | Example: http://website.domain/path/to/asset.css?v=0.0.1
    |
    | If secure option is 'null' schema will be detected automatically.
    |
    */

    'secure' => env('ASSETS_SECURE', null),

    /*
    |--------------------------------------------------------------------------
    | Auto Versioning
    |--------------------------------------------------------------------------
    |
    | Automatically generate the version number on a per file basis using the
    | config version numbers on external files
    |
    */
    'auto' => false,
];
