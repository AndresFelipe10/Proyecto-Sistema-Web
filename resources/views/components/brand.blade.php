@props(['size' => 'sm', 'light' => false, 'inverted' => false])

@php
    $isLg = $size === 'lg';
    $isLight = (bool) ($light || $inverted);
    $imgHeight = $isLg ? '56px' : '32px';
    $textSizeClass = $isLg ? 'fs-2' : 'fs-5';
    $logoSrc = $isLight ? asset('images/brand/logo-mark-white.svg') : asset('images/brand/logo-mark.svg');
    $textColor = $isLight ? '#FFFFFF' : '#1B2A49';
@endphp

<div {{ $attributes->merge(['class' => 'd-inline-flex align-items-center gap-2']) }}>
    <img src="{{ $logoSrc }}" 
         alt="" 
         aria-hidden="true" 
         style="height: {{ $imgHeight }}; width: auto; flex-shrink: 0;">
    <span class="{{ $textSizeClass }} lh-1 {{ $isLight ? 'text-white' : '' }}" style="color: {{ $textColor }} !important; letter-spacing: -0.02em;">
        <span class="fw-bold">Punto</span><span class="fw-medium">Stock</span>
    </span>
</div>

