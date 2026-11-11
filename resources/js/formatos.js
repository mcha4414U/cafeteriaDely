// Recibe valores canónicos, nunca texto ya localizado.
export function cantidadEntrada(valor) {
    const texto = String(valor ?? 0);
    const partes = /^([+-]?)(\d+)(?:\.(\d*))?$/.exec(texto);
    if (!partes) return texto;
    const entero = partes[2].replace(/^0+(?=\d)/, '');
    const decimal = (partes[3] ?? '').replace(/0+$/, '');
    const signo = partes[1] === '-' && (entero !== '0' || decimal) ? '-' : '';
    return signo + entero + (decimal ? '.' + decimal : '');
}
export function cantidadMostrar(valor) {
    const partes = /^(-?)(\d+)(?:\.(\d+))?$/.exec(cantidadEntrada(valor));
    if (!partes) return String(valor);
    return partes[1] + partes[2].replace(/\B(?=(\d{3})+(?!\d))/g,'.') + (partes[3] ? ',' + partes[3] : '');
}
export function monedaCentavos(valor) {
    const negativo = valor < 0n;
    const absoluto = negativo ? -valor : valor;
    return 'Bs. ' + (negativo ? '-' : '') + cantidadMostrar((absoluto / 100n).toString()) + ',' + (absoluto % 100n).toString().padStart(2,'0');
}
