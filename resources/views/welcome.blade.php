<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Control de acceso y citas para exámenes del Hospital La Merced Paita">
    <title>Acceso Clínico | Hospital La Merced Paita</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark"><img src="{{ asset('logo-hospital-la-merced.png') }}" alt="Logo del Hospital La Merced Paita"></div>
            <div><strong>Hospital La Merced</strong><small>Paita · Piura</small></div>
        </div>
        <nav aria-label="Navegación principal">
            <a class="nav-item active" href="{{ route('inicio') }}"><span class="nav-icon">⌂</span>Inicio</a>
            <a class="nav-item" href="{{ route('citas') }}"><span class="nav-icon">▣</span>Citas del día <b>18</b></a>
            <a class="nav-item" href="{{ route('pacientes') }}"><span class="nav-icon">⌕</span>Buscar paciente</a>
            <a class="nav-item" href="{{ route('accesos') }}"><span class="nav-icon">⇄</span>Registrar acceso</a>
            <a class="nav-item" href="{{ route('historial') }}"><span class="nav-icon">◷</span>Historial</a>
        </nav>
        <div class="side-divider"></div>
        <p class="nav-label">GESTIÓN</p>
        <nav>
            <a class="nav-item" href="{{ route('configuracion') }}"><span class="nav-icon">⚙</span>Configuración</a>
            <a class="nav-item" href="{{ route('ayuda') }}"><span class="nav-icon">?</span>Ayuda</a>
        </nav>
        <div class="shift-card">
            <span class="shift-dot"></span>
            <div><small>TURNO ACTIVO</small><strong>07:00 — 15:00</strong><p>Puerta principal</p></div>
        </div>
        <div class="profile">
            <div class="avatar">CM</div>
            <div><strong>Carlos Mendoza</strong><small>Control de acceso</small></div>
            <button aria-label="Más opciones">•••</button>
        </div>
    </aside>

    <main>
        <header class="topbar">
            <button class="menu-btn" id="menuBtn" aria-label="Abrir menú">☰</button>
            <div class="mobile-brand"><img src="{{ asset('logo-hospital-la-merced.png') }}" alt=""> La Merced Paita</div>
            <div class="top-actions">
                <span class="live"><i></i>Sistema operativo</span>
                <button class="icon-btn" aria-label="Notificaciones">♢<em>2</em></button>
                <div class="today"><small>MIÉRCOLES</small><strong>05 AGO 2026</strong></div>
            </div>
        </header>

        <div class="content">
            <section class="hero">
                <div><p class="eyebrow">CONTROL DE ACCESO</p><h1>Buenos días, Carlos</h1><p>Estas son las citas programadas para exámenes hoy.</p></div>
                <button class="primary-btn" id="scanBtn"><span>▦</span> Escanear código QR</button>
            </section>

            <section class="stats" aria-label="Resumen de citas">
                <article><div class="stat-icon blue">▣</div><div><small>CITAS PROGRAMADAS</small><strong>18</strong><p><b>+3</b> desde ayer</p></div></article>
                <article><div class="stat-icon green">✓</div><div><small>YA INGRESARON</small><strong>11</strong><p>61% del total</p></div><div class="ring" style="--value:61">61%</div></article>
                <article><div class="stat-icon amber">◷</div><div><small>POR LLEGAR</small><strong>6</strong><p>Próximo: <b>09:40</b></p></div></article>
                <article><div class="stat-icon red">!</div><div><small>CON RETRASO</small><strong>1</strong><p><b>15 min</b> de demora</p></div></article>
            </section>

            <section class="appointments" id="citas">
                <div class="section-head">
                    <div><h2>Citas para exámenes</h2><p><span></span>Actualizado hace un momento</p></div>
                    <div class="view-tabs"><button class="active">Lista</button><button>Horario</button></div>
                </div>
                <div class="filters">
                    <label class="search"><span>⌕</span><input id="searchInput" type="search" placeholder="Buscar por nombre, DNI o código de cita..." aria-label="Buscar citas"></label>
                    <select id="examFilter" aria-label="Filtrar por examen"><option value="">Todos los exámenes</option><option>Resonancia magnética</option><option>Tomografía</option><option>Ecografía</option><option>Rayos X</option><option>Laboratorio</option></select>
                    <select id="statusFilter" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="Por llegar">Por llegar</option><option value="Ingresó">Ingresó</option><option value="Con retraso">Con retraso</option></select>
                    <button class="filter-btn" id="clearFilters">↻ Limpiar</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>HORA</th><th>PACIENTE</th><th>EXAMEN</th><th>DESTINO</th><th>ESTADO</th><th></th></tr></thead>
                        <tbody id="appointmentsBody"></tbody>
                    </table>
                    <div class="empty" id="emptyState" hidden><strong>No encontramos citas</strong><p>Prueba con otro nombre, DNI o filtro.</p></div>
                </div>
                <div class="table-footer"><p>Mostrando <strong id="resultCount">6</strong> de 18 citas</p><div><button disabled>‹</button><button class="active">1</button><button>2</button><button>3</button><button>›</button></div></div>
            </section>
            <footer><p>Hospital La Merced Paita · Control de acceso</p><p><span></span> Conexión segura · Datos actualizados en tiempo real</p></footer>
        </div>
    </main>
