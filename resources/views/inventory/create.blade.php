@extends('layouts.app')

@section('title', 'Registrar Movimiento de Inventario')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card card-custom p-4 p-md-5 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Registrar Movimiento</h3>
                    <p class="text-muted small mb-0">Modifica y audita las existencias de un producto en <strong>{{ $currentBusiness->name }}</strong>.</p>
                </div>
                <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
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

            <form method="POST" action="{{ route('inventory.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="product_id" class="form-label small fw-semibold text-secondary">Producto <span class="text-danger">*</span></label>
                    <select class="form-select @error('product_id') is-invalid @enderror" id="product_id" name="product_id" required autofocus>
                        <option value="" disabled {{ old('product_id', $selectedProductId) ? '' : 'selected' }}>Selecciona un producto...</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" {{ old('product_id', $selectedProductId) == $product->id ? 'selected' : '' }}>
                                {{ $product->name }} (SKU: {{ $product->sku }}) — Stock actual: {{ $product->stock }} unid.
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="type" class="form-label small fw-semibold text-secondary">Tipo de Movimiento <span class="text-danger">*</span></label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="entry" {{ old('type') === 'entry' ? 'selected' : '' }}>Entrada / Compra (+)</option>
                            <option value="exit" {{ old('type') === 'exit' ? 'selected' : '' }}>Salida / Merma / Consumo (-)</option>
                            <option value="adjustment" {{ old('type') === 'adjustment' ? 'selected' : '' }}>Ajuste de Conteo Físico (~)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="quantity" class="form-label small fw-semibold text-secondary">Cantidad / Nuevo Stock <span class="text-danger">*</span></label>
                        <input type="number" 
                               min="0" 
                               class="form-control @error('quantity') is-invalid @enderror" 
                               id="quantity" 
                               name="quantity" 
                               value="{{ old('quantity', '1') }}" 
                               required>
                        <div class="form-text small" id="quantityHelper">
                            En entradas/salidas indica las unidades a sumar/restar. En ajustes indica el stock total real.
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="reason" class="form-label small fw-semibold text-secondary">Motivo o Justificación <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control @error('reason') is-invalid @enderror" 
                           id="reason" 
                           name="reason" 
                           value="{{ old('reason') }}" 
                           placeholder="Ej. Compra a proveedor, Producto dañado, Conteo mensual..." 
                           required>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary px-4 rounded-3">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i> Aplicar y Guardar Movimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
