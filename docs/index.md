# CodeIgniter Queue Documentation

A library that helps you handle Queues in the CodeIgniter 4 framework.

!!! info "What are queues used for?"

    A queue system is typically used to handle resource-intensive or time-consuming tasks (e.g., image processing, sending emails) that are to be run in the background. It can also be a way to postpone certain activities that are to be executed automatically later.

Add a job to the queue.

```php
service('queue')->push('queueName', 'jobName', ['array' => 'parameters']);
```

Listen for queued jobs.

    php spark queue:work queueName

### Requirements

- PHP 8.1+
- CodeIgniter 4.3+

If you use `database` handler:

- MySQL 8.0.1+
- MariaDB 10.6+
- PostgreSQL 9.5+
- SQL Server 2012+
- Oracle 12.1+
- SQLite3

If you use `Redis` (you still need a relational database to store failed jobs):

- PHPRedis
- Predis

If you use `RabbitMQ` (you still need a relational database to store failed jobs):

- php-amqplib

### Table of Contents

* [Installation](installation.md)
* [Configuration](configuration.md)
* [Basic usage](basic-usage.md)
* [Running queues](running-queues.md)
* [Commands](commands.md)
* [Events](events.md)
* [Troubleshooting](troubleshooting.md)

### Acknowledgements

Every open-source project depends on its contributors to be a success. The following users have
contributed in one manner or another in making this project:

<a href="https://github.com/codeigniter4/queue/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=codeigniter4/queue" alt="Contributors">
</a>

Made with [contrib.rocks](https://contrib.rocks).
