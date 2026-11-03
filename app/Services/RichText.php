<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class RichText
{
    public function sanitize(?string $html): ?string
    {
        if (trim($html ?? '') === '') {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);
            $body = $document->getElementsByTagName('body')->item(0);
            $clean = $body ? $this->children($body) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return trim(html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \n\r\t\v\0\u{00A0}") === '' ? null : $clean;
    }

    private function children(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $html .= htmlspecialchars($child->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'], true)) {
                    continue;
                }
                $content = $this->children($child);
                $html .= match ($tag) {
                    'br' => '<br>',
                    'p', 'div', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote' => '<'.$tag.'>'.$content.'</'.$tag.'>',
                    default => $content,
                };
            }
        }

        return $html;
    }
}
