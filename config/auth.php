<?php

use App\Models\Admin;
use App\Models\User;

return [
    "defaults" => [
        "guard" => env("AUTH_GUARD", "web"),
        "passwords" => env("AUTH_PASSWORD_BROKER", "users"),
    ],

    "guards" => [
        "web" => [
            "driver" => "session",
            "provider" => "users",
        ],
        "admin" => [
            "driver" => "session",
            "provider" => "admins",
        ],
        "client" => [
            "driver" => "session",
            "provider" => "users",
        ],
    ],

    "providers" => [
        "users" => [
            "driver" => "eloquent",
            "model" => User::class,
        ],
        "admins" => [
            "driver" => "eloquent",
            "model" => Admin::class,
        ],
    ],

    "passwords" => [
        "users" => [
            "provider" => "users",
            "table" => "password_reset_tokens",
            "expire" => 60,
            "throttle" => 60,
        ],
        "admins" => [
            "provider" => "admins",
            "table" => "password_reset_tokens",
            "expire" => 60,
            "throttle" => 60,
        ],
    ],

    "password_timeout" => env("AUTH_PASSWORD_TIMEOUT", 10800),
];
