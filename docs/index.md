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
* [Basic usage](basic-usage.md)
* [Running queues](running-queues.md)
* [Commands](commands.md)
* [Troubleshooting](troubleshooting.md)

### Acknowledgements

Every open-source project depends on its contributors to be a success. The following users have
contributed in one manner or another in making this project:

<a href="https://github.com/codeigniter4/queue/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=codeigniter4/queue" alt="Contributors">
</a>

Made with [contrib.rocks](https://contrib.rocks).
