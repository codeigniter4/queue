# CodeIgniter Queue Documentation

A library that helps you handle Queues in the CodeIgniter 4 framework.

Add job to the queue.
```php
service('queue')->push('QueueName', 'jobName', ['array' => 'parameters']);
```

Listen for queue jobs.

    php spark queue:work QueueName

### Requirements

![PHP](https://img.shields.io/badge/PHP-%5E8.1-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-%5E4.3-blue)

### Table of Contents

* [Installation](installation.md)
* [Configuration](configuration.md)
* [Basic usage](basic_usage.md)
* [Running queues](running_queues.md)
* [Commands](commands.md)
* [Troubleshooting](troubleshooting.md)
