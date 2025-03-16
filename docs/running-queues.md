# Running queues

Running a queue can be done in several ways. It all depends on what kind of environment and technical skills we have. One of the best ways is to use [Supervisor](http://supervisord.org). Sadly, it's not trivial and will not work for every environment... luckily there are other options too.

### With Supervisor

Since Supervisor is taking care of everything for us and will make out queue worker up and running, we can use this command:

    php spark queue:work emails -wait 10

This will cause command to check for the new jobs every 10 seconds if the queue is empty. But it will not quit. Waiting time is important since we don't want to overflow out database with the unnecessary queries.

### With CRON

Using queues with CRON is more challenging but definitely doable. You can use command like this:

    php spark queue:work emails -max-jobs 20 --stop-when-empty

We can schedule CRON to execute our command every minute. This way, if there are no emails to handle, the command will quit immediately. And if there are many emails the batch of 20 will be handled every minute.

We could think about resigning with `-max-jobs` parameter, but it can have unpredictable consequences (in the worst case scenario) we may have several commands running at the same time, which will send emails, causing the queue to be finished faster (in theory). But the number of the occupied resources, may be quite big. Especially if we will be flooded with the emails by some bad actor.

So choosing the right command is not so obvious. We have to estimate how many jobs we will have in the queue and decide how crucial it is to empty the queue as soon as possible.

You might use CodeIgniter [Tasks](https://github.com/codeigniter4/tasks) library to schedule queue worker instead of working directly with CRON.

### Working with priorities

By default, every job in the queue has the same priority. However, we can send the jobs to the queue with different priorities. This way some jobs may be handled earlier.

As an example, we will define priorities for the `emails` queue:

```php
// app/Config/Queue.php

public array $queueDefaultPriority = [
    'emails' => 'low',
];

public array $queuePriorities = [
    'emails' => ['high', 'low'],
];
```

With this configuration, we can now add new jobs to the queue like this:

```php
// This job will have low priority:
service('queue')->push('emails', 'email', ['message' => 'Email message with low priority']);
// But this one will have high priority
service('queue')->setPriority('high')->push('emails', 'email', ['message' => 'Email message with high priority']);
```

Now, if we run the worker:

    php spark queue:work emails

It will consume the jobs from the queue based on priority set in the config: `$queuePriorities`. So, first `high` priority and then `low` priority.

But we can also run the worker like this:

    php spark queue:work emails -priority low,high

This way, worker will consume jobs with the `low` priority and then with `high`. The order set in the config file is override.

### Delaying jobs

Normally, when we add jobs to a queue, they are run in the order in which we added them to the queue (FIFO - first in, first out).
Of course, there are also priorities, which we described in the previous section. But what about the scenario where we want to run a job, but not earlier than in 5 minutes?

This is where job delay comes into play. We measure the delay in seconds.

```php
// This job will be run not sooner than in 5 minutes
service('queue')->setDelay(5 * MINUTE)->push('emails', 'email', ['message' => 'Email sent no sooner than 5 minutes from now']);
```

Note that there is no guarantee that the job will run exactly in 5 minutes. If many new jobs are added to the queue (without a delay), it may take a long time before the delayed job is actually executed.

We can also combine delayed jobs with priorities.

### Chained jobs

We can create sequences of jobs that run in a specific order. Each job in the chain will be executed after the previous job has completed successfully.

```php
service('queue')->chain(function($chain) {
    $chain
        ->push('reports', 'generate-report', ['userId' => 123])
            ->setPriority('high') // optional
        ->push('emails', 'email', ['message' => 'Email message goes here', 'userId' => 123])
            ->setDelay(30); // optional
});
```

As you may notice, we can use the same options as in regular `push()` - we can set priority and delay, which are optional settings.

#### Important Differences from Regular `push()`

When using the `chain()` method, there are a few important differences compared to the regular `push()` method:

1. **Method Order**: Unlike the regular `push()` method where you set the priority and delay before pushing the job, in a chain you must set these properties after calling `push()` for each job:

    ```php
    // Regular push() - priority set before pushing
    service('queue')->setPriority('high')->push('queue', 'job', []);

    // Chain push() - priority set after pushing
    service('queue')->chain(function($chain) {
        $chain->push('queue', 'job', [])->setPriority('high');
    });
    ```

2. **Configuration Scope**: Each configuration (priority, delay) only applies to the job that was just added to the chain.

### Running many instances of the same queue

As mentioned above, sometimes we may want to have multiple instances of the same command running at the same time. The queue is safe to use in that scenario with all databases as long as you keep the `skipLocked` to `true` in the config file. Only for SQLite3 driver, this setting is not relevant as it provides atomicity without the need for explicit concurrency control.

The PHPRedis and Predis drivers are also safe to use with multiple instances of the same command.

### Handling long-running process

If we decide to run the long process, e.g., with the command:

    php spark queue:work emails -wait 10

We must remember to restart our command every time we add a new job or change the code in the existing job files. The reason is that the changes will not be visible before we restart the command.
