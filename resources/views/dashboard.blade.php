{{-- Dashboard de v2, mismo diseño visual que v1 (portal-tramec/resources/views/client/home.blade.php
     dentro de layouts/client.blade.php), ahora sobre x-layouts.dashboard. --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Panel Principal</div>

                    <div class="card-body">
                        <x-auth-session-status class="mb-4" :status="session('status')" />

                        Consulte las secciones disponibles en el menú de la izquierda:<br><br>

                        <b>Inicio:</b> Podrá encontrar algunas tablas y nomenclaturas de ayuda de nuestros productos.<br>
                        <b>Productos:</b> Consulte por modelo o características y encontrará su costo y el precio de lista.<br>
                        <b>Manuales/Catálogos:</b> Permite visualizar la documentación de los productos como manuales, catálogos, dimensiones, etc.<br>
                        <b>Existencias:</b> Podrá encontrar las piezas disponibles en cada almacén activo.<br><br>
                        Para soporte o fallas en el portal envíe un correo a info@tramec.mx
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
