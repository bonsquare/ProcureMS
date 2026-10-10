{{-- Number inputs: no up/down arrows, and the mouse wheel or arrow keys never change the value by accident. --}}
<style>
    input[type=number] { -moz-appearance: textfield; appearance: textfield; }
    input[type=number]::-webkit-outer-spin-button, input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
</style>
<script>
    document.addEventListener('wheel', (event) => { const field = document.activeElement; if (field && field.type === 'number' && field === event.target) field.blur(); }, { passive: true });
    document.addEventListener('keydown', (event) => { if ((event.key === 'ArrowUp' || event.key === 'ArrowDown') && event.target.type === 'number') event.preventDefault(); });
</script>

@include('partials.password-toggle')
