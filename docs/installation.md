# Installation

- [Composer Installation](#composer-installation)
- [Manual Installation](#manual-installation)
- [Database Migration](#database-migration)

## Composer Installation

The only thing you have to do is to run this command, and you're ready to go.

    composer require michalsn/codeigniter-queue

## Manual Installation

In the example below we will assume, that files from this project will be located in `app/ThirdParty/queue` directory.

Download this project and then enable it by editing the `app/Config/Autoload.php` file and adding the `Michalsn\CodeIgniterQueue` namespace to the `$psr4` array, like in the below example:

```php
<?php

// ...

public $psr4 = [
    APP_NAMESPACE => APPPATH, // For custom app namespace
    'Config'      => APPPATH . 'Config',
    'Michalsn\CodeIgniterQueue' => APPPATH . 'ThirdParty/queue/src',
];

// ...
```

## Database Migration

Regardless of which installation method you chose, we also need to migrate the database to add new tables.

You can do this with the following command:

    php spark migrate --all
