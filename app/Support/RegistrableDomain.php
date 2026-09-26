<?php

namespace App\Support;

final class RegistrableDomain
{
    /**
     * Multi-tenant hosting/link platforms: "foo.vercel.app" belongs to Foo, but "vercel.app" is not
     * a mail domain we may probe or write to.
     *
     * @var list<string>
     */
    private const SHARED_HOSTING = [
        'github.io', 'gitlab.io', 'vercel.app', 'netlify.app', 'pages.dev', 'workers.dev', 'web.app',
        'firebaseapp.com', 'herokuapp.com', 'carrd.co', 'webflow.io', 'wixsite.com', 'wordpress.com',
        'blogspot.com', 'framer.website', 'framer.app', 'glitch.me', 'replit.app', 'repl.co', 'onrender.com',
        'fly.dev', 'railway.app', 'azurewebsites.net', 'cloudfront.net', 'amazonaws.com', 'ngrok.io',
        'surge.sh', 'gitbook.io', 'linktr.ee', 'bio.link', 'beacons.ai', 'notion.site', 'bubbleapps.io',
        'myshopify.com', 'streamlit.app', 'hf.space', 'ondigitalocean.app', 'pythonanywhere.com', 'appspot.com',
    ];

    /**
     * "https://careers.foo.com/x" -> "foo.com", "www.bar.co.uk" -> "bar.co.uk". A small heuristic, not a
     * public-suffix list: keeps three labels only for "<co|com|org|net|gov|ac|edu>.<ccTLD>". Null for
     * IPs, dotless hosts, invalid hostnames and shared-hosting platforms.
     */
    public static function of(string $urlOrHost): ?string
    {
        $host = parse_url($urlOrHost, PHP_URL_HOST);
        $host = strtolower(is_string($host) && $host !== '' ? $host : $urlOrHost);
        $host = (string) preg_replace('/^www\./', '', rtrim($host, '.'));

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $labels = explode('.', $host);
        $count = count($labels);

        $keep = $count >= 3
            && strlen($labels[$count - 1]) === 2
            && in_array($labels[$count - 2], ['co', 'com', 'org', 'net', 'gov', 'ac', 'edu'], true)
            ? 3
            : 2;

        $domain = implode('.', array_slice($labels, -$keep));

        return in_array($domain, self::SHARED_HOSTING, true) ? null : $domain;
    }
}
