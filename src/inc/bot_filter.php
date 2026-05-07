<?php

namespace WP_Rocket_Smart_Preload\Utils\Bot_Filter;

/**
 * Checks if the given user agent string belongs to a bot.
 * 
 * @param string $user_agent The user agent string to check.
 * @return bool True if the user agent is identified as a bot, false otherwise.
 * @since 1.1.0
 * @author Sandy Figueroa
 */
function is_bot(string $user_agent)
{
    // Single regex match instead of N sequential stripos() calls.
    // The PCRE engine traverses the string once using an optimized alternation automaton.
    // Source: https://www.php.net/manual/en/function.preg-match.php
    // Source: https://www.php.net/manual/en/function.preg-quote.php
    // Static cache: pattern is compiled once per request.
    static $pattern = null;
    if ($pattern === null) {
        $bots = array(
            'WP-Rocket',
            'WP Rocket',
            'Baiduspider',
            'Mediapartners-Google',
            'Googlebot',
            'GoogleOther',
            'Google-CloudVertexBot',
            'Google-Extended',
            'Storebot',
            'AdsBot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandex',
            'sogou',
            'exabot',
            'facebot',
            'ia_archiver',
            'mj12bot',
            'ahrefsbot',
            'semrushbot',
            'seznambot',
            'dotbot'
        );
        $pattern = '/' . implode('|', array_map(function ($bot) {
            return preg_quote($bot, '/');
        }, $bots)) . '/i';
    }
    return preg_match($pattern, $user_agent) === 1;
}
