<?php

namespace App\Services;

class TipTapRenderer
{
    /**
     * Convert TipTap JSON to HTML.
     */
    public function render(?array $json): string
    {
        if (! $json || ! isset($json['content'])) {
            return '';
        }

        return $this->renderNodes($json['content']);
    }

    /**
     * Render an array of TipTap nodes.
     */
    private function renderNodes(array $nodes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= $this->renderNode($node);
        }

        return $html;
    }

    /**
     * Render a single TipTap node.
     */
    private function renderNode(array $node): string
    {
        $type = $node['type'] ?? '';
        $attrs = $node['attrs'] ?? [];
        $content = $node['content'] ?? [];

        return match ($type) {
            'doc' => $this->renderNodes($content),
            'paragraph' => $this->renderParagraph($content),
            'heading' => $this->renderHeading($attrs, $content),
            'bulletList' => $this->renderBulletList($content),
            'orderedList' => $this->renderOrderedList($content),
            'listItem' => $this->renderListItem($content),
            'blockquote' => $this->renderBlockquote($content),
            'codeBlock' => $this->renderCodeBlock($attrs, $content),
            'horizontalRule' => '<hr>',
            'hardBreak' => '<br>',
            'text' => $this->renderText($node),
            'table' => $this->renderTable($content),
            'tableRow' => $this->renderTableRow($content),
            'tableHeader' => $this->renderTableHeader($content),
            'tableCell' => $this->renderTableCell($content),
            default => $this->renderNodes($content),
        };
    }

    /**
     * Render paragraph node.
     */
    private function renderParagraph(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<p>{$inner}</p>";
    }

    /**
     * Render heading node.
     */
    private function renderHeading(array $attrs, array $content): string
    {
        $level = $attrs['level'] ?? 2;
        $level = min(max($level, 1), 6); // Ensure level is between 1-6
        $inner = $this->renderNodes($content);

        return "<h{$level}>{$inner}</h{$level}>";
    }

    /**
     * Render bullet list node.
     */
    private function renderBulletList(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<ul>{$inner}</ul>";
    }

    /**
     * Render ordered list node.
     */
    private function renderOrderedList(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<ol>{$inner}</ol>";
    }

    /**
     * Render list item node.
     */
    private function renderListItem(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<li>{$inner}</li>";
    }

    /**
     * Render blockquote node.
     */
    private function renderBlockquote(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<blockquote>{$inner}</blockquote>";
    }

    /**
     * Render code block node.
     */
    private function renderCodeBlock(array $attrs, array $content): string
    {
        $language = $attrs['language'] ?? '';
        $inner = $this->renderNodes($content);
        $langAttr = $language ? " class=\"language-{$language}\"" : '';

        return "<pre><code{$langAttr}>{$inner}</code></pre>";
    }

    /**
     * Render text node with marks.
     */
    private function renderText(array $node): string
    {
        $text = e($node['text'] ?? '');
        $marks = $node['marks'] ?? [];

        foreach ($marks as $mark) {
            $text = $this->applyMark($mark, $text);
        }

        return $text;
    }

    /**
     * Apply a mark to text.
     */
    private function applyMark(array $mark, string $text): string
    {
        $type = $mark['type'] ?? '';
        $attrs = $mark['attrs'] ?? [];

        return match ($type) {
            'bold' => "<strong>{$text}</strong>",
            'italic' => "<em>{$text}</em>",
            'underline' => "<u>{$text}</u>",
            'strike' => "<s>{$text}</s>",
            'code' => "<code>{$text}</code>",
            'link' => $this->renderLink($attrs, $text),
            'highlight' => "<mark>{$text}</mark>",
            'subscript' => "<sub>{$text}</sub>",
            'superscript' => "<sup>{$text}</sup>",
            default => $text,
        };
    }

    /**
     * Render link mark.
     */
    private function renderLink(array $attrs, string $text): string
    {
        $href = e($attrs['href'] ?? '#');
        $target = isset($attrs['target']) ? ' target="'.e($attrs['target']).'"' : '';
        $rel = isset($attrs['rel']) ? ' rel="'.e($attrs['rel']).'"' : '';

        return "<a href=\"{$href}\"{$target}{$rel}>{$text}</a>";
    }

    /**
     * Render table node.
     */
    private function renderTable(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<table>{$inner}</table>";
    }

    /**
     * Render table row node.
     */
    private function renderTableRow(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<tr>{$inner}</tr>";
    }

    /**
     * Render table header cell node.
     */
    private function renderTableHeader(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<th>{$inner}</th>";
    }

    /**
     * Render table cell node.
     */
    private function renderTableCell(array $content): string
    {
        $inner = $this->renderNodes($content);

        return "<td>{$inner}</td>";
    }
}
