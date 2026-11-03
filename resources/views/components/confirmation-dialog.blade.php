@props(['id', 'title', 'description', 'tone' => \App\Enums\ConfirmationTone::Danger, 'closeAction' => null])
<dialog {{ $attributes->class(['app-dialog', 'confirmation-dialog']) }} id="{{ $id }}"
    role="alertdialog" aria-modal="true" aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description"
    data-confirmation-tone="{{ $tone->value }}" data-default-tone="{{ $tone->value }}">
    <div class="dialog-content confirmation-content">
        <div class="confirmation-top">
            <span class="confirmation-symbol" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3 2 21h20L12 3Z" stroke-linejoin="round"/><path d="M12 9v5m0 3v1"/></svg></span>
            <button type="button" class="icon-close" aria-label="Fechar confirmação"
                @if ($closeAction) @click="{{ $closeAction }}" @else data-confirmation-cancel @endif>×</button>
        </div>
        <span class="eyebrow confirmation-eyebrow">{{ $tone->label() }}</span>
        <h2 id="{{ $id }}-title" data-confirmation-title>{{ $title }}</h2>
        <p id="{{ $id }}-description" class="confirmation-description" data-confirmation-description>{{ $description }}</p>
        {{ $slot }}
    </div>
</dialog>
