# CodeIgniter Queue

Queues for the CodeIgniter 4 framework.

[![PHPUnit](https://github.com/codeigniter4/queue/actions/workflows/phpunit.yml/badge.svg)](https://github.com/codeigniter4/queue/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/codeigniter4/queue/actions/workflows/phpstan.yml/badge.svg)](https://github.com/codeigniter4/queue/actions/workflows/phpstan.yml)
[![Deptrac](https://github.com/codeigniter4/queue/actions/workflows/deptrac.yml/badge.svg)](https://github.com/codeigniter4/queue/actions/workflows/deptrac.yml)
[![Coverage Status](https://coveralls.io/repos/github/codeigniter4/queue/badge.svg?branch=develop)](https://coveralls.io/github/codeigniter4/queue?branch=develop)

![PHP](https://img.shields.io/badge/PHP-%5E8.1-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-%5E4.3-blue)
![License](https://img.shields.io/badge/License-MIT-blue)

## Installation

    composer require codeigniter4/queue

Migrate your database:

    php spark migrate --all

## Configuration

Publish configuration file:

    php spark queue:publish

Create your first Job:

    php spark queue:job Example

Add it to the `$jobHandlers` array in the `app\Config\Queue.php` file:

```php
// ...

use App\Jobs\Example;

// ...

public array $jobHandlers = [
    'my-example' => Example::class
];

// ...
```

## Basic usage

Add job to the queue:

```php
service('queue')->push('queueName', 'my-example', ['data' => 'array']);
```

Run the queue worker:

    php spark queue:work queueName

## Docs

Read the full documentation: https://queue.codeigniter.com

## Contributing

We accept and encourage contributions from the community in any shape. It doesn't matter
whether you can code, write documentation, or help find bugs, all contributions are welcome.
See the [CONTRIBUTING.md](CONTRIBUTING.md) file for details.

