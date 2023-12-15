# Commands

Here are all the commands you can use with the Queue library.

---

Available options:

- [queue:publish](#queuepublish)
- [queue:job](#queuejob)
- [queue:work](#queuework)
- [queue:stop](#queuestop)
- [queue:clear](#queueclear)
- [queue:failed](#queuefailed)
- [queue:retry](#queueretry)
- [queue:forget](#queueforget)
- [queue:flush](#queueflush)


### queue:publish

Allows you to publish a configuration class in the application namespace.

##### Example

    php spark queue:publish

### queue:job

Generates a new job file.

##### Arguments

* `name` - The job class name.

##### Options

* `--namespace` - Set root namespace. Default: "APP_NAMESPACE".
* `--suffix` - Append the component title to the class name (e.g. Email => EmailJob).
* `--force` - Force overwrite existing file.

##### Example

    php spark queue:job Email

It will generate the `Email` class in the `App\Jobs` namespace.

### queue:work

Allows you to consume jobs from a specific queue.

##### Arguments

* `queueName` - Name of the queue we will work with.

##### Options

* `-sleep` - Wait time between the next check for available job when the queue is empty. Default value: `10` (seconds).
* `-rest` - Rest time between the jobs in the queue. Default value: `0` (seconds)
* `-max-jobs` - The maximum number of jobs to handle before worker should exit. Disabled by default.
* `-max-time` - The maximum number of seconds worker should run. Disabled by default.
* `-memory` - The maximum memory in MB that worker can take. Default value: `128`.
* `-priority` - The priority for the jobs from the queue (comma separated). If not provided explicit, will follow the priorities defined in the config via `$queuePriorities` for the given queue. Disabled by default.
* `-tries` - The number of attempts after which the job will be considered as failed. Overrides settings from the Job class. Disabled by default.
* `-retry-after` - The number of seconds after which the job is to be restarted in case of failure. Overrides settings from the Job class. Disabled by default.
* `--stop-when-empty` - Stop when the queue is empty.

##### Example

    php spark queue:work emails -max-jobs 5

It will listen for 5 jobs from the `emails` queue and then stop.

    php spark queue:work emails -max-jobs 5 -priority low,high

It will work the same as the previous command but will first consume jobs from the `emails` queue that were added with the `low` priority.

### queue:stop

Allows you to stop a specific queue in a safe way. It does this as soon as the job that is running in the queue is completed.

##### Arguments

* `queueName` - Name of the queue we will work with.

##### Example

    php spark queue:stop emails

### queue:clear

Allows you to remove all jobs from a specific queue.

##### Arguments

* `queueName` - Name of the queue we will work with.

##### Example

    php spark queue:clear emails

### queue:failed

Allows you to view all failed jobs. Also only from a specific queue

##### Options

* `-queue` - Queue name.

##### Example

    php spark queue:failed -queue emails

It will list failed jobs from the `emails` queue.

### queue:retry

Allows you to retry failed jobs back to the queue.

##### Arguments

* `id` - ID of the failed job or "all" for all failed jobs.

##### Options

* `-queue` -  Queue name.

##### Example

    php spark queue:retry all -queue emails

It will retry all the failed jobs from the `emails` queue.

### queue:forget

Allows you to delete the failed job by ID

##### Arguments

* `id` - ID of the failed job.

##### Example

    php spark queue:forget 123

### queue:flush

Allows you to delete many failed jobs at once. Based on the failed date and queue.

##### Options

* `-hours` - Number of hours.
* `-queue` - Queue name.

##### Example

    php spark queue:flush -hours 6

It will delete all failed jobs older than 6 hours.
