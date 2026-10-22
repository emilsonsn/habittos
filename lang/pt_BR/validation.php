<?php

return [
    'required' => 'O campo :attribute é obrigatório.',
    'required_if' => 'O campo :attribute é obrigatório quando :other é :value.',
    'email' => 'Informe um e-mail válido.',
    'unique' => 'Este :attribute já está cadastrado.',
    'exists' => 'O valor selecionado para :attribute não está disponível.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'min' => ['string' => ':attribute deve ter ao menos :min caracteres.', 'numeric' => ':attribute deve ser no mínimo :min.'],
    'max' => ['string' => ':attribute deve ter no máximo :max caracteres.', 'numeric' => ':attribute deve ser no máximo :max.'],
    'between' => ['numeric' => ':attribute deve estar entre :min e :max.'],
    'numeric' => ':attribute deve ser um número.',
    'integer' => ':attribute deve ser um número inteiro.',
    'decimal' => ':attribute deve ter até duas casas decimais.',
    'date_format' => 'O formato de :attribute deve ser :format.',
    'in' => 'O valor de :attribute é inválido.',
    'enum' => 'O valor de :attribute é inválido.',
    'string' => ':attribute deve ser texto.',
    'boolean' => 'O valor de :attribute é inválido.',
    'array' => 'O valor de :attribute deve ser uma lista.',
    'password' => ['letters' => 'A senha deve conter letras.', 'numbers' => 'A senha deve conter números.'],
    'attributes' => ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'description' => 'descrição', 'amount' => 'valor', 'date' => 'data', 'month' => 'mês', 'year' => 'ano', 'category_id' => 'categoria', 'title' => 'título', 'target' => 'valor desejado', 'current' => 'valor alcançado', 'weekly_limit' => 'limite semanal', 'reserve' => 'reserva', 'permissions' => 'permissões', 'type' => 'tipo'],
];
