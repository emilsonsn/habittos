<div>
    @if ($message)
        <div class="notice success" role="status">{{ $message }}</div>
    @endif
    <div class="page-heading">
        <div><span class="eyebrow">PLANOS QUE SE COMPARTILHAM</span>
            <h1>Pessoas e acessos<span class="heading-dot">.</span></h1>
            <p>Defina o que cada pessoa pode fazer neste ambiente.</p>
        </div>
    </div>
    <div class="dashboard-grid">
        <section class="panel">
            <h2>Quem está por aqui</h2>
            @foreach ($members as $member)
                <div class="member" wire:key="member-{{ $member->id }}">
                    <div><strong>{{ $member->name }}</strong><small>{{ $member->email }}</small>
                        <p class="muted">
                            {{ $member->id === $workspace->owner_id ? 'Proprietário · Acesso completo' : count($member->pivot->permissions ?? []) . ' permissões concedidas' }}
                        </p>
                    </div>
                    @if ($member->id !== $workspace->owner_id &&
                        (auth()->id() === $workspace->owner_id || !in_array('members.manage', $member->pivot->permissions ?? [])))
                        <div><button type="button" class="text-button danger"
                                wire:click="removeMember({{ $member->id }})"
                                data-confirm="Esta pessoa perderá o acesso ao ambiente. Sua conta e os dados que ela já registrou serão preservados." data-confirm-title="Remover acesso?" data-confirm-label="Remover acesso" data-confirm-subject="{{ $member->name }}"
                                wire:loading.attr="disabled">Remover acesso</button>
                            <details>
                                <summary>Editar permissões</summary>
                                <form wire:submit="savePermissions({{ $member->id }})" data-confirm="As permissões selecionadas passarão a definir quais dados e ações esta pessoa pode acessar no ambiente." data-confirm-title="Alterar permissões?" data-confirm-label="Salvar permissões" data-confirm-subject="{{ $member->name }}" data-confirm-tone="{{ \App\Enums\ConfirmationTone::Warning->value }}" class="form-stack">
                                    @foreach ($permissionOptions as $permission)
                                        @if ($permission->value !== 'members.manage' || auth()->id() === $workspace->owner_id)
                                            <label class="checkbox"><input type="checkbox"
                                                    wire:model="memberPermissions.{{ $member->id }}"
                                                    value="{{ $permission->value }}">{{ $permission->label() }}</label>
                                        @endif
                                    @endforeach
                                    <button class="button secondary" wire:loading.attr="disabled">Salvar
                                        permissões</button>
                                </form>
                            </details>
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
        <section class="panel"><span class="eyebrow">COMPARTILHAR AMBIENTE</span>
            <h2>Adicionar uma pessoa</h2>
            <p class="muted">A pessoa deve criar uma conta no habittos antes de receber acesso. Nenhum e-mail será
                enviado.</p>
            <form wire:submit="addMember" data-confirm="A pessoa terá acesso a este ambiente conforme as permissões selecionadas. Confira o e-mail e os acessos antes de continuar." data-confirm-title="Adicionar pessoa ao ambiente?" data-confirm-label="Adicionar pessoa" data-confirm-tone="{{ \App\Enums\ConfirmationTone::Warning->value }}" class="form-stack"><label>E-mail da conta<input type="email"
                        wire:model="email" required></label>
                @error('email')
                    <span class="field-error">{{ $message }}</span>
                @enderror
                <fieldset>
                    <legend>Permissões neste ambiente</legend>
                    @foreach ($permissionOptions as $permission)
                        @if ($permission->value !== 'members.manage' || auth()->id() === $workspace->owner_id)
                            <label class="checkbox"><input type="checkbox" wire:model="permissions"
                                    value="{{ $permission->value }}">{{ $permission->label() }}</label>
                        @endif
                    @endforeach
                </fieldset>
                @error('permissions.*')
                    <span class="field-error">{{ $message }}</span>
                @enderror
                <button class="button" wire:loading.attr="disabled">Conceder acesso</button>
            </form>
        </section>
    </div>
</div>
