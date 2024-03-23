<?php
/**
 * Plugin Name:       Buytiti - Regla de Precios
 * Plugin URI:        https://buytiti.com
 * Description:       Este plugin añade el tabulador de regla de precios asi como edita el single product
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            Jesus Jimenex
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       buytitiregladeprecios
 *
 * @package           buytiti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function buytitiregladeprecios_buytitiregladeprecios_block_init() {
	register_block_type( __DIR__ . '/build' );
}
add_action( 'init', 'buytitiregladeprecios_buytitiregladeprecios_block_init' );

// Agregar la función de descuento
add_action( 'woocommerce_cart_calculate_fees', 'wc_custom_discount', 10, 1 );
function wc_custom_discount( $cart ) {
    global $category_counts; // Hacer que $category_counts sea global

    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;

    $total_discount = 0;
    $category_counts = array();

    // Contar los productos por categoría contenedora
    foreach ( $cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];
        $categories = get_the_terms( $product->get_id(), 'product_cat' );

        if ( $categories ) {
            // Ordenar las categorías por profundidad, de menos profunda a más profunda
            usort($categories, function($a, $b) {
                return count(get_ancestors($a->term_id, 'product_cat')) - count(get_ancestors($b->term_id, 'product_cat'));
            });

            // Obtener la categoría contenedora (la menos profunda)
            $container_category = $categories[0];

            if ( ! isset( $category_counts[ $container_category->term_id ] ) ) {
                $category_counts[ $container_category->term_id ] = 0;
            }
            $category_counts[ $container_category->term_id ] += $cart_item['quantity'];
        }
    }

    // Calcular el descuento para cada producto individualmente
    foreach ( $cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];
        $categories = get_the_terms( $product->get_id(), 'product_cat' );

        if ( $product->is_on_sale() || ! $categories ) {
            continue; // Si el producto está en oferta o no tiene categoría, no aplicar el descuento
        }

        $discount = 0;
        foreach ( $categories as $category ) {
            // Obtener las categorías padres
            $parent_categories = get_ancestors( $category->term_id, 'product_cat' );
            $all_related_categories = array_merge( array( $category->term_id ), $parent_categories );

            foreach ( $all_related_categories as $cat_id ) {
                if ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 21 ) {
                    $discount = $cart_item['line_subtotal'] * 0.20;
                } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 16 ) {
                    $discount = $cart_item['line_subtotal'] * 0.15;
                } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 9 ) {
                    $discount = $cart_item['line_subtotal'] * 0.10;
                } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 4 ) {
                    $discount = $cart_item['line_subtotal'] * 0.05;
                }
                if ( $discount > 0 ) {
                    break;
                }
            }

            if ( $discount > 0 ) {
                break;
            }
        }

        $total_discount += $discount;
    }

    if ( $total_discount > 0 ) {
        $cart->add_fee( 'Descuento de Mayoreo', -$total_discount );
    }
}

add_action( 'woocommerce_after_single_product_summary', 'wc_display_discount_table', 10 );
function wc_display_discount_table() {
    global $product;

    if ($product && ! $product->is_on_sale()) {
        $price = $product->get_price();

        $table_html = '<table id= "table-precios" style="width: 28rem;text-align: center;margin: auto;">';
        $table_html .= '<tr style="border: 1px solid #FF7942;background-color:  #24a4a4;color: white;border-radius: 15px;"><th>CANTIDAD</th><th>DESCUENTO</th><th>PRECIO MAYOREO</th></tr>';
        $table_html .= '<tr style="border: 1px solid #FF7942;background-color: #addbdb;color: #24a4a4;font-weight: bold;"><td>1 - 3</td><td>-</td><td>$' . number_format($price, 2) . '</td></tr>';
        $table_html .= '<tr style="border: 1px solid #FF7942;color: #24a4a4;font-weight: bold;"><td>4 - 8</td><td>5%</td><td>$' . number_format($price * 0.95, 2) . '</td></tr>';
        $table_html .= '<tr style="border: 1px solid #FF7942;background-color: #addbdb;color: #24a4a4;font-weight: bold;"><td>9 - 15</td><td>10%</td><td>$' . number_format($price * 0.90, 2) . '</td></tr>';
        $table_html .= '<tr style="border: 1px solid #FF7942;color: #24a4a4;font-weight: bold;"><td>16 - 20</td><td>15%</td><td>$' . number_format($price * 0.85, 2) . '</td></tr>';
        $table_html .= '<tr style="border: 1px solid #FF7942;background-color: #FF7942;color: white;font-size: 1.3rem;font-weight: bold;"><td>21 +</td><td>20%</td><td>$' . number_format($price * 0.80, 2) . '</td></tr>';
        $table_html .= '</table>';

        // Pasar el precio del producto al JavaScript
        wp_enqueue_script('wc-price-update', plugins_url('/price-update.js', __FILE__), array('jquery'), '1.0', true);
        wp_localize_script('wc-price-update', 'wc_price_update_vars', array(
            'price' => $price,
        ));

        return $table_html;
    }

    return ''; // Devuelve una cadena vacía si el producto está en oferta o no está definido.
}

add_filter( 'woocommerce_cart_item_price', 'wc_display_discounted_unit_price', 10, 3 );
function wc_display_discounted_unit_price( $price, $cart_item, $cart_item_key ) {
    global $category_counts; // Acceder a la variable global $category_counts

    $product = $cart_item['data'];
    $quantity = $cart_item['quantity'];
    $categories = get_the_terms( $product->get_id(), 'product_cat' );

    if ( $product->is_on_sale() || ! $categories ) {
        return $price; // Si el producto está en oferta o no tiene categoría, mostrar el precio original
    }

    $discount = 0;
    foreach ( $categories as $category ) {
        // Obtener las categorías padres
        $parent_categories = get_ancestors( $category->term_id, 'product_cat' );
        $all_related_categories = array_merge( array( $category->term_id ), $parent_categories );

        foreach ( $all_related_categories as $cat_id ) {
            if ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 21 ) {
                $discount = $product->get_price() * 0.20;
            } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 16 ) {
                $discount = $product->get_price() * 0.15;
            } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 9 ) {
                $discount = $product->get_price() * 0.10;
            } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 4 ) {
                $discount = $product->get_price() * 0.05;
            }
            if ( $discount > 0 ) {
                break;
            }
        }

        if ( $discount > 0 ) {
            // Guardar el precio con descuento en los metadatos del producto
            $discounted_price = $product->get_price() - $discount;
            update_post_meta( $product->get_id(), 'precio_con_descuento', $discounted_price );
            return '<del class="discounted-price">' . wc_price( $product->get_price() ) . '</del> ' . wc_price( $discounted_price );
        }
    }

    return $price;
}

add_filter( 'woocommerce_cart_product_subtotal', 'wc_display_discounted_subtotal', 10, 6 );
function wc_display_discounted_subtotal( $subtotal, $product, $quantity, $cart ) {
    global $category_counts; // Acceder a la variable global $category_counts

    $categories = get_the_terms( $product->get_id(), 'product_cat' );

    if ( $product->is_on_sale() || ! $categories ) {
        return $subtotal; // Si el producto está en oferta o no tiene categoría, mostrar el subtotal original
    }

    $discount = 0;
    foreach ( $categories as $category ) {
        // Obtener las categorías padres
        $parent_categories = get_ancestors( $category->term_id, 'product_cat' );
        $all_related_categories = array_merge( array( $category->term_id ), $parent_categories );

        foreach ( $all_related_categories as $cat_id ) {
            if ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 21 ) {
                $discount = $quantity * $product->get_price() * 0.20;
            } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 16 ) {
                $discount = $quantity * $product->get_price() * 0.15;
            } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 9 ) {
                $discount = $quantity * $product->get_price() * 0.10;
            } elseif ( isset($category_counts[ $cat_id ]) && $category_counts[ $cat_id ] >= 4 ) {
                $discount = $quantity * $product->get_price() * 0.05;
            }
            if ( $discount > 0 ) {
                break;
            }
        }

        if ( $discount > 0 ) {
            // Guardar el subtotal con descuento en los metadatos del producto
            $original_subtotal = $quantity * $product->get_price();
            $discounted_subtotal = $original_subtotal - $discount;
            update_post_meta( $product->get_id(), 'subtotal_con_descuento', $discounted_subtotal );
            return '<del class="discounted-price">' . wc_price( $original_subtotal ) . '</del> ' . wc_price( $discounted_subtotal );
        }
    }

    return $subtotal;
}

// Crear un nuevo endpoint para exponer los precios con descuento
add_action('rest_api_init', function () {
  register_rest_route('miplugin/v1', '/descuento/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'mi_funcion_descuento',
  ));
});

function mi_funcion_descuento($data) {
  $post_id = $data['id'];
  // Aquí puedes obtener el precio y el subtotal con descuento de tu producto
  $precio_con_descuento = get_post_meta($post_id, 'precio_con_descuento', true);
  $subtotal_con_descuento = get_post_meta($post_id, 'subtotal_con_descuento', true);
  return array(
    'precio_con_descuento' => $precio_con_descuento,
    'subtotal_con_descuento' => $subtotal_con_descuento,
  );
}

// Guardar el precio con descuento y el subtotal con descuento en los metadatos de la orden
add_action( 'woocommerce_checkout_create_order_line_item', 'guardar_precio_descuento_en_orden', 10, 4 );
function guardar_precio_descuento_en_orden( $item, $cart_item_key, $values, $order ) {
    $product = $values['data'];
    $precio_con_descuento = get_post_meta( $product->get_id(), 'precio_con_descuento', true );
    $subtotal_con_descuento = get_post_meta( $product->get_id(), 'subtotal_con_descuento', true );

    if ( $precio_con_descuento ) {
        $item->add_meta_data( 'precio_con_descuento', $precio_con_descuento );
    }

    if ( $subtotal_con_descuento ) {
        $item->add_meta_data( 'subtotal_con_descuento', $subtotal_con_descuento );
    }
}

// Mostrar el precio con descuento y el subtotal con descuento en los detalles de la orden
add_filter( 'woocommerce_order_item_get_formatted_meta_data', 'mostrar_precio_descuento_en_orden', 10, 2 );
function mostrar_precio_descuento_en_orden( $formatted_meta, $item ) {
    $precio_con_descuento = $item->get_meta( 'precio_con_descuento', true );
    $subtotal_con_descuento = $item->get_meta( 'subtotal_con_descuento', true );

    if ( $precio_con_descuento ) {
        $formatted_meta[] = (object) array(
            'key' => 'Precio con descuento',
            'value' => wc_price( $precio_con_descuento ),
            'display_key' => 'Precio con descuento',
            'display_value' => wc_price( $precio_con_descuento ),
        );
    }

    if ( $subtotal_con_descuento ) {
        $formatted_meta[] = (object) array(
            'key' => 'Subtotal con descuento',
            'value' => wc_price( $subtotal_con_descuento ),
            'display_key' => 'Subtotal con descuento',
            'display_value' => wc_price( $subtotal_con_descuento ),
        );
    }

    return $formatted_meta;
}

function agregar_parrafo_opciones_envio() {
    $imagen_url = wp_upload_dir()['baseurl'] . '/opcion-de-envio.png';
    
    echo '<div id="opciones-envio">';

    // Contenedor para la imagen de "opciones de envío"
    echo '<div id="opciones-envio-img">';
    echo '<img class="img-envio" src="' . $imagen_url . '" alt="Icono de envío" />';
    echo '</div>';

    // Contenedor para el texto "opciones de envío"
    echo '<div id="opciones-envio-text">';
    echo '<p class="text-envios">Opciones de envío</p>';
    echo '</div>';

    echo '</div>';    
    // Agregar el HTML para la ventana modal
    echo '<div id="modal-opciones-envio">';
    echo '<div>';
	echo '<span id="cerrar-modal">×</span>';
	
    // Agregar la tabla
    echo '<table class= table-envio>';
    echo '<tr class = cabecera-envio><th>Zona</th><th>Región</th><th>Método de envío</th></tr>';
    echo '<tr><td>Zona Centro</td><td>Ciudad de México,

    Jalisco,

    Nuevo León,
    
    Aguascalientes,
    
    Campeche,
    
    Chiapas,
    
    Coahuila, 
    
    Colima,
    
    Durango, 
    
    Guanajuato, 
    
    Guerrero, 
    
    Hidalgo, 
    
    Estado de México, 
    
    Michoacán,
    
    Morelos, 
    
    Nayarit, 
    
    Oaxaca, 
    
    Puebla, 
    
    Querétaro,
    
    Quintana Roo, 
    
    San Luis Potosí, 

    Tabasco,
    
    Tamaulipas,
    
    Tlaxcala,
    
    Veracruz, 
    
    Yucatán,
    
    Zacatecas</td><td><li>Envío gratuito (Compras mayores a 1,999.00 pesos)</li>
    <li>Envío J&T (4-7 días hábiles una vez que validemos el pago el envío se puede extender hasta 10 días en su zona)</li>
    <li>Envío Estafeta (2-7 días hábiles una vez que validemos el pago)</li>
    <li>Proporcionar mi propia paqueteria y guía de envió; Realizado el pedido comparte tu comprobante de pago, numero de pedido y la paqueteria por la cual sera enviado, BUYTITI no se hace responsable de la paqueteria de su elección</li></td></tr>';
    echo '<tr><td>Zona Norte</td><td>Baja California, Baja California Sur, Chihuahua, Sinaloa, Sonora	
    </td><td><li>Envío gratuito (Compras mayores a 1,999.00 pesos)</li>
    <li>Envío J&T (4-7 días hábiles una vez que validemos el pago el envío se puede extender hasta 10 días en su zona)</li>
    <li>Envío Estafeta (2-7 días hábiles una vez que validemos el pago)</li>
    <li>Proporcionar mi propia paqueteria y guía de envió; Realizado el pedido comparte tu comprobante de pago, numero de pedido y la paqueteria por la cual sera enviado, BUYTITI no se hace responsable de la paqueteria de su elección</li></td></tr>';
    echo '<tr><td>Reexpedicion</td><td>29730
    97324,
    77890,
    84560,
    22940,
    39940,
    81910,
    41300,
    62739,
    73700,
    73680,
    93700,
    93829,
    33180,
    22920,
    61700,
    38970,
    73560,
    91480,
    87606</td><td><li>Envío Estafeta (2-7 días hábiles una vez que validemos el pago ) + Costo Zona Reexpedicion</li></td></tr>';
    echo '</table>';

    // Agregar la nota
    echo '<p class = "aviso" >En  <span style="color:red;">&nbsp; ENVÍOS GRATUITOS</span>, la paquetería asignada va depender del peso, medidas y ubicación de entrega.</p>';

    echo '</div>';
    echo '</div>';

    // Encolar el archivo JavaScript
    wp_enqueue_script('wc-modal', plugins_url('/modal.js', __FILE__), array('jquery'), '1.0', true);

}

add_action('woocommerce_before_single_product', 'agregar_parrafo_opciones_envio', 20);

function agregar_parrafo_garantia() {
    $imagen_url_garantia = wp_upload_dir()['baseurl'] . '/garantia.png';
    $imagen_url_protegida = wp_upload_dir()['baseurl'] . '/compra-segura.png';
    $imagen_url_buytiti = wp_upload_dir()['baseurl'] . '/icono_titi.png';

    echo '<div id="garantia-container">';

    // Div para la imagen y texto "compra protegida"
    echo '<div class="protegida-div">';
    echo '<img class="img-protegida" src="' . $imagen_url_protegida . '" alt="Icono de compra segura" />';
    echo '<div class="container-textprotegida">';
    echo '<p class="protegida-text">Compra Segura</p>';
    echo '</div>';

    // Agregar el HTML para la ventana modal de "compra protegida"
    echo '<div id="modal-protegida" class="modal">';
    echo '<div class="modal-content">';
    echo '<span class="close">×</span>';
    echo '<p class = "texto-modal" >"Nuestra plataforma de compras garantiza la máxima seguridad para tus transacciones. Utilizamos servicios confiables como Stripe y MercadoPago, reconocidos por sus altos estándares de seguridad en pagos en línea. Además, ofrecemos la opción de depósito bancario, asegurando flexibilidad en tus métodos de pago. Puedes confiar en que tus datos personales y financieros están protegidos en cada paso del proceso de compra, brindándote tranquilidad y seguridad en todas tus transacciones con nosotros."</p>';
    echo '<img class="img-buytiti" src="' . $imagen_url_buytiti . '" alt="Icono de titi" />';
    echo '</div>';
    echo '</div>';

    echo '</div>';
    
    // Div para la imagen y texto "Garantía"
    echo '<div class="garantia-div">';
    echo '<img class="img-garantia" src="' . $imagen_url_garantia . '" alt="Icono de garantía" />';
    echo '<div class="container-textgaranty">';
    echo '<p class="garantia-text">Garantía</p>';
    echo '</div>';

    // Agregar el HTML para la ventana modal de "garantía"
    echo '<div id="modal-garantia" class="modal">';
    echo '<div class="modal-content">';
    echo '<span class="close">×</span>';
    echo '<p>En buytiti garantizamos nuestros productos, puedes visitar nuestra politica de garantias y devoluciones en:</p>';
    echo '<a href="https://buytiti.com/politica-de-devoluciones-y-reembolsos/" target="_blank" class="boton-garantias">Garantías y Devoluciones</a>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
    
    echo '</div>';

    // Encolar el archivo JavaScript
    wp_enqueue_script('wc-modal-duo', plugins_url('/modal-duo.js', __FILE__), array('jquery'), '1.0', true);
}

add_action('woocommerce_before_single_product', 'agregar_parrafo_garantia', 20);


function mover_parrafo_y_tabla_js() {
    $discount_table = wc_display_discount_table(); // Obtén el contenido de la tabla.
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var opcionesEnvio = document.getElementById('opciones-envio');
            var garantiaContainer = document.getElementById('garantia-container');
            var summaryEntrySummary = document.querySelector('.summary.entry-summary');

            if (opcionesEnvio && garantiaContainer && summaryEntrySummary) {
                // Crear un nuevo contenedor y añadir opciones de envío
                var envioContainer = document.createElement("div");
                envioContainer.id = "envio-container";
                envioContainer.appendChild(opcionesEnvio);

                // Añadir el nuevo contenedor al final de garantia-container
                garantiaContainer.appendChild(envioContainer);

                // Añadir garantía y pagos después del nuevo contenedor
                summaryEntrySummary.appendChild(garantiaContainer);

                // Añadir descuento al final de summary.entry-summary usando insertAdjacentHTML
                summaryEntrySummary.insertAdjacentHTML('beforeend', '<?php echo $discount_table; ?>');
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'mover_parrafo_y_tabla_js');