<?php

return [
    'accepted' => 'Debes aceptar :attribute.',
    'boolean' => ':attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual no es correcta.',
    'email' => ':attribute debe ser un correo electrónico válido.',
    'max' => [
        'array' => ':attribute no debe tener más de :max elementos.',
        'file' => ':attribute no debe pesar más de :max kilobytes.',
        'numeric' => ':attribute no debe ser mayor que :max.',
        'string' => ':attribute no debe tener más de :max caracteres.',
    ],
    'min' => [
        'array' => ':attribute debe tener al menos :min elementos.',
        'file' => ':attribute debe pesar al menos :min kilobytes.',
        'numeric' => ':attribute debe ser al menos :min.',
        'string' => ':attribute debe tener al menos :min caracteres.',
    ],
    'required' => ':attribute es obligatorio.',
    'string' => ':attribute debe ser texto.',
    'unique' => ':attribute ya está registrado.',
    'lowercase' => ':attribute debe estar en minúsculas.',

    'attributes' => [
        'name' => 'el nombre',
        'email' => 'el correo electrónico',
        'password' => 'la contraseña',
        'password_confirmation' => 'la confirmación de contraseña',
        'current_password' => 'la contraseña actual',
        'accept_terms' => 'la aceptación de términos',
    ],
];
