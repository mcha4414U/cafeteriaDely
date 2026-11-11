import { Carrito, centavos, moneda } from './carrito';
import { editorReceta } from './receta-pos';
const datos = document.getElementById('datos-pos');
if (datos) {
    const carrito = new Carrito(JSON.parse(datos.textContent));
    const lineas = document.getElementById('lineas-carrito');
    const aviso = document.getElementById('aviso-pos');
    const cobrar = document.getElementById('cobrar-pos');
    const limpiar = document.getElementById('limpiar-carrito');
    const formulario = document.getElementById('cobro-pos');
    const descuento = document.getElementById('descuento-pos');
    const recibido = document.getElementById('recibido-pos');
    const metodo = document.getElementById('metodo-pos');
    let enviando = false, pendiente = null;
    const abrirReceta = editorReceta(carrito, dibujar, () => !enviando && !pendiente);
    const almacenamiento = 'dely-cobro-pendiente-' + formulario.dataset.usuario;
    function bloquear(valor) {
        document.querySelectorAll('[data-producto], #lineas-carrito button, #cobro-pos input:not([type="hidden"]), #cobro-pos select, #cobro-pos textarea').forEach(e => e.disabled = valor);
        limpiar.disabled = valor || carrito.lineas.size === 0;
        cobrar.disabled = enviando || carrito.lineas.size === 0;
    }
    function importes() {
        const bruto = carrito.total();
        let rebaja = 0n;
        try { rebaja = centavos(descuento.value || '0'); } catch { rebaja = bruto + 1n; }
        const total = rebaja <= bruto ? bruto - rebaja : 0n;
        document.getElementById('subtotal-pos').textContent = moneda(bruto);
        ['total-pos','cobrar-total'].forEach(id => document.getElementById(id).textContent = moneda(total));
        recibido.required = metodo.selectedOptions[0]?.textContent.trim().toLowerCase() === 'efectivo';
        recibido.disabled = !recibido.required || !!pendiente || enviando;
        try { const pago = centavos(recibido.value || '0'); document.getElementById('cambio-pos').textContent = recibido.required ? (pago >= total ? 'Cambio: '+moneda(pago-total) : 'Falta: '+moneda(total-pago)) : ''; } catch { document.getElementById('cambio-pos').textContent = ''; }
        descuento.setCustomValidity(rebaja > bruto ? 'El descuento supera el subtotal.' : '');
    }
    function dibujar() {
        lineas.replaceChildren(); aviso.textContent = '';
        for (const [id, cantidad] of carrito.lineas) {
            const producto = carrito.productos.get(id);
            const fila = document.createElement('div'); fila.className = 'pos-linea';
            if (producto.imagen) { const imagen = document.createElement('img'); imagen.src = producto.imagen; imagen.alt = ''; fila.append(imagen); }
            const detalle = document.createElement('div'); detalle.className = 'flex-grow-1';
            const nombre = document.createElement('strong'); nombre.textContent = producto.nombre;
            const precio = document.createElement('div'); precio.className = 'small text-secondary'; precio.textContent = moneda(centavos(producto.precio_venta)) + ' por unidad';
            const controles = document.createElement('div'); controles.className = 'd-flex align-items-center gap-2 mt-2';
            function boton(texto, etiqueta, accion) {
                const elemento = document.createElement('button'); elemento.type = 'button'; elemento.className = 'btn btn-sm btn-outline-secondary'; elemento.textContent = texto; elemento.setAttribute('aria-label', etiqueta + ' ' + producto.nombre);
                elemento.addEventListener('click', () => { accion(); dibujar(); });
                return elemento;
            }
            const numero = document.createElement('span'); numero.textContent = cantidad;
            controles.append(boton('−', 'Disminuir', () => carrito.disminuir(id)), numero, boton('+', 'Aumentar', () => carrito.agregar(id)), boton('Quitar', 'Quitar', () => carrito.quitar(id)));
            if (carrito.tieneReceta(id)) {
                controles.classList.add('flex-wrap');
                controles.append(boton('Receta', 'Receta de', () => abrirReceta(id)));
                const personalizadas = carrito.indicaciones.get(id)?.size ?? 0;
                if (personalizadas) { const etiqueta = document.createElement('small'); etiqueta.className = 'text-primary'; etiqueta.textContent = personalizadas + ' personalizada(s)'; detalle.append(etiqueta); }
            }
            detalle.append(nombre, precio, controles);
            const importe = document.createElement('span'); importe.className = 'text-nowrap fw-semibold'; importe.textContent = moneda(centavos(producto.precio_venta) * BigInt(cantidad));
            fila.append(detalle, importe); lineas.append(fila);
        }
        importes();
        document.getElementById('carrito-vacio').hidden = carrito.lineas.size > 0;
        cobrar.disabled = limpiar.disabled = carrito.lineas.size === 0;
    }
    document.querySelectorAll('[data-producto]').forEach(tarjeta => tarjeta.addEventListener('click', () => { carrito.agregar(tarjeta.dataset.producto); dibujar(); }));
    limpiar.addEventListener('click', () => { carrito.limpiar(); dibujar(); });
    formulario.addEventListener('submit', event => { event.preventDefault(); cobrar.click(); });
    [descuento,recibido,metodo].forEach(e => e.addEventListener('input', importes));
    cobrar.addEventListener('click', async () => {
        if (enviando || !carrito.lineas.size) return;
        importes();
        if (!pendiente && !formulario.reportValidity()) return;
        if (!pendiente) {
            pendiente = Object.fromEntries(new FormData(formulario));
            pendiente.recibido = pendiente.recibido || null;
            delete pendiente._token;
            pendiente.detalles = carrito.detalles();
            try { sessionStorage.setItem(almacenamiento, JSON.stringify(pendiente)); } catch { /* El reintento de esta página conserva su clave. */ }
        }
        enviando = true; bloquear(true); aviso.textContent = 'Registrando venta…';
        try {
            const respuesta = await fetch(formulario.action, {method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':formulario.querySelector('[name="_token"]').value},body:JSON.stringify(pendiente)});
            const resultado = await respuesta.json();
            if (!respuesta.ok) {
                if (respuesta.status === 422 || respuesta.status === 403 || respuesta.status === 419) { pendiente = null; try {sessionStorage.removeItem(almacenamiento);} catch {} }
                throw new Error(resultado.errors ? Object.values(resultado.errors).flat().join(' ') : (respuesta.status === 419 ? 'La sesión venció. Recarga e inicia sesión.' : 'No se pudo confirmar el cobro. Reintenta sin cambiar la orden.'));
            }
            if (!resultado.url) throw new Error('No se pudo confirmar el cobro. Reintenta la misma orden.');
            try {sessionStorage.removeItem(almacenamiento);} catch {}
            window.location.assign(resultado.url);
        } catch(error) {
            aviso.textContent = error.message || 'No se pudo confirmar el cobro. Reintenta la misma orden.';
            enviando = false; bloquear(!!pendiente); importes();
        }
    });
    const pedidoInicial = JSON.parse(document.getElementById('pedido-pos').textContent);
    if(pedidoInicial) {
        pedidoInicial.detalles.forEach(l=>carrito.lineas.set(String(l.producto_id),l.cantidad));
        formulario.elements.cliente_id.value = pedidoInicial.cliente_id;
        formulario.elements.notas.value = pedidoInicial.notas || '';
    }
    try {
        const guardado = JSON.parse(sessionStorage.getItem(almacenamiento) || 'null');
        if (guardado?.detalles?.length) {
            pendiente = guardado; carrito.lineas.clear();
            guardado.detalles.forEach(l => {
                if (!carrito.productos.has(String(l.producto_id))) carrito.productos.set(String(l.producto_id), {id:l.producto_id,nombre:'Producto de cobro pendiente',precio_venta:l.precio_esperado});
                carrito.lineas.set(String(l.producto_id),l.cantidad);
                if (l.indicaciones) carrito.indicaciones.set(String(l.producto_id),new Map(l.indicaciones.map(i=>[i.unidad,i])));
            });
            Object.entries(guardado).forEach(([k,v]) => { const campo = formulario.elements.namedItem(k); if(campo && k !== '_token') campo.value = v ?? ''; });
        }
    } catch { /* Almacenamiento del navegador no disponible. */ }
    let categoria = '';
    const buscar = document.getElementById('buscar-pos');
    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    function filtrar() {
        let visibles = 0;
        document.querySelectorAll('[data-producto]').forEach(tarjeta => {
            tarjeta.hidden = !!((categoria && tarjeta.dataset.categoria !== categoria) || !normalizar(tarjeta.dataset.busqueda).includes(normalizar(buscar.value.trim())));
            if (!tarjeta.hidden) visibles++;
        });
        document.getElementById('pos-sin-resultados').hidden = visibles > 0;
    }
    document.querySelectorAll('#categorias-pos button').forEach(boton => boton.addEventListener('click', () => {
        categoria = boton.dataset.categoria;
        document.querySelectorAll('#categorias-pos button').forEach(opcion => { const activo = opcion === boton; opcion.classList.toggle('btn-cafe', activo); opcion.classList.toggle('btn-outline-secondary', !activo); opcion.setAttribute('aria-pressed', String(activo)); });
        filtrar();
    }));
    buscar.addEventListener('input', filtrar); dibujar(); if (pendiente) { bloquear(true); aviso.textContent = 'Hay un cobro sin confirmar. Pulsa Cobrar para consultar/reintentar la misma operación sin duplicarla.'; }
}
