# Basic usage

The reason we choose queues is that we want to run jobs in the background.

Here we will present how you can create your own job class, which you will later use in your own queue.

---

- [Create a job class](#creating-a-job-class)
- [Implement a job](#implement-a-job)
- [Sending job to the queue](#sending-job-to-the-queue)
- [Consuming the queue](#consuming-the-queue)


### Create a job class

Here with help comes a generator that will allow us to quickly get started. In our example we will create the `Email` class:

    php spark queue:job Email

The above command will create a file in `App\Jobs` namespace. Once we have our class, we need to add it to the `$jobHandlers` array, in the `Config\Queue` configuration file.

```php
// app/Config/Queue.php

use App\Jobs\Email;

// ...

/**
 * Your jobs handlers.
 */
public array $jobHandlers = [
    'email' => Email::class,
];

// ...
```

### Implement a job

One of the most popular tasks delegated to a queue is sending email messages. Therefore, in this example, we will just implement that.

```php
<?php

namespace App\Jobs;

use Exception;
use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;

class Email extends BaseJob implements JobInterface
{
    /**
     * @throws Exception
     */
    public function process()
    {
        $email  = service('email', null, false);
        $result = $email
            ->setTo('test@email.test')
            ->setSubject('My test email')
            ->setMessage($this->data['message'])
            ->send(false);

        if (! $result) {
            throw new Exception($email->printDebugger('headers'));
        }

        return $result;
    }
}
```

To handles the job we always use the `process` method. This method is called when our job is executed.

You may be wondering what the `$this->data['message']` variable is all about. We'll explain that in detail in the next section, but for now it's important for you to remember that all the variables we pass to the Job class are always held in the `$this->data` variable.

Throwing an exception is a way to let the queue worker know that the job has failed.

We can also configure some things on the job level. It's a number of tries, when the job is failing and time after the job will be retried again after failure. We can specify these options by using variables:

```php
// ...

class Email extends BaseJob implements JobInterface
{
    protected int $retryAfter = 60;
    protected int $tries      = 1;

    // ...

}
```

Values presented above, are the default one. So you need to add them only when you want to change them.

These variables may be overwritten by the queue worker, if we use the proper parameters with command `queue:work`. For more information, see [commands](commands.md).

### Sending job to the queue

Sending a task to the queue is very simple and comes down to one command:

```php
service('queue')->push('queueName', 'jobName', ['array' => 'parameters']);
```

In our particular case, for the `Email` class, it might look like this:

```php
service('queue')->push('emails', 'email', ['message' => 'Email message goes here']);
```

We will be pushing `email` job to the `emails` queue.

### Consuming the queue

Since we sent our sample job to queue `emails`, then we need to run the worker with the appropriate queue:

    php spark queue:work emails

Now we are going to consume jobs from the queue `emails`. This command has many parameters, but you can learn more about that at [commands](commands.md) page.
