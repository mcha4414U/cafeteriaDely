// Cantidades y dinero como enteros para que la vista previa coincida con el redondeo del servidor.
const escalado=(valor,decimales)=>{
    const texto=String(valor).trim();
    if(!/^\d+(\.\d*)?$/.test(texto)) return 0n;
    const [entero,fraccion='']=texto.split('.');
    return BigInt(entero+fraccion.padEnd(decimales,'0').slice(0,decimales));
};
const dinero=(centavos)=>'Bs. '+(centavos/100n).toString()+'.'+(centavos%100n).toString().padStart(2,'0');
document.querySelectorAll('[data-compra]').forEach(formulario=>{
    const cuerpo=formulario.querySelector('[data-lineas-compra]');
    let siguiente=Math.max(-1,...Array.from(cuerpo.querySelectorAll('select')).map(select=>Number(select.name.match(/\[(\d+)\]/)?.[1] ?? 0)))+1;
    const recalcular=()=>{
        let total=0n;
        cuerpo.querySelectorAll('[data-linea-compra]').forEach(fila=>{
            const cantidad=escalado(fila.querySelector('[data-cantidad-compra]').value,3);
            const costo=escalado(fila.querySelector('[data-costo-compra]').value,2);
            const subtotal=(cantidad*costo+500n)/1000n;
            total+=subtotal; fila.querySelector('[data-subtotal-compra]').textContent=dinero(subtotal);
        });
        formulario.querySelector('[data-total-compra]').textContent=dinero(total);
    };
    formulario.querySelector('[data-agregar-linea]').addEventListener('click',()=>{
        if(cuerpo.children.length>=100) return;
        const fragmento=formulario.querySelector('template').content.cloneNode(true);
        fragmento.querySelectorAll('[name]').forEach(campo=>campo.name=campo.name.replace('INDICE',siguiente));
        siguiente++; cuerpo.append(fragmento); recalcular();
    });
    cuerpo.addEventListener('click',evento=>{
        if(!evento.target.closest('[data-quitar-linea]')) return;
        if(cuerpo.children.length>1) evento.target.closest('tr').remove();
        recalcular();
    });
    cuerpo.addEventListener('change',evento=>{
        if(evento.target.matches('[data-producto-compra]')) {
            evento.target.closest('tr').querySelector('[data-costo-compra]').value=evento.target.selectedOptions[0]?.dataset.costo || '0';
        }
        recalcular();
    });
    cuerpo.addEventListener('input',recalcular);
    recalcular();
});
