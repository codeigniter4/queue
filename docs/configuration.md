# Configuration

To make changes to the config file, we have to have our copy in the `app/Config/Queue.php`. Luckily, this package comes with handy command that will make this easy.

When we run:

    php spark queue:publish

We will get our copy ready for modifications.

---

Available options:

- [$defaultHandler](#defaulthandler)
- [$handlers](#handlers)
- [$database](#database)
- [$redis](#redis)
- [$predis](#predis)
- [$keepDoneJobs](#keepdonejobs)
- [$keepFailedJobs](#keepfailedjobs)
- [$queueDefaultPriority](#queuedefaultpriority)
- [$queuePriorities](#queuepriorities)
- [$jobHandlers](#jobhandlers)

### $defaultHandler

The default handler used by the library. Default value: `database`.

### $handlers

An array of available handlers. By now only `database`, `redis` and `predis` handlers are implemented.

### $database

The configuration settings for `database` handler.

* `dbGroup` - The database group to use. Default value: `default`.
* `getShared` - Weather to use shared instance. Default value: `true`.

### $redis

The configuration settings for `redis` handler. You need to have a [ext-redis](https://github.com/phpredis/phpredis) installed to use it.

* `host` - The host name or unix socket. Default value: `127.0.0.1`.
* `password` - The password. Default value: `null`.
* `port` - The port number. Default value: `6379`.
* `timeout` - The timeout for connection. Default value: `0`.
* `database` - The database number. Default value: `0`.
* `prefix` - The default key prefix. Default value: `''` (not set).

### $predis

The configuration settings for `predis` handler. You need to have [Predis](https://github.com/predis/predis) installed to use it.

* `scheme` - The scheme to use: `tcp`, `tls` or `unix`. Default value: `tcp`.
* `host` - The host name. Default value: `127.0.0.1`.
* `password` - The password. Default value: `null`.
* `port` - The port number (when `tcp`). Default value: `6379`.
* `timeout` - The timeout for connection. Default value: `5`.
* `database` - The database number. Default value: `0`.
* `prefix` - The default key prefix. Default value: `''` (not set).

### $keepDoneJobs

If the job is done, should we keep it in the table? Default value: `false`.

### $keepFailedJobs

If the job failed, should we move it to the failed jobs table? Default value: `true`.

This is very useful when you want to be able to see which tasks are failing and why.

### $queueDefaultPriority

The default priority for the `queue` if non default `queuePriorities` are set. Not set by default.

This is needed only if you have defined non default priorities for the queue and the default priority should be different from the `default` value.

Example:

```php
public array $queueDefaultPriority = [
    'emails' => 'low',
];
```

This means that all the jobs added to the `emails` queue will have the default priority set to `low`.

### $queuePriorities

The valid priorities for the `queue` in the order they will be consumed first. Not set by default.

By default, the priority is set to `['default']`. If you want to have multiple priorities in the queue, you can define them here.

Example:

```php
public array $queuePriorities = [
    'emails' => ['high', 'low'],
];
```

This means that the jobs added to the `emails` queue can have either `high` or `low` priority.

### $jobHandlers

An array of available jobs as key-value. Every job that you want to use with the queue has to be defined here.

The key of the array is used to recognize the job, when we push it to the queue.

Example:

```php
public array $jobHandlers = [
    'email' => Email::class,
];
```
