<?php

namespace Michalsn\CodeIgniterQueue\Config;

class Registrar
{
    public static function Generators(): array
    {
        return [
            'views' => [
                'queue:job' => 'Michalsn\CodeIgniterQueue\Commands\Generators\Views\job.tpl.php',
            ],
        ];
    }
}
