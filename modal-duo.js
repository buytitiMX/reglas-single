jQuery(document).ready(function($) {
    // Compra protegida
    $('.protegida-div').on('click', function() {
        $('#modal-protegida').show();
    });

    // Garantía
    $('.garantia-div').on('click', function() {
        $('#modal-garantia').show();
    });

    // Cerrar modal
    $(document).on('click', '.close', function() {
        $(this).closest('.modal').hide();
    });
});