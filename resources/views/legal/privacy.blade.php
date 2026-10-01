@extends('layouts.legal')

@section('title', 'Política de Tratamiento de Datos Personales y Cookies')

@section('content')
<div class="row g-4">
    <!-- Barra lateral navegable (Índice) -->
    <div class="col-lg-3">
        <div class="toc-card p-3 shadow-sm">
            <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                <i class="bi bi-list-nested me-1 text-primary"></i> Contenido
            </h6>
            <nav class="d-flex flex-column gap-1">
                <a href="#seccion-1" class="toc-link">1. Roles y Responsabilidades</a>
                <a href="#seccion-2" class="toc-link">2. Cookies Técnicas Esenciales</a>
                <a href="#seccion-3" class="toc-link">3. Seguridad y Aislamiento</a>
                <a href="#seccion-4" class="toc-link">4. Derechos y Canales Habeas Data</a>
                <a href="#seccion-5" class="toc-link">5. Vigencia y Normatividad</a>
            </nav>

            <hr class="my-3 text-muted">

            <div class="bg-light p-2 rounded-3 small text-muted">
                <div class="fw-semibold text-dark mb-1"><i class="bi bi-shield-lock me-1 text-primary"></i> Habeas Data</div>
                <div>Ley 1581 de 2012</div>
                <div>Decreto 1377 de 2013</div>
                <div class="mt-1 text-secondary">Superintendencia de Industria y Comercio (SIC).</div>
            </div>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="col-lg-9">
        <div class="legal-header shadow-sm">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="legal-badge"><i class="bi bi-shield-check"></i> Protección de Datos Personales</span>
                <span class="badge bg-light text-dark fw-medium">Ley 1581 de 2012</span>
            </div>
            <h1 class="h2 fw-bold mb-2">Política de Privacidad y Tratamiento de Datos</h1>
            <p class="mb-0 text-white-50">
                Lineamientos de privacidad, divulgación de cookies técnicas y régimen de protección de datos personales para la plataforma PuntoStock SaaS.
            </p>
        </div>

        <div class="legal-card p-4 p-md-5 mb-4">
            <p class="lead text-dark">
                En cumplimiento de la <strong>Ley Estatutaria 1581 de 2012</strong>, su Decreto Reglamentario 1377 de 2013 y las directrices de la Superintendencia de Industria y Comercio (SIC), el presente documento establece las condiciones de tratamiento de datos personales y uso de cookies aplicables a los servicios prestados por <strong>PuntoStock</strong>.
            </p>

            <hr class="my-4">

            <!-- Sección 1 -->
            <section id="seccion-1" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">1</span> Roles Jurídicos bajo la Ley 1581 de 2012
                </h3>
                <p>
                    Para los efectos legales y regulatorios colombianos, la interacción y gestión de datos dentro del entorno multi-tenant de PuntoStock distingue taxativamente los siguientes roles:
                </p>

                <div class="clause-box">
                    <h6 class="fw-bold text-dark mb-2">
                        <i class="bi bi-shop me-1 text-primary"></i> A. El Comercio Cliente como «Responsable del Tratamiento»
                    </h6>
                    <p class="small text-secondary mb-0">
                        El comercio suscriptor que utiliza el sistema ostenta la calidad jurídica exclusiva de <strong>Responsable del Tratamiento</strong> sobre las bases de datos de sus consumidores finales (clientes en punto de venta, comensales de restaurante, registros de entrega a domicilio y terceros). Es deber inexcusable del comercio obtener la autorización previa, expresa e informada de dichos titulares, así como atender directamente las solicitudes de consulta o supresión de datos que sus clientes finales promuevan.
                    </p>
                </div>

                <div class="clause-box">
                    <h6 class="fw-bold text-dark mb-2">
                        <i class="bi bi-server me-1 text-primary"></i> B. PuntoStock como «Encargado del Tratamiento»
                    </h6>
                    <p class="small text-secondary mb-0">
                        Respecto a la información de los clientes finales ingresada por los comercios, PuntoStock actúa única y exclusivamente en calidad de <strong>Encargado del Tratamiento</strong>. Su alcance se circunscribe a proveer la infraestructura tecnológica, almacenamiento cifrado y copias de respaldo. <strong>PuntoStock no vende, no comercializa, no cede a terceros, no analiza con fines publicitarios ni realiza explotación económica alguna</strong> sobre las bases de datos de clientes pertenecientes a los comercios.
                    </p>
                </div>

                <div class="clause-box">
                    <h6 class="fw-bold text-dark mb-2">
                        <i class="bi bi-person-badge me-1 text-primary"></i> C. PuntoStock como «Responsable del Tratamiento»
                    </h6>
                    <p class="small text-secondary mb-0">
                        PuntoStock funge como Responsable del Tratamiento <strong>única y estrictamente</strong> frente a los datos comerciales y de contacto del suscriptor titular del negocio (nombre comercial, NIT/cédula, correo electrónico institucional, número telefónico y credenciales de acceso). Estos datos se recolectan con el propósito exclusivo de gestionar la relación contractual de la licencia SaaS, cobros de suscripción, notificaciones operativas y soporte técnico.
                    </p>
                </div>
            </section>

            <!-- Sección 2 -->
            <section id="seccion-2" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">2</span> Política de Cookies Técnicas Esenciales
                </h3>
                <p>
                    PuntoStock aplica una política estricta de <strong>cero rastreo comercial</strong>. La plataforma web utiliza única y exclusivamente cookies de índole técnica, esenciales para el funcionamiento seguro del aplicativo:
                </p>

                <div class="table-responsive my-3">
                    <table class="table table-bordered align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 25%;">Cookie</th>
                                <th style="width: 25%;">Tipo y Origen</th>
                                <th style="width: 50%;">Finalidad Técnica</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-monospace fw-semibold text-primary">laravel_session</td>
                                <td>Técnica / Sesión (Propia)</td>
                                <td>Mantiene el identificador de sesión cifrado del usuario autenticado para permitir la navegación segura entre pantallas sin solicitar credenciales repetitivas.</td>
                            </tr>
                            <tr>
                                <td class="font-monospace fw-semibold text-primary">XSRF-TOKEN</td>
                                <td>Seguridad / Protección (Propia)</td>
                                <td>Token criptográfico generado para prevenir ataques de falsificación de peticiones en sitios cruzados (Cross-Site Request Forgery - CSRF), salvaguardando los formularios.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="clause-box mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shield-slash-fill text-success fs-4"></i>
                        <div>
                            <strong>Ausencia total de cookies comerciales:</strong> PuntoStock no implementa cookies de telemetría invasiva, píxeles de Facebook/Meta, etiquetas de Google Analytics, redes publicitarias ni herramientas de perfilamiento comercial de terceros.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sección 3 -->
            <section id="seccion-3" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">3</span> Seguridad, Encriptación y Aislamiento Multi-Tenant
                </h3>
                <p>
                    Para garantizar la confidencialidad, integridad y disponibilidad de la información procesada conforme a los principios de seguridad de la Ley 1581 de 2012, PuntoStock implementa las siguientes medidas:
                </p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light h-100">
                            <h6 class="fw-bold text-dark"><i class="bi bi-layers-half text-primary me-1"></i> Aislamiento Multi-Tenant</h6>
                            <p class="small text-muted mb-0">
                                Segmentación rigurosa de datos a nivel de aplicación y base de datos mediante identificador forzado (<code>business_id</code>). Ningún comercio o usuario puede consultar o inferir datos pertenecientes a otro establecimiento.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light h-100">
                            <h6 class="fw-bold text-dark"><i class="bi bi-lock-fill text-primary me-1"></i> Encriptación Fuerte</h6>
                            <p class="small text-muted mb-0">
                                Todas las contraseñas se almacenan mediante funciones hash unidireccionales (Bcrypt con salting). Las comunicaciones se transmiten cifradas de extremo a extremo mediante el protocolo TLS/HTTPS.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sección 4 -->
            <section id="seccion-4" class="mb-5">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">4</span> Derechos de los Titulares y Canales Habeas Data
                </h3>
                <p>
                    De conformidad con el artículo 8 de la Ley 1581 de 2012, los titulares de la información gozan de los siguientes derechos fundamentales:
                </p>
                <ul class="mb-3">
                    <li>Conocer, actualizar y rectificar sus datos personales frente a los Responsables del Tratamiento.</li>
                    <li>Solicitar prueba de la autorización otorgada para el tratamiento de su información.</li>
                    <li>Ser informados, previa solicitud, respecto del uso que se ha dado a sus datos.</li>
                    <li>Revocar la autorización o solicitar la supresión del dato cuando en el tratamiento no se respeten los principios, derechos y garantías constitucionales y legales.</li>
                </ul>

                <div class="clause-box">
                    <h6 class="fw-bold text-dark mb-2">Canal Oficial de Atención:</h6>
                    <p class="small mb-2">
                        Los suscriptores directos de PuntoStock pueden elevar consultas o reclamos de Habeas Data remitiendo una comunicación formal con su identificación y detalle de solicitud al canal oficial:
                    </p>
                    <div class="d-flex flex-wrap gap-3 small">
                        <span class="text-dark"><i class="bi bi-envelope-at-fill text-primary me-1"></i> legal@puntostock.co</span>
                        <a href="https://wa.me/{{ config('app.support_whatsapp', '573163765939') }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-success fw-semibold">
                            <i class="bi bi-whatsapp me-1"></i>Atención Inmediata WhatsApp
                        </a>
                    </div>
                </div>
            </section>

            <!-- Sección 5 -->
            <section id="seccion-5" class="mb-4">
                <h3 class="h4 section-title mb-3">
                    <span class="badge bg-primary-subtle text-primary me-2">5</span> Vigencia y Modificaciones
                </h3>
                <p>
                    La presente política de tratamiento rige a partir de su publicación electrónica y permanecerá vigente mientras se mantengan las actividades del servicio SaaS. Cualquier modificación sustancial en las finalidades del tratamiento será divulgada oportunamente a través de los canales informativos de la plataforma antes de su entrada en vigor.
                </p>
                <div class="alert alert-light border small text-muted">
                    <i class="bi bi-calendar-event me-1 text-primary"></i> <strong>Última actualización:</strong> Septiembre de 2026. Cali, Valle del Cauca, República de Colombia.
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
