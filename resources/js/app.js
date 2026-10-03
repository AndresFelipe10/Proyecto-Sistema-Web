import './bootstrap';

// Ergonomía global: Auto-selección en foco y eliminación reactiva de ceros parásitos
export function setupNumberInputErgonomics() {
    document.querySelectorAll('input[type="number"], .currency-input, input[name="cost_price"], input[name="sale_price"], input[name="stock"], input[name="min_stock"]').forEach(input => {
        if (input.dataset.ergonomicsAttached) return;
        input.dataset.ergonomicsAttached = 'true';

        input.addEventListener('focus', function () {
            this.select();
        });
        input.addEventListener('mouseup', function (e) {
            e.preventDefault();
        });
    });
}

document.addEventListener('focusin', function (e) {
    const target = e.target;
    if (target && target.matches('input[type="number"], .currency-input, input[name="cost_price"], input[name="sale_price"], input[name="stock"], input[name="min_stock"]')) {
        setTimeout(() => {
            target.select();
        }, 0);
    }
});

document.addEventListener('input', function (e) {
    const target = e.target;
    if (target && target.matches('input[type="number"], .currency-input, input[name="cost_price"], input[name="sale_price"], input[name="stock"], input[name="min_stock"]')) {
        const val = target.value;
        if (/^0+[1-9]/.test(val)) {
            target.value = val.replace(/^0+/, '');
        }
    }
});

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', setupNumberInputErgonomics);
}

