@extends('layouts.app')

@section('title', 'Editar Receta')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Editar Receta: {{ $recipe->name }}</h3>
                <p class="text-muted small mb-0">Modifica los insumos y porciones asociadas a este plato.</p>
            </div>
            <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Volver al listado
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrige los siguientes errores:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card card-custom p-4 bg-white">
            <form method="POST" action="{{ route('recipes.update', $recipe) }}" id="recipeForm" novalidate>
                @csrf
                @method('PUT')

                <h5 class="fw-bold text-primary mb-3"><i class="bi bi-journal-check me-2"></i>1. Datos del Plato y Receta</h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-5">
                        <label for="product_id" class="form-label small fw-semibold text-secondary">Plato que se vende al cliente <span class="text-danger">*</span></label>
                        <select class="form-select @error('product_id') is-invalid @enderror" id="product_id" name="product_id" required>
                            @foreach ($dishes as $dish)
                                <option value="{{ $dish->id }}" {{ old('product_id', $recipe->product_id) == $dish->id ? 'selected' : '' }}>
                                    🍲 {{ $dish->name }} (SKU: {{ $dish->sku }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label for="name" class="form-label small fw-semibold text-secondary">Nombre de la Receta <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $recipe->name) }}" 
                               required>
                    </div>

                    <div class="col-md-2">
                        <label for="is_active" class="form-label small fw-semibold text-secondary">Estado</label>
                        <select class="form-select" id="is_active" name="is_active">
                            <option value="1" {{ old('is_active', $recipe->is_active) ? 'selected' : '' }}>Activa</option>
                            <option value="0" {{ !old('is_active', $recipe->is_active) ? 'selected' : '' }}>Inactiva</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 border-top pt-4">
                    <div>
                        <h5 class="fw-bold text-primary mb-0"><i class="bi bi-basket me-2"></i>2. Insumos e Ingredientes por Porción</h5>
                        <p class="text-muted small mb-0">Indica la cantidad exacta de materia prima consumida por 1 plato servido.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="btnAddIngredient">
                        <i class="bi bi-plus-lg me-1"></i> Agregar Insumo
                    </button>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle" id="ingredientsTable">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th style="width: 45%;">Insumo / Materia Prima</th>
                                <th style="width: 25%;">Cantidad por Porción</th>
                                <th style="width: 20%;">Unidad</th>
                                <th style="width: 10%;" class="text-center">Quitar</th>
                            </tr>
                        </thead>
                        <tbody id="ingredientsTbody">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                    <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-check-circle me-1"></i> Actualizar Receta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="ingredientRowTemplate">
    <tr class="ingredient-row">
        <td>
            <select class="form-select select-ingredient" required>
                <option value="">Selecciona el insumo...</option>
                @foreach ($ingredients as $ing)
                    <option value="{{ $ing->id }}" data-unit="{{ $ing->base_unit }}">
                        🥩 {{ $ing->name }} (Unidad base: {{ $ing->base_unit }})
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" 
                   step="any" 
                   min="0.001" 
                   class="form-control input-qty" 
                   placeholder="Ej. 150 o 0.25" 
                   required>
        </td>
        <td>
            <input type="text" 
                   class="form-control input-unit" 
                   placeholder="Gramos, ml, etc." 
                   readonly 
                   required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle btn-remove" title="Quitar insumo">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('ingredientsTbody');
    const template = document.getElementById('ingredientRowTemplate');
    const btnAdd = document.getElementById('btnAddIngredient');

    let rowIndex = 0;

    function addRow(ingredientId = '', qty = '', unit = '') {
        const clone = template.content.cloneNode(true);
        const tr = clone.querySelector('tr');

        const select = tr.querySelector('.select-ingredient');
        select.name = `items[${rowIndex}][ingredient_id]`;
        if (ingredientId) select.value = ingredientId;

        const inputQty = tr.querySelector('.input-qty');
        inputQty.name = `items[${rowIndex}][quantity_per_portion]`;
        if (qty) inputQty.value = qty;

        const inputUnit = tr.querySelector('.input-unit');
        inputUnit.name = `items[${rowIndex}][unit]`;
        if (unit) inputUnit.value = unit;

        select.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            const baseUnit = selectedOpt.getAttribute('data-unit') || 'unit';
            inputUnit.value = baseUnit;
        });

        tr.querySelector('.btn-remove').addEventListener('click', function () {
            if (tbody.querySelectorAll('tr').length > 1) {
                tr.remove();
            } else {
                alert('La receta debe contener al menos un ingrediente.');
            }
        });

        tbody.appendChild(tr);
        rowIndex++;
    }

    btnAdd.addEventListener('click', () => addRow());

    // Cargar ítems existentes de la receta o desde old()
    @if (old('items'))
        @foreach (old('items') as $idx => $oldItem)
            addRow("{{ $oldItem['ingredient_id'] ?? '' }}", "{{ $oldItem['quantity_per_portion'] ?? '' }}", "{{ $oldItem['unit'] ?? '' }}");
        @endforeach
    @elseif ($recipe->items->isNotEmpty())
        @foreach ($recipe->items as $item)
            addRow("{{ $item->ingredient_id }}", "{{ $item->quantity_per_portion }}", "{{ $item->unit }}");
        @endforeach
    @else
        addRow();
    @endif
});
</script>
@endpush
@endsection
