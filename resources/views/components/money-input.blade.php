@props(['model'])
<input
    {{ $attributes->merge(['type' => 'text', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'placeholder' => '0,00']) }}
    data-money-input x-data="moneyInput($wire, {{ \Illuminate\Support\Js::from($model) }})" x-bind:value="display" x-on:input="update($event)"
    x-on:keydown="decimalKey($event)" x-on:blur="format()">
