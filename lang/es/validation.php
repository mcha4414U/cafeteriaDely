<?php

return [
    'required' => 'El campo :attribute es obligatorio.', 'email' => 'Ingresa un correo electrónico válido.',
    'unique' => 'Este :attribute ya está registrado, incluso entre los archivados.', 'confirmed' => 'La confirmación de :attribute no coincide.',
    'string' => 'El campo :attribute debe ser texto.', 'integer' => 'Selecciona un valor válido para :attribute.', 'boolean' => 'Selecciona un estado válido.',
    'exists' => 'La opción de :attribute no es válida.', 'in' => 'Selecciona una opción válida para :attribute.',
    'numeric' => 'El campo :attribute debe ser un número.', 'decimal' => 'El campo :attribute tiene demasiados decimales.',
    'prohibited' => 'No se permite modificar el campo :attribute desde este formulario.',
    'image' => 'El archivo debe ser una imagen válida.', 'mimes' => 'La imagen debe ser JPG, PNG o WebP.',
    'dimensions' => 'La imagen no puede superar 4000 × 4000 píxeles.', 'uploaded' => 'No se pudo subir la imagen. Revisa su tamaño e inténtalo nuevamente.',
    'date_format' => 'El campo :attribute debe ser una fecha válida.',
    'max' => ['string' => 'El campo :attribute no puede superar :max caracteres.', 'numeric' => 'El campo :attribute no puede superar :max.', 'file' => 'El archivo no puede superar :max KB.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.', 'numeric' => 'El campo :attribute debe ser al menos :min.'],
    'password' => ['mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.', 'numbers' => 'La contraseña debe incluir números.'],
    'attributes' => ['nombre' => 'nombre', 'email' => 'correo electrónico', 'password' => 'contraseña', 'rol_id' => 'rol', 'estado' => 'estado', 'token' => 'enlace', 'categoria_id' => 'categoría', 'codigo' => 'código', 'precio_compra' => 'precio de compra', 'precio_venta' => 'precio de venta', 'stock_inicial' => 'stock inicial', 'stock_actual' => 'stock actual', 'stock_minimo' => 'stock mínimo', 'unidad_medida' => 'unidad de medida', 'fecha_vencimiento' => 'fecha de vencimiento', 'telefono' => 'teléfono', 'direccion' => 'dirección', 'observaciones' => 'observaciones', 'descripcion' => 'descripción', 'imagen' => 'imagen', 'empresa' => 'empresa'],
];
