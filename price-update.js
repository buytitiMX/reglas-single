jQuery(document).ready(function($) {
    // Obtener el precio del producto
    var price = wc_price_update_vars.price;

    // Actualizar el precio cuando cambia la cantidad
    $('input.qty').change(function() {
        var quantity = $(this).val();

        var discount;
        if (quantity >= 4 && quantity <= 8) {
            discount = 0.05;
        } else if (quantity >= 9 && quantity <= 15) {
            discount = 0.10;
        } else if (quantity >= 16 && quantity <= 20) {
            discount = 0.15;
        } else if (quantity >= 21) {
            discount = 0.20;
        } else {
            discount = 0;
        }

        var discounted_price = price * (1 - discount);

        // Actualizar el precio en la página
        $('.woocommerce-Price-amount.amount').html('$' + discounted_price.toFixed(2));
    });
});
