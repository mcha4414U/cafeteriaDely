import './bootstrap';
document.addEventListener('click', (event) => { if (event.target.closest('[data-imprimir]')) window.print(); });
import './compras';
import './presentaciones';
import './producto-formulario';
import './notificaciones-stock';
import './recetas';
import './pos';
import 'bootstrap';
import 'admin-lte';
import { createIcons, Coffee, Eye, EyeOff, ArrowRight, Menu, House, Users, LogOut, ShieldCheck, Plus, Pencil, Lock, ShoppingCart, Package, ArrowLeft, Receipt, Bell } from 'lucide';
import Swal from 'sweetalert2';
import DataTable from 'datatables.net-bs5';
const actualizarIconos = () => createIcons({ icons: { Coffee, Eye, EyeOff, ArrowRight, Menu, House, Users, LogOut, ShieldCheck, Plus, Pencil, Lock, ShoppingCart, Package, ArrowLeft, Receipt, Bell } });
actualizarIconos();
document.querySelectorAll('[data-ver-clave]').forEach(boton => boton.addEventListener('click', () => {
    const campo = document.getElementById(boton.dataset.verClave);
    const mostrar = campo.type === 'password'; campo.type = mostrar ? 'text' : 'password';
    boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
}));
document.querySelectorAll('[data-confirmar]').forEach(formulario => formulario.addEventListener('submit', async evento => {
    if (formulario.dataset.confirmado) return;
    evento.preventDefault();
    const resultado = await Swal.fire({ title: formulario.dataset.confirmar, icon: 'question', showCancelButton: true, confirmButtonText: formulario.dataset.accion || 'Guardar', cancelButtonText: 'Cancelar', confirmButtonColor: '#382417' });
    if (resultado.isConfirmed) { formulario.dataset.confirmado = '1'; formulario.requestSubmit(); }
}));
document.querySelectorAll('.tabla-datos').forEach(tabla => new DataTable(tabla, {
    pageLength: 10, columnDefs: [{ targets: -1, orderable: false }], language: {
        decimal: ',', thousands: '.', search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Sin registros', infoFiltered: '(de _MAX_ registros)', zeroRecords: 'No se encontraron registros',
        emptyTable: 'Aún no hay registros', paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
    }, drawCallback: actualizarIconos
}));
document.querySelectorAll('[data-vista-imagen]').forEach(campo => {
    let urlAnterior;
    campo.addEventListener('change', () => {
        if (urlAnterior) URL.revokeObjectURL(urlAnterior);
        const archivo=campo.files[0];
        if (!archivo) return;
        const imagen=document.getElementById(campo.dataset.vistaImagen);
        urlAnterior=URL.createObjectURL(archivo); imagen.src=urlAnterior; imagen.hidden=false;
        document.querySelector('[data-imagen-vacia]')?.setAttribute('hidden','');
    });
});
