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

### $jobHandlers

An array of available jobs as key-value. Every job that you want to use with the queue has to be defined here.

The key of the array is used to recognize the job, when we push it to the queue.

Example:

```php
public array $jobHandlers = [
    'email' => Email::class,
];
```
