@props([
    'type' => 'text',
    'name',
    'placeholder' => '',
    'value' => null,
])

{{-- Campo de texto con ícono a la derecha, mismo markup/clases que v1 (ad-auth-feilds / ad-input / ad-auth-icon)
     para que el CSS de auth.css lo pinte igual. El ícono se pasa como slot para poder reusar este
     componente con cualquier campo (email, password, lo que se necesite después). --}}
<div {{ $attributes->merge(['class' => 'ad-auth-feilds']) }}>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        value="{{ $value }}"
        class="ad-input"
    />
    <div class="ad-auth-icon">
        {{ $slot }}
    </div>
</div>
