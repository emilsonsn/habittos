@can('finance.create', $workspace)
    @can('budget.manage', $workspace)
        <div x-data @open-month-sync.window="$refs.syncDialog.showModal()"
            @close-month-sync.window="$refs.syncDialog.close()"
            @financial-month-changed.window="$refs.syncDialog.close()">
            @php
                $syncDescription = 'As receitas e os gastos fixos de '.\Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->subMonth()->format('m/Y')
                    .' serão copiados para '.\Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->format('m/Y')
                    .' como pendentes, com a data de início informada abaixo. Os dados do mês anterior serão preservados.';
            @endphp
            <x-confirmation-dialog id="sync-month" x-ref="syncDialog" wire:ignore.self
                title="Sincronizar com o mês anterior?" :description="$syncDescription" :tone="\App\Enums\ConfirmationTone::Warning"
                close-action="$refs.syncDialog.close(); $wire.cancelSynchronization()" @cancel="$wire.cancelSynchronization()">
                <form wire:submit="synchronizePreviousMonth" class="form-stack">
                    <x-cycle-date-fields :month="$month" />
                    @error('synchronization')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    <div class="dialog-actions confirmation-actions">
                        <button type="button" class="button secondary" wire:loading.attr="disabled"
                            @click="$refs.syncDialog.close(); $wire.cancelSynchronization()">Cancelar</button>
                        <button type="submit" class="button confirmation-accept" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="synchronizePreviousMonth">Confirmar sincronização</span>
                            <span wire:loading wire:target="synchronizePreviousMonth">Sincronizando…</span>
                        </button>
                    </div>
                </form>
            </x-confirmation-dialog>
        </div>
    @endcan
@endcan
