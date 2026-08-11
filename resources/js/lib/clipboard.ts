/**
 * Copia texto al portapapeles con degradación en cadena.
 *
 * La API moderna (navigator.clipboard) exige contexto seguro y permiso, y falla
 * en bastantes situaciones reales: http plano, webviews, políticas del navegador.
 * Por eso hay un segundo intento con execCommand, que es viejo pero funciona casi
 * en todos lados. Devuelve false solo si ambos fallan, para poder avisar al usuario.
 */
export async function copyToClipboard(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // Sigue con el método legacy
    }

    try {
        const helper = document.createElement('textarea');
        helper.value = text;
        // Fuera de vista pero seleccionable: display:none o visibility:hidden no sirven
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.top = '-1000px';
        helper.style.opacity = '0';
        document.body.appendChild(helper);

        helper.select();
        helper.setSelectionRange(0, text.length); // iOS ignora select() a secas

        const ok = document.execCommand('copy');
        document.body.removeChild(helper);

        return ok;
    } catch {
        return false;
    }
}
