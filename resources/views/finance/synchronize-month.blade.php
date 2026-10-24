@can('finance.create', $workspace)
    @can('budget.manage', $workspace)
        <div x-data @open-month-sync.window="$refs.syncDialog.showModal()"
            @close-month-sync.window="$refs.syncDialog.close()"
            @financial-month-changed.window="$refs.syncDialog.close()">
            <dialog class="app-dialog" x-ref="syncDialog" wire:ignore.self aria-labelledby="sync-month-title"
                @cancel="$wire.cancelSynchronization()">
                <form wire:submit="synchronizePreviousMonth" class="dialog-content form-stack">
                    <div class="dialog-top"><span class="eyebrow">PREPARAR O MÊS</span>
                        <button type="button" class="icon-close" aria-label="Fechar"
                            @click="$refs.syncDialog.close(); $wire.cancelSynchronization()">×</button>
                    </div>
                    <h2 id="sync-month-title">Sincronizar com o mês anterior?</h2>
                    <p>As receitas e os gastos fixos de {{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->subMonth()->format('m/Y') }}
                        serão copiados para {{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->format('m/Y') }}
                        como efetivados. Todos os registros copiados usarão a data de início informada abaixo.
                        Os dados do mês anterior serão preservados.</p>
                    <x-cycle-date-fields :month="$month" />
                    @error('synchronization')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    <div class="dialog-actions">
                        <button type="button" class="button secondary" wire:loading.attr="disabled"
                            @click="$refs.syncDialog.close(); $wire.cancelSynchronization()">Cancelar</button>
                        <button type="submit" class="button" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="synchronizePreviousMonth">Confirmar sincronização</span>
                            <span wire:loading wire:target="synchronizePreviousMonth">Sincronizando…</span>
                        </button>
                    </div>
                </form>
            </dialog>
        </div>
    @endcan
@endcan
