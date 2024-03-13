jQuery(document).ready(function($) {
    // Obtener el modal
    var modal = document.getElementById('modal-opciones-pago');

    // Obtener el botón que abre el modal
    var btn = document.getElementById('pagos-container');

    // Obtener el elemento <span> que cierra el modal
    var span = document.getElementById('cerrar-modal-pago');

    // Cuando el usuario haga clic en el botón, abrir el modal
    btn.onclick = function() {
        modal.style.display = 'block';
    }

    // Cuando el usuario haga clic en <span> (x), cerrar el modal
    span.onclick = function() {
        modal.style.display = 'none';
    }

    // Cuando el usuario haga clic en cualquier lugar fuera del modal, cerrarlo
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
});