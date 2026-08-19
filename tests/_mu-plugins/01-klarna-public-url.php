<?php
/**
 * Plugin Name: KP Tests, public URL for Klarna
 * Description: Keeps the site local for the browser and public for Klarna. Loaded only inside the Codeception test WP install.
 *
 * The browser drives the site on WORDPRESS_URL, so its uncached assets never spend the
 * ngrok request allowance. Only Klarna's traffic uses the tunnel, which needs the site's
 * URL rewritten on the way out, and a browser that arrives on the tunnel sent back to
 * WORDPRESS_URL, because WordPress would answer it with a redirect to an unserved port.
 */

if (! defined('ABSPATH')) {
    exit;
}

// EndToEnd only: codeception.yml hands these to the built-in server, so the Integration
// and Harness suites, which never see them, are left alone.
$kp_local  = rtrim((string) getenv('WORDPRESS_URL'), '/');
$kp_public = rtrim((string) getenv('KP_WORDPRESS_URL'), '/');

if ($kp_local === '' || $kp_public === '' || $kp_local === $kp_public) {
    return;
}

// ---------------------------------------------------------------------------
// Inbound: what arrived through the tunnel.
// ---------------------------------------------------------------------------

$kp_public_host = (string) parse_url($kp_public, PHP_URL_HOST);
$kp_local_host  = (string) parse_url($kp_local, PHP_URL_HOST);
$kp_local_port  = parse_url($kp_local, PHP_URL_PORT);

if ($kp_local_port !== null) {
    $kp_local_host .= ':' . $kp_local_port;
}

/** Whether the given header names the public host. The forwarded one can be a list. */
$kp_asked_for = static function (string $header) use ($kp_public_host): bool {
    foreach (explode(',', (string) ($_SERVER[$header] ?? '')) as $host) {
        if (strcasecmp(strtok(trim($host), ':') ?: '', $kp_public_host) === 0) {
            return true;
        }
    }

    return false;
};

if ($kp_asked_for('HTTP_HOST') || $kp_asked_for('HTTP_X_FORWARDED_HOST')) {
    $kp_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $kp_uri    = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $kp_path   = (string) (parse_url($kp_uri, PHP_URL_PATH) ?: '/');

    // What Klarna calls the site on. A redirect would drop a POST body, and Klarna
    // follows none.
    $kp_is_callback = in_array($kp_method, ['GET', 'HEAD'], true) === false
        || strpos($kp_path, '/wc-api/') === 0
        || strpos($kp_path, '/wp-json/') === 0
        || isset($_GET['wc-api'])
        || isset($_GET['rest_route']);

    if ($kp_is_callback) {
        // Handled as the local request it would have been.
        $_SERVER['HTTP_HOST']   = $kp_local_host;
        $_SERVER['SERVER_NAME'] = strtok($kp_local_host, ':');
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    } else {
        // Anything a browser asks for, Klarna's confirmation URL included.
        header('Location: ' . $kp_local . $kp_uri, true, 302);
        header('Cache-Control: no-store');
        exit;
    }

    unset($kp_method, $kp_uri, $kp_path, $kp_is_callback);
}

// ---------------------------------------------------------------------------
// Outbound: what KP sends to Klarna, which will not take a localhost URL.
// ---------------------------------------------------------------------------

$kp_port       = (string) getenv('BUILTIN_SERVER_PORT');
$kp_local_urls = array_values(
    array_unique(
        array_filter(
            [
                $kp_local,
                $kp_port === '' ? '' : 'http://localhost:' . $kp_port,
                $kp_port === '' ? '' : 'http://127.0.0.1:' . $kp_port,
            ]
        )
    )
);

// Both spellings of every local URL: bodies are rewritten as arrays and as the JSON they
// have already been encoded to, where the slashes are escaped.
$kp_search  = [];
$kp_replace = [];
foreach ($kp_local_urls as $kp_url) {
    $kp_search[]  = $kp_url;
    $kp_replace[] = $kp_public;
    $kp_search[]  = str_replace('/', '\/', $kp_url);
    $kp_replace[] = str_replace('/', '\/', $kp_public);
}

$kp_rewrite = static function ($value) use (&$kp_rewrite, $kp_search, $kp_replace) {
    if (is_string($value)) {
        return str_replace($kp_search, $kp_replace, $value);
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = $kp_rewrite($item);
        }

        return $value;
    }

    // Payment categories and the like reach the body as objects.
    if ($value instanceof stdClass) {
        foreach (get_object_vars($value) as $key => $item) {
            $value->$key = $kp_rewrite($item);
        }
    }

    return $value;
};

/*
 * On `http_request_args` rather than KP's own `kp_wc_api_request_args`, which only covers
 * the bodies KP core builds: order management, subscriptions and HPP build their own.
 */
add_filter(
    'http_request_args',
    static function ($args, $url) use ($kp_rewrite) {
        $host = (string) wp_parse_url((string) $url, PHP_URL_HOST);

        // Klarna's API only: everywhere the browser can see it, the URL stays local.
        if (preg_match('/(^|\.)klarna\.com$/i', $host) !== 1) {
            return $args;
        }

        if (isset($args['body'])) {
            $args['body'] = $kp_rewrite($args['body']);
        }

        return $args;
    },
    10,
    2
);

unset(
    $kp_local,
    $kp_public,
    $kp_public_host,
    $kp_local_host,
    $kp_local_port,
    $kp_asked_for,
    $kp_port,
    $kp_local_urls,
    $kp_url,
    $kp_search,
    $kp_replace
);
