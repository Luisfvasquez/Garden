<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Server-side handling of the Tiptap document that makes up a letter body.
 * The editor is deliberately restricted (docs/sistema-diseno.md): bold, italic,
 * underline, blockquote, horizontal rule. Anything else is stripped — never
 * trust the client (docs/api/cartas.md).
 */
final class TiptapContent
{
    private const ALLOWED_NODES = [
        'doc',
        'paragraph',
        'text',
        'blockquote',
        'horizontalRule',
        'hardBreak',
    ];

    private const ALLOWED_MARKS = ['bold', 'italic', 'underline'];

    /**
     * @param  array<string, mixed>  $doc
     * @return array{type: string, content?: array<int, mixed>}
     */
    public static function sanitize(array $doc): array
    {
        $node = self::sanitizeNode($doc);
        if ($node === null || ($node['type'] ?? null) !== 'doc') {
            return ['type' => 'doc', 'content' => []];
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    public static function toPlainText(array $doc): string
    {
        $lines = [];
        self::collectText($doc, $lines);

        return trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)) ?? '');
    }

    public static function wordCount(string $plain): int
    {
        $trimmed = trim($plain);

        return $trimmed === '' ? 0 : count(preg_split('/\s+/', $trimmed) ?: []);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private static function sanitizeNode(array $node): ?array
    {
        $type = is_string($node['type'] ?? null) ? $node['type'] : null;
        if ($type === null || ! in_array($type, self::ALLOWED_NODES, true)) {
            return null;
        }

        $clean = ['type' => $type];

        if ($type === 'text') {
            $clean['text'] = is_string($node['text'] ?? null) ? $node['text'] : '';
            $marks = self::sanitizeMarks(is_array($node['marks'] ?? null) ? $node['marks'] : []);
            if ($marks !== []) {
                $clean['marks'] = $marks;
            }

            return $clean['text'] === '' ? null : $clean;
        }

        $children = [];
        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $sanitized = self::sanitizeNode($child);
                if ($sanitized !== null) {
                    $children[] = $sanitized;
                }
            }
        }

        if ($children !== []) {
            $clean['content'] = $children;
        }

        return $clean;
    }

    /**
     * @param  array<int, mixed>  $marks
     * @return array<int, array{type: string}>
     */
    private static function sanitizeMarks(array $marks): array
    {
        $out = [];
        foreach ($marks as $mark) {
            $type = is_array($mark) && is_string($mark['type'] ?? null) ? $mark['type'] : null;
            if ($type !== null && in_array($type, self::ALLOWED_MARKS, true)) {
                $out[$type] = ['type' => $type];
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $lines
     */
    private static function collectText(array $node, array &$lines): void
    {
        if (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null)) {
            $lines[] = $node['text'];

            return;
        }

        $buffer = [];
        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            if (($child['type'] ?? null) === 'text' && is_string($child['text'] ?? null)) {
                $buffer[] = $child['text'];
            } else {
                if ($buffer !== []) {
                    $lines[] = implode('', $buffer);
                    $buffer = [];
                }
                self::collectText($child, $lines);
            }
        }
        if ($buffer !== []) {
            $lines[] = implode('', $buffer);
        }
    }
}
