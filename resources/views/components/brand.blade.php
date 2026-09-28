@props(['size' => 'sm'])

@php
    $isLg = $size === 'lg';
    $imgHeight = $isLg ? '56px' : '32px';
    $textSizeClass = $isLg ? 'fs-2' : 'fs-5';
@endphp

<div {{ $attributes->merge(['class' => 'd-inline-flex align-items-center gap-2']) }}>
    <img src="{{ asset('images/brand/logo-mark.svg') }}" 
         alt="" 
         aria-hidden="true" 
         style="height: {{ $imgHeight }}; width: auto; flex-shrink: 0;">
    <span class="{{ $textSizeClass }} lh-1" style="color: #1B2A49 !important; letter-spacing: -0.02em;">
        <span class="fw-bold">Punto</span><span class="fw-medium">Stock</span>
    </span>
</div>
