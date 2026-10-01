@extends('layouts.legal')

@section('title', 'Términos y Condiciones del Servicio')

@section('content')
<div class="row g-4">
    <!-- Barra lateral navegable (Índice) -->
    <div class="col-lg-3">
        <div class="toc-card p-3 shadow-sm">
            <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                <i class="bi bi-list-nested me-1 text-primary"></i> Contenido
            </h6>
            <nav class="d-flex flex-column gap-1">
                <a href="#seccion-1" class="toc-link">1. Naturaleza del Servicio</a>
                <a href="#seccion-2" class="toc-link">2. Propiedad de la Información</a>
                <a href="#seccion-3" class="toc-link">3. Límite de Responsabilidad</a>
                <a href="#seccion-4" class="toc-link">4. Responsabilidad Fiscal (DIAN)</a>
                <a href="#seccion-5" class="toc-link">5. Suspensión y Retención</a>
                <a href="#seccion-6" class="toc-link">6. Validez y Aceptación (Ley 527)</a>
            </nav>

            <hr class="my-3 text-muted">

            <div class="bg-light p-2 rounded-3 small text-muted">
                <div class="fw-semibold text-dark mb-1"><i class="bi bi-info-circle me-1 text-primary"></i> Vigencia Contractual</div>
                <div>Versión: 1.0</div>
                <div>Actualizado: {{ date('Y') }}</div>
                <div class="mt-1 text-secondary">Aplica a todos los comercios suscriptores en Colombia.</div>
            </div>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="col-lg-9">
        <div class="legal-header shadow-sm">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="legal-badge"><i class="bi bi-shield-check"></i> Marco Contractual SaaS</span>
                <span class="badge bg-light text-dark fw-medium">República de Colombia</span>
            </div>
            <h1 class="h2 fw-bold mb-2">Términos y Condiciones del Servicio</h1>
            <p class="mb-0 text-white-50">
                Reglamento contractual de suscripción y uso de la plataforma PuntoStock bajo la modalidad Software como Servicio (SaaS).
            </p>
        </div>

        <div class="legal-card p-4 p-md-5 mb-4">
            <p class="lead text-dark">
                El presente contrato regula los términos y condiciones aplicables a la suscripción, acceso y utilización del sistema de gestión comercial, comandas e inventario <strong>PuntoStock</strong> por parte del comercio suscriptor y sus usuarios autorizados.
            </p>

            <hr class="my-4">

            <!-- Sección 1 -->
            <section id="seccion-1" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">1</span> Naturaleza del Servicio y Licencia SaaS
                </h3>
                <p>
                    <strong>PuntoStock</strong> es una solución tecnológica desarrollada y operada bajo el modelo de <em>Software como Servicio</em> (SaaS por sus siglas en inglés). La suscripción al servicio confiere al comercio suscriptor una <strong>licencia de uso no exclusiva, revocable, intransferible y de alcance limitado</strong>, destinada de forma privativa a la gestión operativa, control de inventario, punto de venta (POS), facturación comercial interna, comandas de mesas de restaurante y reportería estadística.
                </p>
                <div class="clause-box">
                    <strong>Reserva de Propiedad Intelectual:</strong> La contratación del servicio no transfiere, vende ni cede el código fuente, diseño, bases arquitectónicas, marcas o derechos de autor de la plataforma. Queda estrictamente prohibida la ingeniería inversa, descompilación, reproducción no autorizada o sublicenciamiento de los módulos que integran PuntoStock.
                </div>
            </section>

            <!-- Sección 2 -->
            <section id="seccion-2" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">2</span> Titularidad, Propiedad y Portabilidad de la Información
                </h3>
                <p>
                    El comercio cliente es el <strong>dueño y titular exclusivo y absoluto</strong> de todas las bases de datos y registros que ingrese, procese o gestione a través de la plataforma, incluyendo de manera enunciativa pero no limitativa: catálogo de productos, listados de precios, directorio de clientes, historial de ventas, registros de comandas y comprobantes de egresos.
                </p>
                <p>
                    PuntoStock garantiza expresamente el derecho de portabilidad de datos sin costes adicionales arbitrarios:
                </p>
                <div class="clause-box">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">
                            <strong>Exportación nativa:</strong> El comercio podrá descargar sus catálogos, clientes y ventas en formatos abiertos universales (archivos CSV / Excel) directamente desde las pantallas de la plataforma durante todo el tiempo que mantenga su cuenta activa.
                        </li>
                        <li>
                            <strong>Período de rescate post-terminación:</strong> En caso de cancelación voluntaria o terminación contractual del servicio, PuntoStock mantendrá disponibles las funciones de exportación de bases de datos por un término improrrogable de <strong>treinta (30) días calendario</strong> contados a partir de la fecha efectiva de culminación.
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Sección 3 -->
            <section id="seccion-3" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">3</span> Límite de Responsabilidad Tecnológica y Operativa
                </h3>
                <p>
                    PuntoStock se ejecuta sobre centros de datos en la nube de clase empresarial (Amazon Web Services - AWS) implementando esquemas de alta disponibilidad, seguridad perimetral y réplicas de datos para minimizar contingencias. Sin embargo, en virtud de la naturaleza descentralizada de las redes de comunicaciones, el suscriptor reconoce y acepta expresamente las siguientes exoneraciones de responsabilidad:
                </p>
                <div class="clause-box">
                    <p class="fw-semibold mb-2 text-dark">Exclusiones formales de responsabilidad:</p>
                    <ol class="mb-0 ps-3">
                        <li class="mb-2"><strong>Suministro eléctrico local:</strong> Interrupciones, cortes o fluctuaciones en la energía eléctrica de los establecimientos físicos del comercio.</li>
                        <li class="mb-2"><strong>Conectividad a Internet:</strong> Degradación, intermitencia, caídas o limitaciones técnicas atribuibles al proveedor de acceso a internet (ISP) contratado por el comercio.</li>
                        <li class="mb-2"><strong>Fuerza mayor e infraestructura de nube:</strong> Indisponibilidad regional o global fortuita de la infraestructura de telecomunicaciones o de los centros de cómputo en la nube (AWS).</li>
                        <li><strong>Hardware local del comercio:</strong> Averías mecánicas, falta de consumibles (papel térmico), desconfiguración de controladores o incompatibilidad física en terminales, impresoras térmicas de tickets, computadores, tablets o lectores de códigos.</li>
                    </ol>
                </div>
            </section>

            <!-- Sección 4 -->
            <section id="seccion-4" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">4</span> Responsabilidad Fiscal, Tributaria y Facturación (DIAN)
                </h3>
                <p>
                    PuntoStock constituye un software de registro y apoyo a la operación interna comercial y gastronómica. En ninguna circunstancia reemplaza las obligaciones tributarias sustanciales y formales que le atañen al contribuyente frente a las autoridades fiscales de Colombia:
                </p>
                <div class="clause-box">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-octagon-fill text-danger fs-5 mt-1"></i>
                        <div>
                            <div class="fw-bold text-dark mb-1">Obligación Exclusiva e Indelegable del Comercio:</div>
                            <p class="mb-2 small">
                                La obligación de expedir factura de venta conforme a los calendarios, requisitos y topes de <strong>Facturación Electrónica de la Dirección de Impuestos y Aduanas Nacionales (DIAN)</strong> corresponde única y exclusivamente al comercio titular.
                            </p>
                            <p class="mb-0 small">
                                De igual forma, la determinación, liquidación, reporte y pago oportuno de tributos de orden nacional o territorial —incluyendo el <strong>Impuesto Nacional al Consumo (INC 8%)</strong> en restaurantes y cafeterías, el <strong>Impuesto sobre las Ventas (IVA)</strong> o el impuesto de Industria y Comercio (ICA)— recae exclusivamente sobre el suscriptor. Los comprobantes impresos o pre-cuentas de restaurante emitidos por PuntoStock son documentos de control interno y <strong>no constituyen factura electrónica ante la DIAN</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sección 5 -->
            <section id="seccion-5" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">5</span> Ciclo de Suspensión, Retención de Datos y Purga
                </h3>
                <p>
                    La suscripción a PuntoStock opera mediante el esquema de <strong>pago anticipado por períodos de treinta (30) días calendario</strong>. Los ciclos de continuidad se rigen por las siguientes etapas:
                </p>
                <div class="clause-box">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">
                            <strong>Período de gracia:</strong> Llegada la fecha de corte sin registro del pago del nuevo período, el comercio dispondrá de una ventana de cortesía de <strong>tres (3) días calendario</strong> antes de suspender el acceso operativo a la plataforma.
                        </li>
                        <li class="mb-2">
                            <strong>Retención de custodia (60 días):</strong> Trascurridos los 3 días de gracia y suspendida la cuenta, PuntoStock conservará y salvaguardará de forma inalterada la información del negocio durante <strong>sesenta (60) días calendario</strong> para posibilitar la regularización de la suscripción o la entrega de respaldos.
                        </li>
                        <li>
                            <strong>Purga definitiva por abandono:</strong> Si al término de los sesenta (60) días de retención no se hubiese reactivado el servicio ni coordinado el retiro de la información, PuntoStock procederá a la <strong>eliminación y purga definitiva de las bases de datos</strong> de forma segura e irreversible, quedando exonerado de cualquier deber de archivo posterior.
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Sección 6 -->
            <section id="seccion-6" class="mb-4">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">6</span> Validez Jurídica de la Aceptación Electrónica (Ley 527 de 1999)
                </h3>
                <p>
                    Conforme a la <strong>Ley 527 de 1999</strong> (Régimen de Comercio Electrónico y Mensajes de Datos de Colombia) y el Código de Comercio colombiano, el consentimiento contractual se perfecciona mediante la marcación de la casilla de verificación (checkbox) al activar o actualizar las credenciales de acceso al sistema.
                </p>
                <p>
                    PuntoStock registra de manera inmutable en su base de datos la estampa cronológica (fecha y hora exacta en zona horaria de Colombia) y la dirección IP pública de origen desde la cual se manifestó la voluntad, confiriéndole <strong>plena fuerza vinculante, validez legal y mérito probatorio</strong> entre las partes.
                </p>
                <div class="alert alert-light border small text-muted mt-3">
                    <i class="bi bi-geo-alt-fill me-1 text-primary"></i> <strong>Jurisdicción y Ley Aplicable:</strong> Cualquier divergencia derivada del presente acuerdo será sometida a las leyes de la República de Colombia y a los jueces competentes de la ciudad de Cali, Departamento del Valle del Cauca.
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
