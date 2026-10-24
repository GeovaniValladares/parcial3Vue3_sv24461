<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'present' => 'El campo :attribute debe estar presente.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'exists' => 'El valor seleccionado para :attribute no es válido.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'string' => 'El campo :attribute debe ser texto.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'numeric' => 'El campo :attribute debe ser numérico.',
    'array' => 'El campo :attribute debe ser una lista.',
    'distinct' => 'El campo :attribute contiene valores repetidos.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'date_format' => 'El campo :attribute debe tener el formato :format.',
    'before_or_equal' => 'El campo :attribute no puede ser una fecha futura.',
    'between' => ['numeric' => 'El campo :attribute debe estar entre :min y :max.'],
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.'],
    'password' => ['letters' => 'La contraseña debe contener letras.', 'mixed' => 'La contraseña debe contener mayúsculas y minúsculas.', 'numbers' => 'La contraseña debe contener números.', 'symbols' => 'La contraseña debe contener símbolos.'],
    'attributes' => ['name' => 'nombre', 'email' => 'correo electrónico', 'phone' => 'teléfono', 'password' => 'contraseña', 'current_password' => 'contraseña temporal', 'code' => 'código', 'student_id' => 'estudiante', 'academic_group_id' => 'grupo', 'evaluation' => 'evaluación', 'score' => 'nota', 'recorded_on' => 'fecha de registro', 'student_code' => 'carné', 'career' => 'carrera', 'subject' => 'asignatura', 'teacher_id' => 'docente', 'cycle' => 'ciclo', 'group_ids' => 'grupos', 'observations' => 'observaciones'],
];
