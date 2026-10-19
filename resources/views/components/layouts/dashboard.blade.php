<!doctype html>
<html lang="es">
<head>
    <title>Portal de Existencias TRAMEC</title>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    {{-- Mismo tema que v1 (portal-tramec/resources/views/layouts/client.blade.php),
         copiado con los mismos assets de public/dash-assets. v1 traía además datatables,
         ckeditor y apexcharts en este layout: se omiten aquí porque ninguna página de v2
         los usa todavía; si una vista futura los necesita, se agregan vía @push/@stack. --}}
    <link rel="stylesheet" type="text/css" href="{{ asset('dash-assets/css/fonts.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('dash-assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('dash-assets/css/font-awesome.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('dash-assets/css/icofont.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('dash-assets/css/style.css') }}">

    {{-- style.css espera un <img class="sp_logo">/<img class="sp_mini_logo"> para achicar el logo
         al colapsar (body.mini-sidebar), pero este layout usa un ícono/insignia + texto: sin
         esto, el texto completo "PORTAL TRAMEC" se recorta feo contra el riel de 80px. --}}
    <style>
        {{-- Logo real de TRAMEC expandido -- es horizontal (no una marca cuadrada), así
             que no entra en el riel colapsado de 80px: se oculta ahí nomás (queda el
             botón de expandir solo) hasta que haya una versión cuadrada del logo. --}}
        .logo-wrapper { transition: width .3s, padding .3s; }
        .logo-wrapper .admin-logo { display: flex; align-items: center; gap: 12px; text-align: left; overflow: hidden; }
        .logo-wrapper .brand-logo-full {
            max-width: 168px;
            width: 100%;
            height: auto;
        }
        body.mini-sidebar .logo-wrapper { width: 80px; padding: 10px 0; justify-content: center; }
        body.mini-sidebar .logo-wrapper .admin-logo { gap: 0; }
        body.mini-sidebar .logo-wrapper .logo-full-text { display: none; }

        {{-- El tema original no trae fondo oscuro para el menú móvil: "mini-sidebar" en pantallas
             angostas (<1199px) abre el sidebar ENCIMA del contenido (a diferencia de escritorio,
             donde esa misma clase lo encoge a solo iconos), y sin backdrop se veía el contenido
             asomando al lado, encimado y sin forma clara de cerrarlo. --}}
        .sidebar-backdrop { display: none; }
        @media (max-width: 1199px) {
            .sidebar-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(17, 24, 39, .55);
                z-index: 8; /* debajo del sidebar (9) y el header (99), encima del contenido */
                /* El sidebar tarda 0.3s en deslizarse (ver .sidebar-wrapper en style.css) --
                   sin este mismo fade, el fondo aparecía de golpe mientras el menú todavía
                   estaba a medio abrir, un instante que se veía roto/a medio cargar. */
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease, visibility 0.3s ease;
            }
            body.mini-sidebar .sidebar-backdrop {
                opacity: 1;
                visibility: visible;
            }
            /* Botón hamburguesa un poco más chico y liviano en móvil. */
            .toggle-btn span { width: 20px; }
            .toggle-btn span:before { width: 14px; top: -6px; }
            .toggle-btn span:after { width: 17px; bottom: -6px; }

            {{-- El sidebar en móvil arranca 80px más abajo (debajo del header, ver
                 "top: 80px" en style.css) pero seguía midiendo height:100vh -- se pasaba
                 80px del viewport real y la tarjeta de perfil (al fondo) quedaba fuera de
                 vista sin hacer scroll. --}}
            {{-- height:100vh no era lo que ganaba -- era "min-height:100%" (100% del
                 viewport para un position:fixed = altura completa), que le gana a
                 cualquier height más chico que se intente poner. Hay que bajar los dos
                 a la vez para que el sidebar no se pase 80px del viewport real. --}}
            .sidebar-wrapper { height: auto !important; min-height: 0 !important; bottom: 0; }
            {{-- El tema original le agrega 80px de padding abajo del menú en móvil (pensado
                 para cuando no había nada más debajo) -- con la tarjeta de perfil nueva esto
                 la empujaba fuera de la vista, de nuevo obligando a scrollear para verla. --}}
            .side-menu-wrap ul.main-menu { padding-bottom: 8px; }
        }

        {{-- Pulido del sidebar: mismo color de marca (--secondary de fondo, --primary de acento),
             pero con mejor spacing, un indicador de sección activa que sí funciona (el CSS del
             tema original espera la clase "active" en el <li>, pero acá va en el <a> — con estas
             reglas propias, apuntadas directo a "a.active", no hace falta tocar el markup de cada
             link ni pelear con el CSS viejo) y transiciones suaves en el hover. --}}
        .side-menu-wrap ul.main-menu { padding-top: 8px; }
        .menu-section-label {
            display: block;
            margin: 8px 22px 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .38);
        }
        .side-menu-wrap ul.main-menu li { margin: 3px 12px; width: calc(100% - 24px); }
        .side-menu-wrap ul.main-menu li > a {
            display: flex;
            align-items: center;
            border-radius: 10px;
            padding: 8px 14px;
            color: rgba(255, 255, 255, .72);
            transition: background-color .18s ease, color .18s ease;
        }
        .side-menu-wrap ul.main-menu li > a:before { display: none; }
        {{-- Insignia cuadrada redondeada alrededor de cada ícono, como en el diseño de
             referencia -- en vez de un ícono suelto, así resalta más y da más jerarquía
             visual sin cambiar la paleta (fondo sutil translúcido, azul de marca si
             está activo). --}}
        .side-menu-wrap ul.main-menu li > a .icon-menu {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            margin-right: 14px;
            border-radius: 8px;
            background: rgba(255, 255, 255, .06);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color .18s ease;
        }
        .side-menu-wrap ul.main-menu li > a .icon-menu svg { stroke: rgba(255, 255, 255, .72); transition: stroke .18s ease; }
        .side-menu-wrap ul.main-menu li > a .menu-chevron {
            margin-left: auto;
            flex-shrink: 0;
            opacity: 0;
            stroke: rgba(255, 255, 255, .72);
            transition: opacity .18s ease;
        }
        .side-menu-wrap ul.main-menu li > a:hover {
            background: rgba(255, 255, 255, .06);
            color: var(--white);
        }
        .side-menu-wrap ul.main-menu li > a:hover .icon-menu { background: rgba(255, 255, 255, .1); }
        .side-menu-wrap ul.main-menu li > a:hover .icon-menu svg { stroke: var(--white); }
        .side-menu-wrap ul.main-menu li > a:hover .menu-chevron { opacity: .5; }
        .side-menu-wrap ul.main-menu li > a.active {
            background: rgba(17, 161, 253, .16);
            color: var(--white);
            font-weight: 600;
        }
        .side-menu-wrap ul.main-menu li > a.active .icon-menu { background: var(--primary); }
        .side-menu-wrap ul.main-menu li > a.active .icon-menu svg { stroke: var(--white); }
        .side-menu-wrap ul.main-menu li > a.active .menu-chevron { opacity: 1; stroke: var(--primary); }

        {{-- Colapsado (solo iconos): sin esto el ícono queda pegado a la izquierda del
             riel angosto (el padding original asume que al lado hay texto) en vez de
             centrado en el ancho de 80px. --}}
        @media (min-width: 1199px) {
            body.mini-sidebar:not(.sidebar-hover) .side-menu-wrap ul.main-menu li > a {
                justify-content: center;
                align-items: center;
                padding: 0;
                width: 44px;
                height: 44px;
                margin: 0 auto;
                border-radius: 50%;
            }
            {{-- El tema le pone al ícono una caja fija de 35px (para el modo con texto),
                 pero el SVG mide 20px y queda pegado a la izquierda de esa caja en vez de
                 centrado -- por eso se veía corrido a la izquierda del círculo. La
                 insignia cuadrada (fondo, radio) tampoco tiene sentido acá -- el link
                 entero ya ES el círculo, iría una forma anidada dentro de otra. --}}
            body.mini-sidebar:not(.sidebar-hover) .side-menu-wrap ul.main-menu li > a .icon-menu {
                margin-right: 0;
                width: auto;
                height: auto;
                background: transparent !important;
                display: flex;
                justify-content: center;
            }
            body.mini-sidebar:not(.sidebar-hover) .menu-section-label,
            body.mini-sidebar:not(.sidebar-hover) .menu-chevron {
                display: none;
            }
            {{-- El acento activo era una barra a la izquierda (pensada para un link
                 ancho): en un círculo se ve como una muesca rara, así que colapsado
                 el estado activo se marca solo con el fondo/color, sin la barra. --}}
            body.mini-sidebar:not(.sidebar-hover) .side-menu-wrap ul.main-menu li > a.active {
                box-shadow: none;
            }

            {{-- El botón de colapsar/expandir vive DENTRO del sidebar (acá abajo), no en
                 el header -- en escritorio el de siempre (header) se oculta siempre,
                 haya o no colapsado. Sigue existiendo para MÓVIL, donde el sidebar
                 arranca oculto del todo y no hay otra forma de abrirlo. --}}
            .toggle-btn-header { display: none; }

            {{-- Botón para EXPANDIR, redondo y del mismo color que los íconos del menú --
                 solo visible colapsado. --}}
            .toggle-btn-rail { display: none; }
            body.mini-sidebar:not(.sidebar-hover) .toggle-btn-rail {
                {{-- position:relative + el svg centrado con position:absolute, NO
                     flexbox: con display:flex el <svg> colapsaba a width:0 pese a
                     tener width:16px explícito (un bug de Chromium al resolver el
                     tamaño de un <svg> como flex-item; con position no aplica). --}}
                display: block;
                position: relative;
                width: 32px;
                height: 32px;
                margin: 8px auto 4px;
                border-radius: 50%;
                border: 1px solid rgba(255, 255, 255, .24);
                background: transparent;
                color: rgba(255, 255, 255, .72);
                transition: background-color .18s ease, color .18s ease;
            }
            body.mini-sidebar:not(.sidebar-hover) .toggle-btn-rail:hover {
                background: rgba(255, 255, 255, .08);
                color: var(--white);
            }
            body.mini-sidebar:not(.sidebar-hover) .toggle-btn-rail svg {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 16px;
                height: 16px;
            }

            {{-- Botón para COLAPSAR, mismo estilo redondo -- vive en el logo (que ya es
                 position:relative, ver style.css .logo-wrapper), solo visible expandido. --}}
            .toggle-btn-expanded { display: none; }
            body:not(.mini-sidebar) .toggle-btn-expanded {
                display: block;
                position: absolute;
                top: 50%;
                right: 16px;
                transform: translateY(-50%);
                width: 32px;
                height: 32px;
                border-radius: 50%;
                border: 1px solid rgba(255, 255, 255, .24);
                background: transparent;
                color: rgba(255, 255, 255, .72);
                transition: background-color .18s ease, color .18s ease;
            }
            body:not(.mini-sidebar) .toggle-btn-expanded:hover {
                background: rgba(255, 255, 255, .08);
                color: var(--white);
            }
            body:not(.mini-sidebar) .toggle-btn-expanded svg {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 16px;
                height: 16px;
            }
        }

        {{-- Título de la sección actual en el header (ver $currentSectionTitle más abajo):
             reemplaza al ".page-title-wrapper" que quedaba siempre vacío arriba del
             contenido. El header es blanco (no navy -- ver .header-right en style.css,
             así se ve el tema siempre), por eso el texto va oscuro, no blanco. --}}
        .header-section-title {
            margin: 0 0 0 18px;
            padding-left: 18px;
            border-left: 1px solid rgba(0, 0, 0, .1);
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary);
            white-space: nowrap;
        }
        @media (max-width: 575px) {
            .header-section-title { display: none; }
        }
        @media (min-width: 1199px) {
            {{-- Sin el botón de colapsar al lado (se movió al sidebar), el margen/línea
                 de separación ya no tiene sentido -- solo un poco de aire a la izquierda. --}}
            .header-section-title { margin-left: 0; padding-left: 24px; border-left: none; }
        }

        {{-- Tarjeta de usuario al fondo del sidebar (estilo de la referencia), en vez de
             solo un avatar en el header -- mismos colores de marca. .sidebar-wrapper pasa
             a ser flex-column y .side-menu-wrap crece (flex:1) para empujarla siempre
             al fondo, sin importar cuántos items tenga el menú. --}}
        .sidebar-wrapper { display: flex; flex-direction: column; }
        .side-menu-wrap { flex: 1 1 auto; }
        .sidebar-profile-card {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 8px 12px 16px;
            padding: 10px;
            border-top: 1px solid rgba(255, 255, 255, .1);
            padding-top: 16px;
        }
        .sidebar-profile-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
            color: var(--white);
        }
        .sidebar-profile-link:hover { color: var(--white); opacity: .85; }
        .sidebar-profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: var(--white);
            font-size: 13px;
            font-weight: 700;
            overflow: hidden;
        }
        .sidebar-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sidebar-profile-info { min-width: 0; overflow: hidden; }
        .sidebar-profile-name {
            display: block;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-profile-email {
            display: block;
            font-size: 11px;
            color: rgba(255, 255, 255, .5);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-profile-logout {
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .5);
            transition: background-color .18s ease, color .18s ease;
        }
        .sidebar-profile-logout:hover { background: rgba(255, 255, 255, .08); color: var(--white); }
        @media (min-width: 1199px) {
            body.mini-sidebar:not(.sidebar-hover) .sidebar-profile-card {
                justify-content: center;
                padding: 16px 0 0;
                margin: 8px 0 16px;
            }
            {{-- El tema le pone padding-left:30px a CUALQUIER <a> del sidebar colapsado
                 (pensado para los links del menú, ver "a" genérico en style.css) -- acá
                 empujaba el avatar fuera de centro, se anula. --}}
            body.mini-sidebar:not(.sidebar-hover) .sidebar-profile-link { flex: 0 0 auto; padding-left: 0 !important; }
            body.mini-sidebar:not(.sidebar-hover) .sidebar-profile-info,
            body.mini-sidebar:not(.sidebar-hover) .sidebar-profile-logout {
                display: none;
            }
        }
    </style>

</head>
<body>
    <script>
        {{-- Restaura el colapsado guardado (ver observer al final del body) solo en escritorio:
             en móvil "mini-sidebar" abre el menú como overlay, así que arrancar con esa clase
             puesta taparía la pantalla apenas se carga la página. --}}
        try {
            if (window.innerWidth >= 1199 && localStorage.getItem('tramec_sidebar_collapsed') === '1') {
                document.body.classList.add('mini-sidebar');
            }
        } catch (e) {}
    </script>

    <div class="loader">
        <div class="spinner">
            <img src="{{ asset('dash-assets/images/loader.gif') }}" alt="" />
        </div>
    </div>

    @php
        // El header traía un ".page-title-wrapper" aparte para esto, pero ningún view
        // pasaba $title -- quedaba siempre vacío, ocupando espacio de más arriba del
        // contenido. Se calcula acá (por ruta, sin tocar cada view) y se muestra
        // directo en el header, al lado del botón de colapsar.
        $currentSectionTitle = match (true) {
            request()->routeIs('dashboard') => 'Panel Principal',
            request()->routeIs('products') || request()->routeIs('products.*') => 'Productos',
            request()->routeIs('warehouses') || request()->routeIs('warehouses.*') => 'Almacenes',
            request()->routeIs('existencias') => 'Existencias',
            request()->routeIs('profile.*') => 'Mi Perfil',
            default => '',
        };
    @endphp

    <div class="page-wrapper">
        <header class="header-wrapper main-header">
            <div class="header-inner-wrapper">
                <div class="header-right">
                    <div class="header-left">
                        <div class="header-links">
                            <a href="javascript:void(0);" class="toggle-btn toggle-btn-header">
                                <span></span>
                            </a>
                        </div>
                        @if($currentSectionTitle)
                            <h1 class="header-section-title">{{ $currentSectionTitle }}</h1>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <aside class="sidebar-wrapper">
            <div class="logo-wrapper">
                <a href="{{ route('dashboard') }}" class="admin-logo">
                    <img src="{{ asset('Logo-Tramec-MX-removebg-preview.png') }}" alt="TRAMEC" class="brand-logo-full logo-full-text">
                </a>

                {{-- Para colapsar: solo visible expandido (ver .toggle-btn-expanded más
                     arriba). El de expandir (.toggle-btn-rail) vive más abajo, debajo del
                     logo, porque acá ya no hay lugar cuando el sidebar está angosto. --}}
                <a href="javascript:void(0);" class="toggle-btn toggle-btn-expanded" aria-label="Colapsar menú">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </a>
            </div>

            {{-- Para volver a expandir sin ir hasta el header: solo visible con el
                 sidebar colapsado (ver .toggle-btn-rail más arriba). --}}
            <a href="javascript:void(0);" class="toggle-btn toggle-btn-rail" aria-label="Expandir menú">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <div class="side-menu-wrap">
                {{-- v1 arma este menú según el nivel/permisos del usuario (Manuales, Almacenes,
                     Existencias, Administración...). Esas secciones todavía no existen en v2,
                     así que se agregan aquí mismo cuando se porten. --}}
                <span class="menu-section-label">Menú</span>
                <ul class="main-menu">
                    @if(auth()->user()->puedeVer('inicio'))
                    <li>
                        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <span class="icon-menu feather-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-home">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                            </span>
                            <span class="menu-text">Inicio</span>
                            <svg class="menu-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->puedeVer('productos'))
                    <li>
                        <a href="{{ route('products') }}" class="{{ request()->routeIs('products') || request()->routeIs('products.*') ? 'active' : '' }}">
                            <span class="icon-menu feather-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-package">
                                    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                </svg>
                            </span>
                            <span class="menu-text">Productos</span>
                            <svg class="menu-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->puedeVer('almacenes'))
                    <li>
                        <a href="{{ route('warehouses') }}" class="{{ request()->routeIs('warehouses') || request()->routeIs('warehouses.*') ? 'active' : '' }}">
                            <span class="icon-menu feather-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-archive">
                                    <polyline points="21 8 21 21 3 21 3 8"></polyline>
                                    <rect x="1" y="3" width="22" height="5"></rect>
                                    <line x1="10" y1="12" x2="14" y2="12"></line>
                                </svg>
                            </span>
                            <span class="menu-text">Almacenes</span>
                            <svg class="menu-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->puedeVer('existencias'))
                    <li>
                        <a href="{{ route('existencias') }}" class="{{ request()->routeIs('existencias') || request()->routeIs('existencias.*') ? 'active' : '' }}">
                            <span class="icon-menu feather-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-bar-chart-2">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                            </span>
                            <span class="menu-text">Existencias</span>
                            <svg class="menu-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->isAdmin())
                    <li>
                        <a href="{{ route('price-rules.index') }}" class="{{ request()->routeIs('price-rules.*') ? 'active' : '' }}">
                            <span class="icon-menu feather-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-percent">
                                    <line x1="19" y1="5" x2="5" y2="19"></line>
                                    <circle cx="6.5" cy="6.5" r="2.5"></circle>
                                    <circle cx="17.5" cy="17.5" r="2.5"></circle>
                                </svg>
                            </span>
                            <span class="menu-text">Descuentos y aumentos</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <span class="icon-menu feather-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="feather feather-users">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </span>
                            <span class="menu-text">Usuarios</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>

            {{-- Tarjeta de usuario al fondo del sidebar (reemplaza al dropdown que vivía
                 en el header, ver .sidebar-profile-card más arriba). --}}
            <div class="sidebar-profile-card">
                <a href="{{ route('profile.edit') }}" class="sidebar-profile-link">
                    {{-- La foto genérica de plantilla (dash-assets/images/user.jpg) se veía
                         desalineada en el círculo chico -- si el usuario no subió una foto
                         propia, mejor mostrar sus iniciales que una foto de stock que no es
                         de nadie. --}}
                    <span class="sidebar-profile-avatar">
                        @if(Auth::user()->image)
                            <img src="{{ asset(Auth::user()->image) }}" alt="">
                        @else
                            {{ mb_strtoupper(mb_substr(Auth::user()->first_name, 0, 1).mb_substr(Auth::user()->last_name, 0, 1)) }}
                        @endif
                    </span>
                    <span class="sidebar-profile-info">
                        <span class="sidebar-profile-name">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</span>
                        <span class="sidebar-profile-email">{{ Auth::user()->email }}</span>
                    </span>
                </a>
                <a href="javascript:void(0);" class="sidebar-profile-logout" title="Cerrar sesión"
                    onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </div>
        </aside>

        <div class="sidebar-backdrop"></div>

        <div class="page-wrapper">
            <div class="main-content">
                {{ $slot }}

                <div class="ad-footer-btm">
                    <p>Copyright {{ date('Y') }} © Todos los Derechos Reservados.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('dash-assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dash-assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('dash-assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('dash-assets/js/custom.js') }}"></script>
    <script>
        {{-- custom.js (del template) es quien de verdad alterna la clase "mini-sidebar" al
             hacer clic en .toggle-btn; acá solo observamos ese cambio para recordarlo en
             localStorage (nada más en escritorio, donde esa clase sí significa "colapsado") y
             para cerrar el menú overlay en móvil al tocar fuera o al elegir una sección. --}}
        try {
            new MutationObserver(function () {
                if (window.innerWidth >= 1199) {
                    localStorage.setItem(
                        'tramec_sidebar_collapsed',
                        document.body.classList.contains('mini-sidebar') ? '1' : '0'
                    );
                }
            }).observe(document.body, { attributes: true, attributeFilter: ['class'] });

            var closeMobileMenu = function () {
                if (window.innerWidth < 1199) {
                    document.body.classList.remove('mini-sidebar');
                }
            };
            document.querySelector('.sidebar-backdrop').addEventListener('click', closeMobileMenu);
            document.querySelectorAll('.sidebar-wrapper .main-menu a').forEach(function (link) {
                link.addEventListener('click', closeMobileMenu);
            });
        } catch (e) {}
    </script>
</body>
</html>
