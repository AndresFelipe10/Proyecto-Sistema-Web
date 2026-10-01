{{-- Modal de Configuración de Impresión de Comandas y Cocina --}}
<div class="modal fade" id="kitchenConfigModal" tabindex="-1" aria-labelledby="kitchenConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark" id="kitchenConfigModalLabel">
                    <i class="bi bi-printer-fill text-primary me-2"></i>Configuración de Comandas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="form-check form-switch d-flex justify-content-between align-items-center ps-0">
                        <label class="form-check-label fw-bold text-dark mb-0 pe-3" for="switchAutoPrintKitchen">
                            Impresión automática a cocina (80mm)
                            <span class="d-block small fw-normal text-muted mt-1">
                                Abre automáticamente el ticket térmico al abrir una comanda o agregar platos.
                            </span>
                        </label>
                        <input class="form-check-input ms-0 fs-4" type="checkbox" role="switch" id="switchAutoPrintKitchen" checked style="cursor: pointer;">
                    </div>
                </div>

                <div class="alert alert-info border-0 rounded-3 small mb-2">
                    <div class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-1"></i> Gestión Inteligente de Impresoras:</div>
                    <p class="mb-2">
                        Los navegadores modernos (Google Chrome y Microsoft Edge) recuerdan de forma independiente la última impresora que seleccionas para cada ventana:
                    </p>
                    <ul class="mb-0 ps-3">
                        <li><strong>Cocina:</strong> Selecciona tu impresora térmica de 80mm al imprimir la comanda de cocina.</li>
                        <li><strong>Caja:</strong> Selecciona tu impresora de facturas al cobrar. El navegador recordará automáticamente el destino correcto para cada una.</li>
                    </ul>
                </div>

                <p class="text-muted small mb-0">
                    <i class="bi bi-shield-check text-success me-1"></i> Si el navegador bloquea las ventanas emergentes, haz clic en el icono de la barra de direcciones y selecciona <em>"Permitir siempre ventanas emergentes de este sitio"</em>.
                </p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-primary w-100 fw-bold py-2" data-bs-dismiss="modal" id="btnSaveKitchenConfig">
                    <i class="bi bi-check2 me-1"></i> Guardar Preferencia
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const switchAuto = document.getElementById('switchAutoPrintKitchen');
    const saveBtn = document.getElementById('btnSaveKitchenConfig');

    // Cargar estado desde localStorage (por defecto activado)
    const isAutoPrint = localStorage.getItem('puntostock_auto_print_kitchen') !== 'disabled';
    if (switchAuto) {
        switchAuto.checked = isAutoPrint;
    }

    if (saveBtn && switchAuto) {
        saveBtn.addEventListener('click', function () {
            if (switchAuto.checked) {
                localStorage.setItem('puntostock_auto_print_kitchen', 'enabled');
            } else {
                localStorage.setItem('puntostock_auto_print_kitchen', 'disabled');
            }
        });
    }
});
</script>
