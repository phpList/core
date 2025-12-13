<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use DOMDocument;
use DOMElement;
use DOMXPath;

class HtmlUrlRewriter
{
    public function addAbsoluteResources(string $html, string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, "/");

        // 1) Rewrite HTML attributes via DOM (handles quotes, whitespace, etc.)
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        // Prevent DOMDocument from adding html/body tags if you pass fragments
        $wrapped = '<!doctype html><meta charset="utf-8"><div id="__wrap__">' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);

        // Attributes to rewrite
        $attrMap = [
            '//*[@src]' => 'src',
            '//*[@href]' => 'href',
            '//*[@action]' => 'action',
            '//*[@background]' => 'background',
        ];

        foreach ($attrMap as $query => $attr) {
            foreach ($xpath->query($query) as $node) {
                /** @var DOMElement $node */
                $val = $node->getAttribute($attr);
                $node->setAttribute($attr, $this->absolutizeUrl($val, $baseUrl));
            }
        }

        // srcset needs special handling (multiple candidates)
        foreach ($xpath->query('//*[@srcset]') as $node) {
            /** @var DOMElement $node */
            $node->setAttribute('srcset', $this->rewriteSrcset($node->getAttribute('srcset'), $baseUrl));
        }

        // 2) Rewrite inline <style> blocks (CSS)
        foreach ($xpath->query('//style') as $styleNode) {
            /** @var DOMElement $styleNode */
            $css = $styleNode->nodeValue;
            $styleNode->nodeValue = $this->rewriteCssUrls($css, $baseUrl);
        }

        // 3) Rewrite style="" attributes (CSS)
        foreach ($xpath->query('//*[@style]') as $node) {
            /** @var DOMElement $node */
            $css = $node->getAttribute('style');
            $node->setAttribute('style', $this->rewriteCssUrls($css, $baseUrl));
        }

        // Extract the original fragment back out
        $wrap = $dom->getElementById('__wrap__');
        $out = '';
        foreach ($wrap->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        libxml_clear_errors();

        return $out;
    }

    /**
     * Convert $url to an absolute URL based on $baseUrl.
     * Leaves alone:
     * - already absolute (scheme:)
     * - protocol-relative (//example.com) => keeps host but adds scheme
     * - anchors (#...)
     * - placeholders like [SOMETHING]
     * - mailto:, tel:, data:, javascript: (etc)
     */
    public function absolutizeUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '' || $url[0] === '#') return $url;
        if (preg_match('/\[[^\]]+\]/', $url)) return $url;

        // already has a scheme (http:, https:, mailto:, data:, etc.)
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) return $url;

        $base = parse_url($baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            // If base is invalid, bail out rather than corrupt URLs
            return $url;
        }

        // protocol-relative
        if (str_starts_with($url, '//')) {
            return $base['scheme'] . ':' . $url;
        }

        $basePath = $base['path'] ?? '/';
        // If baseUrl points to a file, use its directory
        if (!str_ends_with($basePath, '/')) {
            $basePath = preg_replace('#/[^/]*$#', '/', $basePath);
        }

        if (str_starts_with($url, '/')) {
            $path = $url;
        } else {
            $path = $basePath . $url;
        }

        $path = $this->normalizePath($path);

        $port = isset($base['port']) ? ':' . $base['port'] : '';
        return $base['scheme'] . '://' . $base['host'] . $port . $path;
    }

    function normalizePath(string $path): string
    {
        // Keep query/fragment if present
        $parts = parse_url($path);
        $p = $parts['path'] ?? $path;

        $segments = explode('/', $p);
        $out = [];
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $seg;
        }
        $norm = '/' . implode('/', $out);

        if (isset($parts['query'])) $norm .= '?' . $parts['query'];
        if (isset($parts['fragment'])) $norm .= '#' . $parts['fragment'];
        return $norm;
    }

    public function rewriteSrcset(string $srcset, string $baseUrl): string
    {
        // "a.jpg 1x, /b.jpg 2x" => absolutize each URL part
        $candidates = array_map('trim', explode(',', $srcset));
        foreach ($candidates as &$cand) {
            if ($cand === '') continue;
            // split at first whitespace: "url descriptor..."
            if (preg_match('/^(\S+)(\s+.*)?$/', $cand, $m)) {
                $u = $m[1];
                $d = $m[2] ?? '';
                $cand = $this->absolutizeUrl($u, $baseUrl) . $d;
            }
        }
        return implode(', ', $candidates);
    }

    public function rewriteCssUrls(string $css, string $baseUrl): string
    {
        // url(...) handling (supports quotes or no quotes)
        $css = preg_replace_callback(
            '#url\(\s*(["\']?)(.*?)\1\s*\)#i',
            function ($m) use ($baseUrl) {
                $q = $m[1];
                $u = $m[2];
                $abs = $this->absolutizeUrl($u, $baseUrl);
                return 'url(' . ($q !== '' ? $q : '') . $abs . ($q !== '' ? $q : '') . ')';
            },
            $css
        );

        // @import "..."; or @import url("..."); etc.
        return preg_replace_callback(
            '#@import\s+(?:url\()?(\s*["\']?)([^"\')\s;]+)\1\)?#i',
            function ($m) use ($baseUrl) {
                $q = trim($m[1]);
                $u = $m[2];
                $abs = $this->absolutizeUrl($u, $baseUrl);
                // Preserve original form loosely
                return str_starts_with($m[0], '@import url')
                    ? '@import url(' . ($q ?: '') . $abs . ($q ?: '') . ')'
                    : '@import ' . ($q ?: '') . $abs . ($q ?: '');
            },
            $css
        );
    }
}
