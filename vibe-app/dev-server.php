<?php

/**
 * Router for the local `php -S` server. Laravel's own router assumes the working directory is public/,
 * which `artisan serve` arranges but a plain `php -S` does not. Starting PHP directly lets the
 * upload settings (upload_tmp_dir) and opcache be passed on the command line.
 */
chdir(__DIR__.'/public');

// The router must hand back its return value: false tells PHP to serve the static file itself (css, js, images).
return require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
