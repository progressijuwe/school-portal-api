<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Origins are driven by environment rather than hardcoded, so a new Vercel
| deployment or a domain change is a Railway variable edit instead of a code
| change. Use CORS_ALLOWED_ORIGIN_PATTERNS for Vercel preview builds — pasting
| individual deploy hashes into the allow-list does not scale and leaves dead
| entries behind.
|
| Example:
|   CORS_ALLOWED_ORIGINS="https://portal.example.edu,http://localhost:5173"
|   CORS_ALLOWED_ORIGIN_PATTERNS="#^https://student-portal-[a-z0-9-]+\.vercel\.app$#"
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))
)));

$patterns = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
)));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => $patterns,

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    // Lets the SPA read rate-limit state instead of guessing after a 429.
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],

    'max_age' => 86400,

    /*
     * False because the SPA authenticates with bearer tokens, not cookies —
     * see the note on statefulApi() in bootstrap/app.php. Flipping this to true
     * without also enabling stateful Sanctum and SANCTUM_STATEFUL_DOMAINS would
     * do nothing except widen what a browser is willing to send.
     */
    'supports_credentials' => false,

];
