<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * HTML Sanitizer for blog content
 */
class Sanitizer
{
    private static array $allowedTags = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'br', 'hr',
        'ul', 'ol', 'li',
        'strong', 'b', 'em', 'i', 'u', 's',
        'a', 'img',
        'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span',
        'figure', 'figcaption',
    ];

    private static array $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel', 'style'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'style'],
        'div' => ['class', 'style'],
        'span' => ['class', 'style'],
        'p' => ['class', 'style'],
        'figure' => ['class', 'style'],
        'figcaption' => ['class', 'style'],
        'h1' => ['class', 'style'],
        'h2' => ['class', 'style'],
        'h3' => ['class', 'style'],
        'h4' => ['class', 'style'],
        'table' => ['class', 'style'],
        'th' => ['colspan', 'rowspan', 'style'],
        'td' => ['colspan', 'rowspan', 'style'],
    ];

    public static function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Remove script, style, iframe, object, embed tags and their content
        $html = preg_replace('/<(script|style|iframe|object|embed|form|input|button|textarea|select)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/<(script|style|iframe|object|embed|form|input|button|textarea|select)[^>]*\/?>/is', '', $html) ?? $html;

        // Remove event handlers
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html) ?? $html;
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html) ?? $html;

        // Remove javascript: and data: protocols in href/src
        $html = preg_replace('/(href|src)\s*=\s*["\']?\s*(javascript|data|vbscript):/i', '$1="', $html) ?? $html;

        // Use strip_tags with allowed tags
        $allowed = '<' . implode('><', self::$allowedTags) . '>';
        $html = strip_tags($html, $allowed);

        // Clean attributes more carefully using DOMDocument if available
        if (class_exists(\DOMDocument::class) && $html !== '') {
            $html = self::cleanWithDom($html);
        }

        return trim($html);
    }

    private static function cleanWithDom(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);

        // Wrap in a container to handle fragments
        $wrapped = '<?xml encoding="UTF-8"><div id="sanitize-root">' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('sanitize-root');
        if ($root === null) {
            return $html;
        }

        self::cleanNode($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    private static function cleanNode(\DOMNode $node): void
    {
        if ($node->nodeType === XML_ELEMENT_NODE && $node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);

            if (!in_array($tag, self::$allowedTags, true)) {
                // Remove disallowed tag but keep children
                while ($node->firstChild) {
                    $node->parentNode?->insertBefore($node->firstChild, $node);
                }
                $node->parentNode?->removeChild($node);
                return;
            }

            // Clean attributes
            $allowedAttrs = self::$allowedAttributes[$tag] ?? [];
            $attrsToRemove = [];

            if ($node->hasAttributes()) {
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $attrName = strtolower($attr->name);
                    if (!in_array($attrName, $allowedAttrs, true)) {
                        $attrsToRemove[] = $attr->name;
                    } elseif (in_array($attrName, ['href', 'src'], true)) {
                        $value = $attr->value;
                        if (preg_match('/^\s*(javascript|data|vbscript):/i', $value)) {
                            $attrsToRemove[] = $attr->name;
                        }
                    } elseif ($attrName === 'style') {
                        $value = $attr->value;
                        if (preg_match('/(?:javascript|expression|behavior|vbscript):/i', $value)) {
                            $attrsToRemove[] = $attr->name;
                        }
                    }
                }
            }

            foreach ($attrsToRemove as $attrName) {
                $node->removeAttribute($attrName);
            }

            // Force rel="noopener noreferrer" on external links
            if ($tag === 'a' && $node->hasAttribute('target')) {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        // Recurse children (copy list first as we may modify)
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::cleanNode($child);
        }
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function truncate(string $text, int $length = 160): string
    {
        $text = strip_tags($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }
}
