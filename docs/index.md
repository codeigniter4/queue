# CodeIgniter Queue Documentation

A library that helps you handle Queues in the CodeIgniter 4 framework.

Add job to the queue.

```php
service('queue')->push('queueName', 'jobName', ['array' => 'parameters']);
```

Listen for queued jobs.

    php spark queue:work queueName

### Requirements

![PHP](https://img.shields.io/badge/PHP-%5E8.1-red)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-%5E4.3-red)

### Table of Contents

* [Installation](installation.md)
* [Configuration](configuration.md)
* [Basic usage](basic-usage)
* [Running queues](running-queues)
* [Commands](commands.md)
* [Troubleshooting](troubleshooting.md)
