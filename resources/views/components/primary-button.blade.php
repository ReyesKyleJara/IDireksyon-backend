<button {{ $attributes->merge(['type' => 'submit', 'class' => 'admin-primary border border-transparent focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
