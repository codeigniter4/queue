<?php

namespace CodeIgniter\Queue\Config;

class Registrar
{
    public static function Generators(): array
    {
        return [
            'views' => [
                'queue:job' => 'CodeIgniter\Queue\Commands\Generators\Views\job.tpl.php',
            ],
        ];
    }
}
