# Configuration

To make changes to the config file, we have to have our copy in the `app/Config/Queue.php`. Luckily, this package comes with handy command that will make this easy.

When we run:

    php spark queue:publish

We will get our copy ready for modifications.

---

Available options:

- [$defaultHandler](#defaultHandler)
- [$handlers](#handlers)
- [$database](#database)
- [$keepDoneJobs](#keepDoneJobs)
- [$keepFailedJobs](#keepFailedJobs)
- [$queueDefaultPriority](#queueDefaultPriority)
- [$queuePriorities](#queuePriorities)
- [$jobHandlers](#jobHandlers)

### $defaultHandler

The default handler used by the library. Default value: `database`.

### $handlers

An array of available handlers. By now only `database` handler is implemented.

### $database

The configuration settings for `database` handler.

* `dbGroup` - The database group to use. Default value: `default`.
* `getShared` - Weather to use shared instance. Default value: `true`.

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
