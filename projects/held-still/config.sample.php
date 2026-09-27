<?php
/**
 * Held Still — access settings.
 *
 * Copy this file to config.php (same folder) on the server and put in the
 * hash of the password. Make the hash with:
 *
 *     php -r 'echo password_hash("the password", PASSWORD_DEFAULT), "\n";'
 *
 * config.php is git-ignored, so the hash never lands in the repository.
 * Without a config.php the password box answers "Access isn't set up yet"
 * and nothing in this folder is served.
 */
return [
    'password_hash' => '',

    // Where a correct password leads. Default: the site in ./site/.
    // Set a full URL to send visitors to an external Held Still site
    // instead (that site is then not protected by this gate).
    'redirect' => '/projects/held-still/',
];
