jQuery(document).ready(function($) {
    // Opciones de envío
    $('#opciones-envio').on('click', function() {
        $('#modal-opciones-envio').show();
    });

    $('#cerrar-modal').on('click', function() {
        $('#modal-opciones-envio').hide();
    });
});
