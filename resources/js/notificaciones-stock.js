const campana = document.querySelector('[data-notificaciones-stock]');
if (campana) {
    const estado = campana.querySelector('[data-estado-avisos]');
    let consultando = false;
    let enviando = false;
    const actualizar = async () => {
        if (consultando || enviando || document.hidden) return;
        consultando = true;
        try {
            const respuesta = await fetch(campana.dataset.url, {headers:{Accept:'application/json'}, cache:'no-store'});
            if (!respuesta.ok) throw new Error();
            const datos = await respuesta.json();
            campana.querySelector('[data-contador-stock]').textContent = datos.cantidad;
            campana.querySelector('[data-lista-avisos]').innerHTML = datos.html;
        } catch {
            estado.textContent = 'No se pudo actualizar la lista. Se volverá a intentar.';
        } finally {
            consultando = false;
        }
    };
    campana.addEventListener('show.bs.dropdown', actualizar);
    window.addEventListener('focus', actualizar);
    setInterval(actualizar, 30000);
    campana.addEventListener('submit', async evento => {
        const formulario = evento.target.closest('[data-avisar-stock]');
        if (!formulario) return;
        evento.preventDefault();
        if (enviando || consultando) return;
        enviando = true;
        const boton = formulario.querySelector('button');
        boton.disabled = true;
        estado.textContent = 'Solicitando aviso…';
        try {
            const respuesta = await fetch(formulario.action, {method:'POST', body:new FormData(formulario), headers:{Accept:'application/json'}});
            const datos = await respuesta.json();
            estado.textContent = datos.mensaje || (respuesta.status === 404 ? 'La alerta ya no está pendiente. Actualiza la lista.' : 'No se pudo solicitar el aviso. Recarga la página.');
        } catch {
            estado.textContent = 'No se pudo confirmar la solicitud. Espera antes de volver a intentarlo.';
        } finally {
            enviando = false;
            boton.disabled = false;
        }
    });
}
