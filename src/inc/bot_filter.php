<?php

namespace WP_Rocket_Smart_Preload\Utils\Bot_Filter;

/**
 * Checks if the given user agent string belongs to a bot.
 *
 * Uses a single preg_match() call with a case-insensitive alternation pattern.
 * The PCRE engine traverses the string once using an optimized alternation automaton,
 * which is significantly faster than N sequential stripos() calls.
 *
 * The pattern is compiled once per request via static caching.
 *
 * Sources for bot identifiers:
 * - Google: https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers
 * - Google (special): https://developers.google.com/crawling/docs/crawlers-fetchers/google-special-case-crawlers
 * - Bing: https://www.bing.com/webmasters/help/which-crawlers-does-bing-use-8c184ec0
 * - OpenAI: https://developers.openai.com/api/docs/bots
 * - Apple: https://support.apple.com/en-us/119829
 *
 * @param string $user_agent The user agent string to check.
 * @return bool True if the user agent is identified as a bot, false otherwise.
 * @since 1.1.0
 * @author Sandy Figueroa
 */
function is_bot(string $user_agent): bool
{
    // Source: https://www.php.net/manual/en/function.preg-match.php
    // Source: https://www.php.net/manual/en/function.preg-quote.php
    static $pattern = null;
    if ($pattern === null) {
        $bots = array(
            // -- WP Rocket's own preload bot --
            'WP-Rocket',
            'WP Rocket',

            // -- Google crawlers --
            // Source: https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers
            // 'Googlebot' also matches Googlebot-Image, Googlebot-Video, Googlebot-News via substring.
            'Googlebot',
            'GoogleOther',
            'Google-CloudVertexBot',
            'Google-Extended',
            'Google-InspectionTool',
            // 'Storebot' also matches Storebot-Google via substring.
            'Storebot',
            // 'AdsBot' also matches AdsBot-Google, AdsBot-Google-Mobile, OAI-AdsBot via substring.
            'AdsBot',
            'Mediapartners-Google',
            // Source: https://developers.google.com/crawling/docs/crawlers-fetchers/google-special-case-crawlers
            'APIs-Google',
            'Google-Safety',

            // -- Bing / Microsoft crawlers --
            // Source: https://www.bing.com/webmasters/help/which-crawlers-does-bing-use-8c184ec0
            'bingbot',
            'AdIdxBot',
            'MicrosoftPreview',
            'BingVideoPreview',

            // -- Yahoo --
            'slurp',

            // -- OpenAI crawlers --
            // Source: https://developers.openai.com/api/docs/bots
            'GPTBot',
            'OAI-SearchBot',
            'ChatGPT-User',

            // -- Apple --
            // Source: https://support.apple.com/en-us/119829
            // 'Applebot' also matches Applebot-Extended via substring.
            'Applebot',

            // -- Anthropic --
            'ClaudeBot',

            // -- Other AI / large-scale crawlers --
            // Common Crawl (used to train many AI models).
            'CCBot',
            // ByteDance / TikTok crawler.
            'Bytespider',
            // Amazon crawler.
            'Amazonbot',

            // -- Other search engines --
            'Baiduspider',
            'duckduckbot',
            'yandex',
            'sogou',
            'exabot',
            'seznambot',
            // Huawei Petal Search.
            'PetalBot',

            // -- SEO tool crawlers --
            'ahrefsbot',
            'semrushbot',
            'mj12bot',
            'dotbot',
            'DataForSeoBot',

            // -- Social media / preview bots --
            // These generate automated fetches when users share links; not real visits.
            'facebot',
            'LinkedInBot',
            'Twitterbot',
            'Pinterestbot',

            // -- Archive / other --
            'ia_archiver',
        );
        $pattern = '/' . implode('|', array_map(function ($bot) {
            return preg_quote($bot, '/');
        }, $bots)) . '/i';
    }
    return preg_match($pattern, $user_agent) === 1;
}
