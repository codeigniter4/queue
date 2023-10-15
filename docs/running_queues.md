# Running queues

Running a queue can be done in several ways. It all depends on what kind of environment and technical skills we have. One of the best ways is to use [Supervisor](http://supervisord.org). Sadly, it's not trivial and will not work for every environment... luckily there are other options too.

### With Supervisor

Since Supervisor is taking care of everything for us and will make out queue worker up and running, we can use this command:

    php spark queue:work emails -wait 10

This will cause command to check for the new jobs every 10 seconds if the queue is empty. But it will not quit. Waiting time is important since we don't want to overflow out database with the unnecessary queries.

### With CRON

Using queues with CRON is more challenging, but definitely doable. You can use command like this:

    php spark queue:work emails -max-jobs 20 --stop-when-empty

We can schedule CRON to execute our command every minute. This way, if there are no emails to handle, the command will quit immediately. And if there are many emails the batch of 20 will be handled every minute.

We could think about resigning with `-max-jobs` parameter, but it can have unpredictable consequences (in the worst case scenario) we may have several commands running at the same time, which will send emails, causing the queue to be finished faster (in theory). But the number of the occupied resources, may be quite big. Especially if we will be flooded with the emails by some bad actor.

So choosing the right command is not so obvious. We have to estimate how many jobs we will have in the queue and decide how crucial it is to empty the queue as soon as possible.

You might use CodeIgniter [Tasks](https://github.com/codeigniter4/tasks) library to schedule queue worker instead of working directly with CRON.

### Running many instances of the same queue

As mentioned above, sometimes we may want to have multiple instances of the same command running at the same time. The queue is safe to use in that scenario with all databases except `SQLite3` since it doesn't guarantee that the job will be selected only by one process.

### Handling long-running process

If we decide to run the long process e.g. with the command:

    php spark queue:work emails -wait 10

We must remember to restart our command every time we add a new job or change the code in the existing job files. The reason is that the changes will not be visible before we restart the command.
