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
    $user_agent = strtolower($user_agent);
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
    foreach ($bots as $bot) {
        if (stripos($user_agent, strtolower($bot)) !== false) {
            return true;
        }
    }
    return false;
}
