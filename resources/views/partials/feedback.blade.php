@if (session('success'))
    <div class="notice success" role="status" x-data="{ visible: true }" x-show="visible" x-init="setTimeout(() => visible = false, 6000)"
        x-transition.opacity>
        {{ session('success') }}<button type="button" @click="visible = false" aria-label="Dispensar mensagem">×</button>
    </div>
@endif
@if ($errors->any())
    <div class="notice error" role="alert"><strong>Revise os campos abaixo.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
