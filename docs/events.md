# Events

The Queue library provides a comprehensive event system that allows you to monitor and react to various queue operations. Events are triggered at key points in the queue lifecycle, enabling you to implement logging, monitoring, alerting, or custom business logic.

---

## Overview

Queue events are built on top of CodeIgniter's native event system and are emitted automatically by the queue handlers and workers. Each event carries contextual information about what happened, when it happened, and any relevant data.

Events allow you to:

- **Monitor** queue performance and job execution
- **Log** queue activities for debugging and auditing
- **Alert** administrators when jobs fail or workers stop
- **Collect metrics** for analytics and reporting
- **Implement custom logic** based on queue operations

## Available Events

All event names are available as constants in the `CodeIgniter\Queue\Events\QueueEventManager` class.

### Job Events

Events related to individual job lifecycle:

| Event | Constant | When Triggered | Metadata |
|-------|----------|----------------|----------|
| `queue.job.pushed` | `JOB_PUSHED` | A job is added to the queue | `job_class`, `job` |
| `queue.job.push.failed` | `JOB_PUSH_FAILED` | Failed to push a job to the queue | `job_class`, `exception` |
| `queue.job.processing.started` | `JOB_PROCESSING_STARTED` | A worker starts processing a job | `job_class`, `job`, `worker_id` |
| `queue.job.processing.completed` | `JOB_PROCESSING_COMPLETED` | A job completes successfully | `job_class`, `job`, `processing_time`, `worker_id` |
| `queue.job.failed` | `JOB_FAILED` | A job fails after exhausting all retry attempts | `job_class`, `job`, `exception`, `processing_time`, `worker_id` |

### Worker Events

Events related to queue worker lifecycle:

| Event | Constant | When Triggered | Metadata |
|-------|----------|----------------|----------|
| `queue.worker.started` | `WORKER_STARTED` | A queue worker starts processing | `priorities`, `config`, `worker_id` |
| `queue.worker.stopped` | `WORKER_STOPPED` | A queue worker stops | `priorities`, `uptime_seconds`, `jobs_processed`, `worker_id`, `stop_reason`, `memory_usage`, `memory_peak` |

Worker stop reasons include:

- `signal_stop` - Stopped by signal (SIGTERM, SIGINT, etc.)
- `memory_limit` - Memory limit reached
- `time_limit` - Max time limit reached
- `job_limit` - Max jobs processed
- `planned_stop` - Scheduled stop via `queue:stop` command
- `empty_queue` - Queue is empty (with `--stop-when-empty` flag)

### Handler Events

Events related to queue handler connections:

| Event | Constant | When Triggered | Metadata |
|-------|----------|----------------|----------|
| `queue.handler.connection.established` | `HANDLER_CONNECTION_ESTABLISHED` | Successfully connected to queue backend | `config` |
| `queue.handler.connection.failed` | `HANDLER_CONNECTION_FAILED` | Failed to connect to queue backend | `exception`, `config` |

### Operation Events

Events related to queue operations:

| Event | Constant | When Triggered | Metadata |
|-------|----------|----------------|----------|
| `queue.cleared` | `QUEUE_CLEARED` | Queue is cleared via `queue:clear` command | None |

## Listening to Events

You can listen to queue events using CodeIgniter's standard event system. Add your listeners in `app/Config/Events.php`:

```php
<?php

use CodeIgniter\Events\Events;
use CodeIgniter\Queue\Events\QueueEvent;
use CodeIgniter\Queue\Events\QueueEventManager;

// Listen to job completion
Events::on(QueueEventManager::JOB_PROCESSING_COMPLETED, static function (QueueEvent $event) {
    log_message('info', 'Job completed: {job} in {time}s', [
        'job'  => $event->getJobClass(),
        'time' => $event->getProcessingTime(),
    ]);
});

// Listen to job failures
Events::on(QueueEventManager::JOB_FAILED, static function (QueueEvent $event) {
    log_message('error', 'Job failed: {job} - {error}', [
        'job'   => $event->getJobClass(),
        'error' => $event->getExceptionMessage(),
    ]);
});

// Listen to worker lifecycle
Events::on(QueueEventManager::WORKER_STARTED, static function (QueueEvent $event) {
    log_message('info', 'Worker started for queue: {queue}', [
        'queue' => $event->getQueue(),
    ]);
});

Events::on(QueueEventManager::WORKER_STOPPED, static function (QueueEvent $event) {
    $metadata = $event->getAllMetadata();

    log_message('info', 'Worker stopped: {reason}, processed {jobs} jobs in {time}s', [
        'reason' => $metadata['stop_reason'],
        'jobs'   => $metadata['jobs_processed'],
        'time'   => round($metadata['uptime_seconds'], 2),
    ]);
});
```

## Event Object

All event listeners receive a `QueueEvent` object with the following methods:

### Basic Information

```php
$event->getType();      // Event type (e.g., 'queue.job.pushed')
$event->getHandler();   // Handler name (e.g., 'database', 'redis')
$event->getQueue();     // Queue name (e.g., 'emails', 'default')
$event->getTimestamp(); // Time object when event occurred
```

### Event Type Checks

```php
$event->isJobEvent();        // True for job-related events
$event->isWorkerEvent();     // True for worker-related events
$event->isConnectionEvent(); // True for connection-related events
$event->isOperationEvent();  // True for operation events
```

### Job Information (for job events)

```php
$event->getJobId();            // Job ID
$event->getJobClass();         // Fully qualified job class name
$event->getPriority();         // Job priority
$event->getAttempts();         // Number of attempts
$event->getStatus();           // Job status
$event->getProcessingTime();   // Processing time in seconds
$event->getProcessingTimeMs(); // Processing time in milliseconds
```

### Error Information (for failed events)

```php
$event->getException();        // Exception object (if any)
$event->getExceptionMessage(); // Exception message
$event->hasFailed();           // True if event contains an exception
```

### Metadata Access

```php
// Get specific metadata
$event->getMetadata('worker_id');
$event->getMetadata('priority', 'default');

// Get all metadata
$metadata = $event->getAllMetadata();
```
