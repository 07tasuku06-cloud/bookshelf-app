<?php

return [
    'required' => ':attributeを入力してください。',
    'string' => ':attributeは文字列で入力してください。',
    'integer' => ':attributeは整数で入力してください。',
    'numeric' => ':attributeは数値で入力してください。',
    'email' => ':attributeはメールアドレス形式で入力してください。',
    'unique' => 'この:attributeは既に使用されています。',
    'confirmed' => ':attributeが確認用と一致しません。',
    'date' => ':attributeは正しい日付で入力してください。',
    'url' => ':attributeは正しいURL形式で入力してください。',

    'min' => [
        'numeric' => ':attributeは:min以上で入力してください。',
        'string' => ':attributeは:min文字以上で入力してください。',
        'array' => ':attributeは:min個以上選択してください。',
    ],

    'max' => [
        'numeric' => ':attributeは:max以下で入力してください。',
        'string' => ':attributeは:max文字以内で入力してください。',
        'array' => ':attributeは:max個以内で選択してください。',
    ],

    'between' => [
        'numeric' => ':attributeは:minから:maxの間で入力してください。',
        'string' => ':attributeは:min文字から:max文字の間で入力してください。',
    ],

    'attributes' => [
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => '確認用パスワード',
        'title' => 'タイトル',
        'author' => '著者名',
        'isbn' => 'ISBN',
        'published_date' => '出版日',
        'description' => '説明',
        'image_url' => '画像URL',
        'genre_ids' => 'ジャンル',
        'rating' => '評価',
        'comment' => 'レビュー',
    ],
];
