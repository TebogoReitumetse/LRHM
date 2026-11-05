<?php

namespace App\Enums;

enum Role: string
{
    case Employee = 'employee';
    case Manager = 'manager';
    case Director = 'director';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Manager => 'Manager',
            self::Director => 'Director',
            self::Admin => 'System Administrator',
        };
    }
}
