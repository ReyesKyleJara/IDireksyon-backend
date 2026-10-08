<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Restricted Tiptap JSON: never accept or store arbitrary HTML. */
class GuideRichText
{
    public static function validate(mixed $document, string $path): array
    {
        $count = 0;
        $walk = function ($node, int $depth = 0, ?string $parent = null) use (&$walk, &$count, $path): void {
            $fail = fn () => throw ValidationException::withMessages([$path => 'Use only the supported text formatting.']);
            if (! is_array($node) || ++$count > 2000 || $depth > 12 || array_diff(array_keys($node), ['type', 'content', 'text', 'marks', 'attrs'])) $fail();
            $type = $node['type'] ?? null;
            $allowed = match ($parent) {
                null => ['doc'],
                'doc', 'listItem' => ['paragraph', 'heading', 'bulletList', 'orderedList'],
                'bulletList', 'orderedList' => ['listItem'],
                'paragraph', 'heading' => ['text', 'hardBreak'],
                default => [],
            };
            if (! in_array($type, $allowed, true)) $fail();
            $attrs = $node['attrs'] ?? [];
            if (! is_array($attrs)) $fail();
            if ($type === 'heading') {
                if (array_diff(array_keys($attrs), ['level']) || ! in_array($attrs['level'] ?? null, [2, 3], true)) $fail();
            } elseif ($type === 'orderedList') {
                if (array_diff(array_keys($attrs), ['start', 'type']) || (isset($attrs['start']) && $attrs['start'] !== 1) || ! in_array($attrs['type'] ?? null, [null, '1'], true)) $fail();
            } elseif ($attrs !== []) $fail();
            if ($type === 'text') {
                if (! is_string($node['text'] ?? null) || mb_strlen($node['text']) > 20000 || isset($node['content'])) $fail();
                $marks = $node['marks'] ?? [];
                if (! is_array($marks) || ! array_is_list($marks) || count($marks) > 4) $fail();
                foreach ($marks as $mark) {
                    if (! is_array($mark) || array_diff(array_keys($mark), ['type', 'attrs']) || ! in_array($mark['type'] ?? null, ['bold', 'italic', 'underline', 'link'], true)) $fail();
                    $ma = $mark['attrs'] ?? [];
                    if (! is_array($ma)) $fail();
                    if ($mark['type'] === 'link') {
                        if (array_diff(array_keys($ma), ['href', 'target', 'rel', 'class', 'title']) || ! self::safeUrl($ma['href'] ?? null)
                            || ! in_array($ma['target'] ?? null, [null, '_blank', '_self'], true)
                            || ! in_array($ma['rel'] ?? null, [null, 'noopener noreferrer nofollow', 'noopener noreferrer'], true)
                            || ($ma['class'] ?? null) !== null || (isset($ma['title']) && (! is_string($ma['title']) || mb_strlen($ma['title']) > 255))) $fail();
                    } elseif ($ma !== []) $fail();
                }
            } else {
                if (isset($node['text'], $node['marks']) || array_key_exists('text', $node) || array_key_exists('marks', $node)) $fail();
                $children = $node['content'] ?? [];
                if (! is_array($children) || ! array_is_list($children) || ($type === 'hardBreak' && $children !== [])) $fail();
                foreach ($children as $child) $walk($child, $depth + 1, $type);
            }
        };
        $walk($document);
        return $document;
    }

    public static function safeUrl(mixed $url): bool
    {
        return is_string($url) && strlen($url) <= 2048 && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true);
    }

    public static function render(mixed $document): string
    {
        // Older plain text remains readable; invalid stored formatting is never executed.
        if (is_string($document)) return nl2br(e($document));
        try { self::validate($document, 'content'); } catch (ValidationException) { return ''; }
        $render = function (array $node) use (&$render): string {
            if ($node['type'] === 'text') {
                $html = e($node['text']);
                foreach ($node['marks'] ?? [] as $mark) {
                    $html = match ($mark['type']) {
                        'bold' => '<strong>'.$html.'</strong>',
                        'italic' => '<em>'.$html.'</em>',
                        'underline' => '<u>'.$html.'</u>',
                        'link' => '<a href="'.e($mark['attrs']['href']).'" target="_blank" rel="noopener noreferrer">'.$html.'</a>',
                    };
                }
                return $html;
            }
            $body = implode('', array_map($render, $node['content'] ?? []));
            $tag = match ($node['type']) {
                'paragraph' => 'p', 'heading' => 'h'.$node['attrs']['level'],
                'bulletList' => 'ul', 'orderedList' => 'ol', 'listItem' => 'li', default => null,
            };
            return $node['type'] === 'hardBreak' ? '<br>' : ($tag ? '<'.$tag.'>'.$body.'</'.$tag.'>' : $body);
        };
        return $render($document);
    }
}
