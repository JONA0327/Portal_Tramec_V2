<!doctype html>
<html lang="es">
<head>
    <title>Portal de Existencias TRAMEC</title>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    {{-- Mismos estilos base que v1 (fonts/bootstrap/auth.css), pero el panel decorativo y
         los colores se repintan acá encima con la paleta del resto del portal (--secondary
         navy / --primary azul, del dashboard) en vez de los de auth.css, para que login y
         dashboard se vean como el mismo producto. --}}
    <link rel="stylesheet" type="text/css" href="{{ asset('dash-assets/css/fonts.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('dash-assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('dash-assets/css/auth.css') }}">

    <style>
        :root {
            --secondary: #272e48;
            --primary: #11a1fd;
        }

        body { background: #f4f6fb; }

        .ad-auth-wrapper {
            background: var(--secondary);
        }
        {{-- auth.css pinta un fondo (auth-bg.png) detrás de toda la página -- se saca:
             el panel decorativo de acá adentro (con los engranes) ya cumple ese rol,
             y a color plano se ve más limpio/moderno que la imagen de plantilla. --}}
        .ad-auth-wrapper:before { display: none; }

        .ad-auth-box {
            max-width: 940px;
            width: 100%;
            border-radius: 24px;
            box-shadow: 0 30px 60px -20px rgba(10, 14, 30, .35);
            overflow: hidden;
            {{-- Se arma con flex propio (no la grilla de Bootstrap): la fila de acá
                 abajo usaba clases de Bootstrap 5 ("g-0") que esta versión (4.1.3) no
                 trae, y los márgenes negativos de ".row" rompían el centrado de toda
                 la tarjeta. Más simple y predecible sin la grilla. --}}
            display: flex !important;
            align-items: stretch;
        }
        .ad-auth-box > .ad-auth-form-col { flex: 1 1 0; min-width: 0; }

        {{-- Panel decorativo: mismo navy que el sidebar del dashboard, con engranes de
             fondo (ver .ad-gear-ring más abajo) en vez de la imagen estática de v1 --
             mismo espíritu (columna decorativa a la izquierda), más acorde al rubro
             (transmisiones/engranes) y a la paleta del resto del portal. Oculto en
             pantallas angostas: ahí el <a class="ad-auth-logo"> de cada view hace de
             marca (ver reglas responsive). --}}
        .ad-auth-decor {
            position: relative;
            flex: 0 0 42%;
            max-width: 42%;
            background: var(--secondary);
            color: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 40px;
            overflow: hidden;
        }
        .ad-auth-decor-gears {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .ad-gear-ring {
            position: absolute;
            opacity: .5;
            animation: ad-gear-spin 60s linear infinite;
        }
        .ad-gear-ring.reverse { animation-direction: reverse; }
        @keyframes ad-gear-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .ad-auth-decor-content { position: relative; z-index: 1; }
        .ad-auth-decor-brand { margin-bottom: 28px; }
        .ad-auth-decor-logo {
            max-width: 220px;
            width: 100%;
            height: auto;
            display: block;
        }
        .ad-auth-decor-content h3 {
            color: var(--white);
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 14px;
            line-height: 1.3;
        }
        .ad-auth-decor-content p {
            color: rgba(255, 255, 255, .65);
            font-size: 15px;
            line-height: 1.6;
            max-width: 320px;
        }
        {{-- Delta es una marca que TRAMEC representa/distribuye -- se muestra como marca
             secundaria, separada de la principal por una línea, no como si fuera el
             logo del portal. --}}
        .ad-auth-decor-partner {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, .14);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .ad-auth-decor-partner .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: rgba(255, 255, 255, .45);
            max-width: 90px;
            line-height: 1.4;
        }
        .ad-auth-decor-partner-logo {
            max-width: 90px;
            height: auto;
        }

        .ad-auth-content { padding: 56px 56px 48px; }

        {{-- El "logo" de cada view (ver login.blade.php, etc.) queda como respaldo para
             cuando el panel decorativo está oculto (pantallas angostas) -- ver media
             query más abajo. En pantallas anchas ya se ve arriba, en el panel. --}}
        .ad-auth-logo h4 { color: var(--secondary); font-weight: 700; }

        .ad-auth-content h2 .primary { color: var(--primary); }
        .ad-auth-content h2 { font-size: 30px; }
        .ad-auth-content p { color: #6b7280; }

        .ad-auth-feilds input:focus { border-color: var(--primary); }
        .ad-auth-feilds input:focus ~ .ad-auth-icon svg { stroke: var(--primary); }
        .ad-auth-icon svg { stroke: #9aa3b5; }
        .ad-checkbox input:checked ~ span:before { background-color: var(--primary); }
        .ad-checkbox input:checked ~ span { color: var(--primary); }
        .ad-forgot-link, .forgot-pws-btn, .ad-register-text a { color: var(--primary); }

        .ad-auth-btn input[type="submit"] {
            background: var(--primary);
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: .01em;
        }
        .ad-auth-btn input[type="submit"]:hover { background: var(--secondary); }

        {{-- Restaurar Contraseña usa un <button class="btn btn-primary"> genérico (v1
             ya lo tenía así, un estilo más simple que login/register) -- seguía pintado
             con el azul por defecto de Bootstrap en vez del azul de marca. Se corrige
             el color acá nomás, sin tocar esa estructura más simple. --}}
        .ad-auth-content .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 10px;
            font-weight: 600;
        }
        .ad-auth-content .btn-primary:hover,
        .ad-auth-content .btn-primary:focus {
            background: var(--secondary);
            border-color: var(--secondary);
        }
        .ad-auth-content a { color: var(--primary); }

        @media (max-width: 991px) {
            .ad-auth-decor { display: none; }
            .ad-auth-logo { display: inline-block; }
        }
        @media (min-width: 992px) {
            {{-- Con el panel decorativo mostrando la marca, el logo repetido adentro
                 del formulario ya no hace falta -- solo queda de respaldo en móvil. --}}
            .ad-auth-logo { display: none; }
        }
        @media (max-width: 576px) {
            .ad-auth-content { padding: 8px 20px 22px; }
        }
    </style>
</head>
<body>
    <div class="ad-auth-wrapper">
        <div class="ad-auth-box">

            <div class="ad-auth-decor">
                <div class="ad-auth-decor-gears" aria-hidden="true">
                    <svg class="ad-gear-ring" style="width:260px;height:260px;top:-70px;right:-80px;" viewBox="0 0 200 200">
                        <circle cx="100" cy="100" r="72" fill="none" stroke="#ffffff" stroke-width="24" stroke-dasharray="16 13"/>
                        <circle cx="100" cy="100" r="40" fill="none" stroke="#ffffff" stroke-width="2"/>
                    </svg>
                    <svg class="ad-gear-ring reverse" style="width:180px;height:180px;bottom:-50px;left:-60px;" viewBox="0 0 200 200">
                        <circle cx="100" cy="100" r="70" fill="none" stroke="#11a1fd" stroke-width="26" stroke-dasharray="14 12"/>
                        <circle cx="100" cy="100" r="36" fill="none" stroke="#11a1fd" stroke-width="2"/>
                    </svg>
                    <svg class="ad-gear-ring" style="width:120px;height:120px;bottom:60px;right:10px;" viewBox="0 0 200 200">
                        <circle cx="100" cy="100" r="68" fill="none" stroke="#ffffff" stroke-width="22" stroke-dasharray="12 10"/>
                    </svg>
                </div>

                <div class="ad-auth-decor-content">
                    <div class="ad-auth-decor-brand">
                        <img src="{{ asset('Logo-Tramec-MX-removebg-preview.png') }}" alt="TRAMEC" class="ad-auth-decor-logo">
                    </div>
                    <h3>Existencias y precios, siempre al día.</h3>
                    <p>Consultá el catálogo, los precios por canal y las existencias por almacén de TRAMEC y OMEC en un solo lugar, sincronizado en tiempo real con el ERP.</p>

                    <div class="ad-auth-decor-partner">
                        <span class="label">Representamos productos de</span>
                        <img src="{{ asset('delta-electronics-vector-logo-removebg-preview.png') }}" alt="Delta Electronics" class="ad-auth-decor-partner-logo">
                    </div>
                </div>
            </div>

            <div class="ad-auth-form-col">
                <div class="ad-auth-content">
                    {{ $slot }}
                </div>
            </div>

        </div>
    </div>
</body>
</html>
