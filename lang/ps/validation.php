<?php

// The messages a user meets most often. Any other rule falls back to English.
return [
    'required' => ':attribute اړین دی.',
    'numeric' => ':attribute باید شمېره وي.',
    'integer' => ':attribute باید پوره شمېره وي.',
    'date' => ':attribute سمه نېټه نه ده.',
    'email' => ':attribute باید سم برېښنالیک وي.',
    'unique' => 'دا :attribute مخکې ثبت شوی دی.',
    'exists' => 'ټاکل شوی :attribute سم نه دی.',
    'in' => 'ټاکل شوی :attribute سم نه دی.',
    'different' => ':attribute او :other باید توپیر ولري.',
    'confirmed' => 'د :attribute تکرار سمون نه لري.',
    'image' => ':attribute باید عکس وي.',
    'mimes' => ':attribute باید د :values ډول فایل وي.',
    'array' => ':attribute سم نه دی.',
    'string' => ':attribute باید متن وي.',
    'gt' => ['numeric' => ':attribute باید له :value څخه زیات وي.'],
    'gte' => ['numeric' => ':attribute باید :value یا زیات وي.'],
    'min' => [
        'numeric' => ':attribute باید له :min څخه کم نه وي.',
        'string' => ':attribute باید لږ تر لږه :min توري وي.',
        'file' => ':attribute باید لږ تر لږه :min کیلوبایټه وي.',
    ],
    'max' => [
        'numeric' => ':attribute باید له :max څخه زیات نه وي.',
        'string' => ':attribute باید له :max تورو زیات نه وي.',
        'file' => ':attribute باید له :max کیلوبایټو زیات نه وي.',
    ],
];
