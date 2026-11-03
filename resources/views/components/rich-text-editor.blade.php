@props(['model', 'label', 'editorId', 'value' => '', 'readonly' => false, 'optional' => false, 'max' => 20000])
<div class="rich-text-field">
    <span id="{{ $editorId }}-label">{{ $label }} @if ($optional)<span class="muted">(opcional)</span>@endif</span>
    @if ($readonly)
        <div class="rich-text-content rich-text-readonly" role="region" aria-labelledby="{{ $editorId }}-label">
            @if ($value)
                {!! app(\App\Services\RichText::class)->sanitize($value) !!}
            @else
                <span class="muted">Nenhum texto informado.</span>
            @endif
        </div>
    @else
        <div class="rich-text-editor" wire:ignore x-data="HabittosRichText.create($wire, @js($model))">
            <div class="rich-text-toolbar" role="toolbar" aria-label="Formatação de {{ mb_strtolower($label) }}" @mousedown.prevent>
                <button type="button" @click="format('bold')" aria-label="Negrito" title="Negrito"><strong>B</strong></button>
                <button type="button" @click="format('italic')" aria-label="Itálico" title="Itálico"><em>I</em></button>
                <button type="button" @click="format('underline')" aria-label="Sublinhado" title="Sublinhado"><u>U</u></button>
                <button type="button" @click="format('insertUnorderedList')" aria-label="Lista com marcadores" title="Lista com marcadores">• Lista</button>
                <button type="button" @click="format('insertOrderedList')" aria-label="Lista numerada" title="Lista numerada">1. Lista</button>
                <button type="button" @click="format('formatBlock', 'blockquote')" aria-label="Citação" title="Citação">❝</button>
                <button type="button" @click="format('removeFormat')" aria-label="Limpar formatação" title="Limpar formatação">Limpar</button>
            </div>
            <div x-ref="editor" class="rich-text-content" contenteditable="true" role="textbox" aria-multiline="true"
                aria-labelledby="{{ $editorId }}-label" aria-describedby="{{ $editorId }}-help"
                @input="sync()" @keyup="remember()" @mouseup="remember()" @blur="remember()"
                @paste="paste($event)" @drop.prevent></div>
        </div>
        <small id="{{ $editorId }}-help" class="muted">Até {{ number_format($max, 0, ',', '.') }} caracteres, incluindo a formatação.</small>
    @endif
</div>
