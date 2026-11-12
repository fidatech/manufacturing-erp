<?php

// The messages a user meets most often. Any other rule falls back to English.
return [
    'required' => ':attribute ضروری است.',
    'numeric' => ':attribute باید عدد باشد.',
    'integer' => ':attribute باید عدد صحیح باشد.',
    'date' => ':attribute تاریخ درست نیست.',
    'email' => ':attribute باید یک ایمیل آدرس درست باشد.',
    'unique' => 'این :attribute قبلاً ثبت شده است.',
    'exists' => ':attribute انتخاب‌شده درست نیست.',
    'in' => ':attribute انتخاب‌شده درست نیست.',
    'different' => ':attribute و :other باید متفاوت باشند.',
    'confirmed' => 'تکرار :attribute مطابقت ندارد.',
    'image' => ':attribute باید عکس باشد.',
    'mimes' => ':attribute باید فایلی از نوع :values باشد.',
    'array' => ':attribute درست نیست.',
    'string' => ':attribute باید متن باشد.',
    'gt' => ['numeric' => ':attribute باید بیشتر از :value باشد.'],
    'gte' => ['numeric' => ':attribute باید :value یا بیشتر باشد.'],
    'min' => [
        'numeric' => ':attribute نباید کمتر از :min باشد.',
        'string' => ':attribute باید حداقل :min حرف باشد.',
        'file' => ':attribute باید حداقل :min کیلوبایت باشد.',
    ],
    'max' => [
        'numeric' => ':attribute نباید بیشتر از :max باشد.',
        'string' => ':attribute نباید بیشتر از :max حرف باشد.',
        'file' => ':attribute نباید بیشتر از :max کیلوبایت باشد.',
    ],
];
