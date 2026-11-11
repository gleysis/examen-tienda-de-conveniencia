<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Catálogo de configuración del sistema
    |--------------------------------------------------------------------------
    |
    | Define las opciones de configuración conocidas agrupadas por sección.
    | Cada campo indica tipo, valor por defecto, visibilidad pública y reglas
    | de validación usadas al guardar desde el panel de configuración.
    |
    | Tipos de control: string, text, integer, float, boolean, select, image.
    | Los campos "image" se gestionan con su propio endpoint de subida.
    |
    | "settings.edit" permite editar todos los grupos. "edit_permission" permite
    | además que otros roles editen un grupo concreto. Los grupos marcados como
    | "personal" son predeterminados que cada usuario puede sobrescribir.
    |
    */

    'groups' => [
        'store' => [
            'label' => 'Tienda',
            'description' => 'Información general del negocio.',
            'fields' => [
                'store_name' => [
                    'label' => 'Nombre de la tienda',
                    'type' => 'string',
                    'default' => 'Mi Tienda',
                    'is_public' => true,
                    'rules' => 'required|string|max:255',
                ],
                'store_address' => [
                    'label' => 'Dirección',
                    'type' => 'string',
                    'default' => '',
                    'is_public' => true,
                    'rules' => 'nullable|string|max:255',
                ],
                'store_phone' => [
                    'label' => 'Teléfono',
                    'type' => 'string',
                    'default' => '',
                    'is_public' => true,
                    'rules' => 'nullable|string|max:50',
                ],
                'store_email' => [
                    'label' => 'Correo electrónico',
                    'type' => 'string',
                    'default' => '',
                    'is_public' => true,
                    'rules' => 'nullable|email|max:255',
                ],
                'store_tax_id' => [
                    'label' => 'RUC',
                    'type' => 'string',
                    'default' => '',
                    'is_public' => true,
                    'rules' => 'nullable|string|max:50',
                ],
                'store_logo' => [
                    'label' => 'Logo',
                    'type' => 'image',
                    'default' => '',
                    'is_public' => true,
                    'description' => 'Imagen PNG, JPG o WEBP de hasta 2 MB. Se muestra en el recibo.',
                    'rules' => 'nullable|string|max:2048',
                ],
            ],
        ],

        'sales' => [
            'label' => 'Ventas',
            'description' => 'Configuraciones que afectan la facturación.',
            'fields' => [
                'tax_rate' => [
                    'label' => 'Tasa de IVA (%)',
                    'type' => 'float',
                    'default' => 15,
                    'is_public' => true,
                    'description' => 'Porcentaje aplicado sobre el subtotal en el punto de venta.',
                    'rules' => 'required|numeric|min:0|max:100',
                ],
                'currency_symbol' => [
                    'label' => 'Moneda',
                    'type' => 'string',
                    'default' => 'C$',
                    'is_public' => true,
                    'description' => 'Símbolo que se muestra junto a los precios.',
                    'rules' => 'required|string|max:10',
                ],
                'invoice_prefix' => [
                    'label' => 'Prefijo de factura',
                    'type' => 'string',
                    'default' => 'INV',
                    'is_public' => false,
                    'description' => 'Las facturas se numeran como PREFIJO-000001.',
                    'rules' => 'required|string|max:10|alpha_dash',
                ],
                'allow_negative_stock' => [
                    'label' => 'Permitir ventas sin existencias',
                    'type' => 'boolean',
                    'default' => false,
                    'is_public' => true,
                    'description' => 'Si está activo, las ventas pueden dejar el inventario en negativo.',
                    'rules' => 'required|boolean',
                ],
                'allow_discounts' => [
                    'label' => 'Permitir descuentos en ventas',
                    'type' => 'boolean',
                    'default' => true,
                    'is_public' => true,
                    'description' => 'Habilita los códigos de descuento en el cobro.',
                    'rules' => 'required|boolean',
                ],
                'max_discount_percent' => [
                    'label' => 'Porcentaje máximo de descuento permitido',
                    'type' => 'float',
                    'default' => 100,
                    'is_public' => true,
                    'description' => 'Descuento máximo sobre el subtotal de una venta.',
                    'rules' => 'required|numeric|min:0|max:100',
                ],
            ],
        ],

        'pos' => [
            'label' => 'Punto de Venta',
            'description' => 'Configuraciones de la caja.',
            'edit_permission' => 'settings.pos.edit',
            'fields' => [
                'receipt_footer' => [
                    'label' => 'Mensaje al pie del recibo',
                    'type' => 'text',
                    'default' => '¡Gracias por su compra!',
                    'is_public' => true,
                    'rules' => 'nullable|string|max:500',
                ],
                'auto_print_receipt' => [
                    'label' => 'Impresión automática',
                    'type' => 'boolean',
                    'default' => false,
                    'is_public' => true,
                    'description' => 'Abre el diálogo de impresión al completar una venta.',
                    'rules' => 'required|boolean',
                ],
                'receipt_paper_width' => [
                    'label' => 'Ancho del papel del recibo',
                    'type' => 'select',
                    'default' => '58',
                    'is_public' => true,
                    'options' => ['58' => '58 mm (impresora portátil)', '80' => '80 mm (impresora de mostrador)'],
                    'description' => 'Ajusta el recibo al rollo térmico de la impresora.',
                    'rules' => 'required|in:58,80',
                ],
                'receipt_show_logo' => [
                    'label' => 'Mostrar logo en el recibo',
                    'type' => 'boolean',
                    'default' => true,
                    'is_public' => true,
                    'description' => 'Requiere haber subido un logo en la sección Tienda.',
                    'rules' => 'required|boolean',
                ],
                'receipt_show_store_info' => [
                    'label' => 'Mostrar datos del negocio en el recibo',
                    'type' => 'boolean',
                    'default' => true,
                    'is_public' => true,
                    'description' => 'RUC, dirección, teléfono y correo.',
                    'rules' => 'required|boolean',
                ],
                'confirm_cancel_sale' => [
                    'label' => 'Solicitar confirmación al cancelar una venta',
                    'type' => 'boolean',
                    'default' => true,
                    'is_public' => true,
                    'rules' => 'required|boolean',
                ],
            ],
        ],

        'inventory' => [
            'label' => 'Inventario',
            'description' => 'Control de productos y existencias.',
            'edit_permission' => 'settings.inventory.edit',
            'fields' => [
                'low_stock_default' => [
                    'label' => 'Umbral de stock bajo',
                    'type' => 'integer',
                    'default' => 5,
                    'is_public' => false,
                    'description' => 'Se usa para productos que no tienen un stock mínimo propio.',
                    'rules' => 'required|integer|min:0',
                ],
                'low_stock_alerts' => [
                    'label' => 'Alertas de stock bajo',
                    'type' => 'boolean',
                    'default' => true,
                    'is_public' => false,
                    'description' => 'Muestra en el panel de inicio los productos con stock bajo.',
                    'rules' => 'required|boolean',
                ],
                'notify_out_of_stock' => [
                    'label' => 'Notificar cuando un producto llegue a cero existencias',
                    'type' => 'boolean',
                    'default' => true,
                    'is_public' => false,
                    'description' => 'Avisa al cajero tras la venta y muestra los productos agotados en el panel de inicio.',
                    'rules' => 'required|boolean',
                ],
            ],
        ],

        'printing' => [
            'label' => 'Impresión',
            'personal_label' => 'Impresora',
            'description' => 'Impresoras disponibles y valores de impresión predeterminados para los usuarios.',
            'personal_description' => 'Elige la impresora y las opciones de impresión de tu usuario.',
            'personal' => true,
            'fields' => [
                'available_printers' => [
                    'label' => 'Impresoras disponibles',
                    'type' => 'text',
                    'default' => '',
                    'is_public' => false,
                    'personal' => false,
                    'description' => 'Una impresora por línea, con el nombre exacto configurado en el sistema operativo.',
                    'rules' => 'nullable|string|max:1000',
                ],
                'printer_name' => [
                    'label' => 'Impresora',
                    'type' => 'select',
                    'default' => '',
                    'is_public' => false,
                    'options' => ['' => 'Impresora predeterminada del navegador'],
                    'options_key' => 'available_printers',
                    'description' => 'Impresora que usará este usuario para los recibos.',
                    'rules' => 'nullable|string|max:100',
                ],
                'printer_paper_width' => [
                    'label' => 'Ancho del papel',
                    'type' => 'select',
                    'default' => '80',
                    'is_public' => false,
                    'options' => ['58' => '58 mm', '80' => '80 mm'],
                    'description' => 'Debe coincidir con el rollo térmico de tu impresora.',
                    'rules' => 'required|in:58,80',
                ],
                'printer_copies' => [
                    'label' => 'Copias del recibo',
                    'type' => 'integer',
                    'default' => 1,
                    'is_public' => false,
                    'description' => 'Cantidad de copias que se imprimen por cada venta.',
                    'rules' => 'required|integer|min:1|max:5',
                ],
                'printer_auto_print' => [
                    'label' => 'Impresión automática',
                    'type' => 'boolean',
                    'default' => false,
                    'is_public' => false,
                    'description' => 'Imprime el recibo automáticamente al completar la venta.',
                    'rules' => 'required|boolean',
                ],
            ],
        ],

        'system' => [
            'label' => 'Preferencias del Sistema',
            'personal_label' => 'Mis preferencias',
            'description' => 'Valores predeterminados para los usuarios que no han elegido sus propias preferencias.',
            'personal_description' => 'Se aplican solo a tu usuario y en todos tus dispositivos.',
            'personal' => true,
            'fields' => [
                'theme' => [
                    'label' => 'Tema',
                    'type' => 'select',
                    'default' => 'light',
                    'is_public' => true,
                    'options' => ['light' => 'Claro', 'dark' => 'Oscuro', 'auto' => 'Automático'],
                    'description' => 'Automático sigue la configuración del sistema operativo.',
                    'rules' => 'required|in:light,dark,auto',
                ],
                'language' => [
                    'label' => 'Idioma',
                    'type' => 'select',
                    'default' => 'es',
                    'is_public' => true,
                    'options' => ['es' => 'Español', 'en' => 'Inglés'],
                    'rules' => 'required|in:es,en',
                ],
                'font_size' => [
                    'label' => 'Tamaño de letra',
                    'type' => 'select',
                    'default' => 'normal',
                    'is_public' => true,
                    'options' => ['small' => 'Pequeño', 'normal' => 'Normal', 'large' => 'Grande'],
                    'rules' => 'required|in:small,normal,large',
                ],
                'date_format' => [
                    'label' => 'Formato de fecha',
                    'type' => 'select',
                    'default' => 'dd/MM/yyyy',
                    'is_public' => true,
                    'options' => [
                        'dd/MM/yyyy' => '31/12/2026',
                        'MM/dd/yyyy' => '12/31/2026',
                        'yyyy-MM-dd' => '2026-12-31',
                    ],
                    'rules' => 'required|in:dd/MM/yyyy,MM/dd/yyyy,yyyy-MM-dd',
                ],
                'time_format' => [
                    'label' => 'Formato de hora',
                    'type' => 'select',
                    'default' => 'HH:mm',
                    'is_public' => true,
                    'options' => ['HH:mm' => '24 horas (14:30)', 'hh:mm a' => '12 horas (02:30 PM)'],
                    'rules' => 'required|in:HH:mm,hh:mm a',
                ],
            ],
        ],
    ],
];
