import './bootstrap';
import 'bootstrap';
import 'admin-lte';
import { createIcons, Coffee, Eye, EyeOff, ArrowRight, Menu, House, Users, LogOut, ShieldCheck, Plus, Pencil, Lock } from 'lucide';
import Swal from 'sweetalert2';
import DataTable from 'datatables.net-bs5';
const actualizarIconos = () => createIcons({ icons: { Coffee, Eye, EyeOff, ArrowRight, Menu, House, Users, LogOut, ShieldCheck, Plus, Pencil, Lock } });
actualizarIconos();
document.querySelectorAll('[data-ver-clave]').forEach(boton => boton.addEventListener('click', () => {
    const campo = document.getElementById(boton.dataset.verClave);
    const mostrar = campo.type === 'password'; campo.type = mostrar ? 'text' : 'password';
    boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
}));
document.querySelectorAll('[data-confirmar]').forEach(formulario => formulario.addEventListener('submit', async evento => {
    if (formulario.dataset.confirmado) return;
    evento.preventDefault();
    const resultado = await Swal.fire({ title: formulario.dataset.confirmar, icon: 'question', showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar', confirmButtonColor: '#382417' });
    if (resultado.isConfirmed) { formulario.dataset.confirmado = '1'; formulario.requestSubmit(); }
}));
document.querySelectorAll('.tabla-datos').forEach(tabla => new DataTable(tabla, {
    pageLength: 10, columnDefs: [{ targets: -1, orderable: false }], language: {
        search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ usuarios', info: 'Mostrando _START_ a _END_ de _TOTAL_ usuarios',
        infoEmpty: 'Sin usuarios', infoFiltered: '(de _MAX_ usuarios)', zeroRecords: 'No se encontraron usuarios',
        emptyTable: 'Aún no hay usuarios', paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
    }, drawCallback: actualizarIconos
}));
