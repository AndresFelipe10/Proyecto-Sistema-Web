@extends('layouts.app')

@section('title', 'Asistente de Consultas IA')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold mb-1">
            <i class="bi bi-stars text-primary me-1"></i> Asistente de Consultas
        </h3>
        <p class="text-muted small mb-0">
            Pregunta en lenguaje natural sobre ventas, inventario, clientes y catálogo de <strong>{{ $currentBusiness->name }}</strong>.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
            <i class="bi bi-shield-check me-1"></i> Solo Lectura
        </span>
        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
            <i class="bi bi-building-check me-1"></i> Tenant Aislado
        </span>
        @if(!$enabled)
            <span class="badge bg-warning-subtle text-dark rounded-pill px-3 py-2">
                <i class="bi bi-power me-1"></i> Desactivado en .env
            </span>
        @endif
    </div>
</div>

@if(!$enabled)
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
            <div>
                <h5 class="fw-bold mb-1">Módulo de IA Desactivado</h5>
                <p class="mb-2 text-muted">
                    El asistente se encuentra desactivado mediante la variable <code>AI_MODULE_ENABLED=false</code> en la configuración.
                    Tal como establece la arquitectura del proyecto, <strong>el núcleo del sistema funciona al 100% de manera independiente</strong>.
                </p>
                <small class="text-secondary">
                    Para activarlo, define <code>AI_MODULE_ENABLED=true</code> y proporciona una <code>GEMINI_API_KEY</code> en tu archivo <code>.env</code>.
                </small>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Contenedor del Chat --}}
        <div class="card card-custom bg-white border-0 shadow-sm d-flex flex-column" style="min-height: 520px; height: 600px;">
            <div class="card-header bg-transparent border-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                <span class="small fw-semibold text-muted text-uppercase">Conversación</span>
                <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none" id="clearChatBtn">
                    <i class="bi bi-trash me-1"></i> Limpiar
                </button>
            </div>

            <div class="card-body px-4 overflow-y-auto flex-grow-1" id="chatMessages" style="scroll-behavior: smooth;">
                {{-- Mensaje Inicial de Bienvenida --}}
                <div class="d-flex gap-3 mb-4">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bi bi-stars fs-5"></i>
                    </div>
                    <div class="bg-light p-3 rounded-4 shadow-sm" style="max-width: 85%;">
                        <p class="mb-1 text-dark">
                            ¡Hola! Soy tu asistente de consultas de <strong>{{ $currentBusiness->name }}</strong>.
                        </p>
                        <p class="mb-0 small text-muted">
                            Puedes preguntarme sobre productos agotados, existencias críticas, volumen de ventas de hoy o del mes, clientes registrados y más.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Formulario de Entrada --}}
            <div class="card-footer bg-white border-0 p-3">
                <form id="queryForm" class="d-flex gap-2">
                    @csrf
                    <input type="text" id="userInput" class="form-control rounded-pill px-4 py-2"
                           placeholder="Escribe tu consulta aquí (ej: ¿Cuáles productos tienen bajo stock?)..."
                           autocomplete="off" maxlength="500">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold d-flex align-items-center gap-2" id="sendBtn">
                        <span>Enviar</span>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Sugerencias Rápidas --}}
        <div class="card card-custom bg-white border-0 shadow-sm p-4 mb-4">
            <h6 class="fw-bold mb-3 text-dark">
                <i class="bi bi-lightbulb text-warning me-1"></i> Preguntas Frecuentes
            </h6>
            <div class="d-flex flex-column gap-2" id="suggestionChips">
                @foreach ($suggestions as $suggestion)
                    <button type="button" class="btn btn-sm btn-outline-secondary text-start rounded-pill py-2 px-3 suggestion-btn text-truncate">
                        <i class="bi bi-arrow-return-right me-1 text-primary"></i> {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Reglas de Seguridad Visibles al Usuario --}}
        <div class="card card-custom bg-light border-0 p-3">
            <h6 class="fw-bold small text-muted text-uppercase mb-2">Garantías de Seguridad</h6>
            <ul class="list-unstyled small text-muted mb-0 d-flex flex-column gap-2">
                <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Solo Lectura:</strong> Imposible alterar o borrar datos por error.</li>
                <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Aislamiento Estricto:</strong> Solo accede a datos de {{ $currentBusiness->name }}.</li>
                <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Sin SQL arbitrario:</strong> Intenciones verificadas contra una whitelist fija.</li>
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('queryForm');
    const input = document.getElementById('userInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatContainer = document.getElementById('chatMessages');
    const clearBtn = document.getElementById('clearChatBtn');
    const suggestionBtns = document.querySelectorAll('.suggestion-btn');

    const scrollToBottom = () => {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    };

    const appendUserMessage = (text) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex justify-content-end mb-3';
        wrapper.innerHTML = `
            <div class="bg-primary text-white p-3 rounded-4 shadow-sm" style="max-width: 80%;">
                <p class="mb-0">${escapeHtml(text)}</p>
            </div>
        `;
        chatContainer.appendChild(wrapper);
        scrollToBottom();
    };

    const appendAssistantMessage = (summary, data = []) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex gap-3 mb-4';

        let dataHtml = '';
        if (Array.isArray(data) && data.length > 0) {
            const firstItem = data[0];
            const keys = Object.keys(firstItem).filter(k => k !== 'id');

            dataHtml = `
                <div class="table-responsive mt-2 bg-white rounded-3 border">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                ${keys.map(k => `<th class="text-uppercase">${escapeHtml(k.replace('_', ' '))}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(row => `
                                <tr>
                                    ${keys.map(k => `<td>${escapeHtml(String(row[k] ?? ''))}</td>`).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        wrapper.innerHTML = `
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                <i class="bi bi-stars fs-5"></i>
            </div>
            <div class="bg-light p-3 rounded-4 shadow-sm" style="max-width: 85%;">
                <p class="mb-1 text-dark">${escapeHtml(summary)}</p>
                ${dataHtml}
            </div>
        `;
        chatContainer.appendChild(wrapper);
        scrollToBottom();
    };

    const appendLoading = () => {
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'chatLoading';
        loadingDiv.className = 'd-flex gap-3 mb-3';
        loadingDiv.innerHTML = `
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                <div class="spinner-border spinner-border-sm text-light" role="status"></div>
            </div>
            <div class="bg-light p-3 rounded-4 text-muted small">
                Analizando tu consulta con seguridad...
            </div>
        `;
        chatContainer.appendChild(loadingDiv);
        scrollToBottom();
    };

    const removeLoading = () => {
        const loading = document.getElementById('chatLoading');
        if (loading) loading.remove();
    };

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    const sendQuery = async (queryText) => {
        if (!queryText.trim()) return;

        appendUserMessage(queryText);
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;
        appendLoading();

        try {
            const response = await fetch("{{ route('ai.ask') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ query: queryText })
            });

            removeLoading();

            if (!response.ok) {
                appendAssistantMessage("El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario.");
                return;
            }

            const resData = await response.json();
            appendAssistantMessage(resData.summary, resData.data);
        } catch (error) {
            removeLoading();
            appendAssistantMessage("El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario.");
        } finally {
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    };

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        sendQuery(input.value);
    });

    suggestionBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const text = btn.textContent.trim();
            sendQuery(text);
        });
    });

    clearBtn.addEventListener('click', () => {
        chatContainer.innerHTML = `
            <div class="d-flex gap-3 mb-4">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bi bi-stars fs-5"></i>
                </div>
                <div class="bg-light p-3 rounded-4 shadow-sm" style="max-width: 85%;">
                    <p class="mb-0 small text-muted">Historial reiniciado. Formula tu pregunta cuando desees.</p>
                </div>
            </div>
        `;
    });
});
</script>
@endpush
