@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'admin-input']) }}>
