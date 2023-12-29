# Installation

- [Composer Installation](#composer-installation)
- [Manual Installation](#manual-installation)
- [Database Migration](#database-migration)

## Composer Installation

The only thing you have to do is to run this command, and you're ready to go.

    composer require codeigniter4/queue

#### A composer error occurred?

If you get the following error:

```console
Could not find a version of package codeigniter4/queue matching your minimum-stability (stable).
Require it with an explicit version constraint allowing its desired stability.
```

1. Run the following commands to change your [minimum-stability](https://getcomposer.org/doc/articles/versions.md#minimum-stability) in your project `composer.json`:

    ```console
    composer config minimum-stability dev
    composer config prefer-stable true
    ```

2. Or specify an explicit version:

    ```console
    composer require codeigniter4/queue:dev-develop
    ```

   The above specifies `develop` branch.
   See <https://getcomposer.org/doc/articles/versions.md#branches>

## Manual Installation

In the example below we will assume, that files from this project will be located in `app/ThirdParty/queue` directory.

Download this project and then enable it by editing the `app/Config/Autoload.php` file and adding the `CodeIgniter\Queue` namespace to the `$psr4` array, like in the below example:

```php
<?php

// ...

public $psr4 = [
    APP_NAMESPACE => APPPATH, // For custom app namespace
    'Config'      => APPPATH . 'Config',
    'CodeIgniter\Queue' => APPPATH . 'ThirdParty/queue/src',
];

// ...
```

## Database Migration

Regardless of which installation method you chose, we also need to migrate the database to add new tables.

You can do this with the following command:

    php spark migrate --all