</div>

<div class="modal-backdrop" id="detailModal" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button class="modal-close" data-close aria-label="Cerrar">×</button>
        <div class="modal-status"><span>✓</span> CITA CONFIRMADA</div>
        <h2 id="modalTitle">Detalle de la cita</h2>
        <div class="patient-card"><div class="avatar large" id="modalInitials">AR</div><div><strong id="modalPatient">Ana Ruiz Mendoza</strong><p id="modalDni">DNI 45872931</p></div></div>
        <div class="detail-grid"><div><small>HORA</small><strong id="modalTime">09:40</strong></div><div><small>CÓDIGO</small><strong id="modalCode">CT-02841</strong></div><div><small>EXAMEN</small><strong id="modalExam">Tomografía</strong></div><div><small>DESTINO</small><strong id="modalDest">Piso 1 · Sala 108</strong></div></div>
        <div class="instruction"><span>→</span><div><strong>Indicación para el paciente</strong><p>Dirigirse a Admisión de Diagnóstico por Imágenes antes de ingresar a la sala.</p></div></div>
        <button class="confirm-btn" id="confirmEntry">Confirmar ingreso</button>
        <button class="secondary-btn" data-close>Cerrar detalle</button>
    </section>
</div>

<div class="toast" id="toast"><span>✓</span><div><strong>Ingreso registrado</strong><p>El paciente ya puede acceder.</p></div></div>
<script>
window.appointments = [
 {time:'08:30',name:'María López Torres',dni:'70452918',initials:'ML',exam:'Laboratorio',detail:'Análisis de sangre',dest:'Piso 2 · Lab. 204',status:'Ingresó',code:'LAB-1042'},
 {time:'09:15',name:'Jorge Salazar Peña',dni:'43982617',initials:'JS',exam:'Resonancia magnética',detail:'RM de rodilla derecha',dest:'Sótano · Sala RM-02',status:'Con retraso',code:'RM-0837'},
 {time:'09:40',name:'Ana Ruiz Mendoza',dni:'45872931',initials:'AR',exam:'Tomografía',detail:'TAC de tórax',dest:'Piso 1 · Sala 108',status:'Por llegar',code:'CT-02841'},
 {time:'10:00',name:'Luis Paredes Silva',dni:'61230984',initials:'LP',exam:'Ecografía',detail:'Ecografía abdominal',dest:'Piso 1 · Consult. 112',status:'Por llegar',code:'ECO-3348'},
 {time:'10:30',name:'Carmen Díaz Rojas',dni:'38741925',initials:'CD',exam:'Rayos X',detail:'Radiografía de tórax',dest:'Piso 1 · Sala RX-03',status:'Por llegar',code:'RX-2195'},
 {time:'11:15',name:'Pedro Huamán Quispe',dni:'50921763',initials:'PH',exam:'Resonancia magnética',detail:'RM cerebral',dest:'Sótano · Sala RM-01',status:'Por llegar',code:'RM-0921'}
];
</script>
</body>
</html>
