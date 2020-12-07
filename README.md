cloud-tasks-wrapper provides a higher-level interface for dispatching and processing Google Cloud Tasks from PHP.

[![Tests](https://github.com/ingenerator/cloud-tasks-wrapper/workflows/Run%20tests/badge.svg)](https://github.com/ingenerator/cloud-tasks-wrapper/actions)

# Installing cloud-tasks-wrapper

This isn't in packagist yet : you'll need to add our package repository to your composer.json:

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://php-packages.ingenerator.com"
    }
  ]
}
```

`$> composer require ingenerator/cloud-tasks-wrapper`

# Functionality included

The wrapper is divided into two sections:

* Client - queues HTTP tasks for processing
* Server - handles incoming task requests (framework-independent)

Many simple applications will implement both the client and server within the same codebase. However, the wrapper makes
it easy to build standalone task handlers, or to target specific task types to different handler endpoints based on
configuration.

## Task types

The wrapper is structured around the concept of task types. A task type is a simple string value that defines an
application-level task. For example, you might have task types including `recalculate-deletion-date`,
`send-email-notification`, `queue-email-batch`, `extract-image-text` etc.

Task types are mapped to handler URLs through global configuration. For example:

```php
use Ingenerator\CloudTasksWrapper\TaskTypeConfigProvider;

$task_cfg = [
    // Will be applied to all task types
    '_default'                  => [
        // The GCP queue to create these tasks in
        'queue'        => ['project' => 'my-app', 'location' => 'europe-west-1', 'name' => 'high-priority'],
        // Optional, the service account email address that GCP should use to sign OIDC auth tokens
        // If this is set then the wrapper's default task handler chain will also verify that the
        // OIDC token matches this email address.
        'signer_email' => 'app-task-publisher@my-app.iam.gserviceaccount.com',
        // The HTTP URL where tasks of this type should be sent. Supports a placeholder for simple
        // string replacement.
        'handler_url'  => 'https://my.app/_task/{TASK_TYPE}',
    ],
    'recalculate-deletion-date' => [
        // This task type is now defined, and will just use the defaults
        // Requests will be sent to `https://my.app/_task/recalculate-deletion-date`
    ],
    'extract-image-text'        => [
        // This task is offloaded to a different service for processing
        'handler_url' => $is_development ? 'http://imageservice.emulator:3900' : 'https://my.image-processing.func/do-it',
    ],
    'handle-third-party'        => [
        // This is an incoming task type sent by a different project / codebase / etc altogether
        // They would push tasks into a queue with your endpoint URL, and sign with their service account
        'signer_email' => 'some-integration@external-service.iam.gserviceaccount.com',
    ],
    'queue-email-batch'         => [
        // This is sent through a different queue, perhaps with lower rate limits / concurrency
        'queue' => ['name' => 'background-jobs'],
    ],
];
// In most cases this will be instantiated in your dependency container
$task_types = new TaskTypeConfigProvider($task_cfg);
```

Your app's usecases and processes only have / need knowledge of the `task_type` to despatch and can therefore be easily
tested. The actual queue / handler URL / auth signing details can easily be overridden at the config layer e.g. to suit
dev / prod environments.

## Creating tasks

To create/queue a task from your application, use the `TaskCreator` interface. The `CloudTaskCreator` implementation
handles actually creating tasks, the `MockCloudTaskCreator` implementation can be used from unit tests.

The simplest possible example would be:

```php
function something(TaskCreator $creator) {
  $task_name = $creator->create('extract-image-text');
  print "Queued task $task_name\n";
}

function test_something_queues_task() {
  $creator = new MockCloudTaskCreator;
  something($creator);
  $creator->assertQueuedExactly(['task_type' => 'extract-image-text', 'options' => []]);
}

```

This becomes more powerful when combined with the `CreateTaskOptions` object, which supports a variety of common
usecases.

#### Setting a task_id

Cloud Tasks supports server-side deduplication of tasks when you provide your own task ID (note that this increases
latency). We support:

* `task_id`: An explicit ID that will be passed through as-is (we take care of appending this to the queue path to build
  the fully-qualified task_name that Cloud Tasks needs). Per the Cloud Tasks docs, these should be well-distributed
  values to minimimse the performance impact.

* `task_id_from`: A string to convert (as an SHA256 hash) into a `task_id`. This simplifies your app providing
  known/unqiue values that are also well-distributed.

The task_id will also take account of throttling behaviour (see below).

#### Ignoring duplicate task submissions

By default Cloud Tasks gives an `ALREADY_EXISTS` error if you attempt to create two tasks with the same ID. If you are
using server-side deduplication, this may well be intentional - `throw_on_duplicate => FALSE` tells the wrapper to
ignore these errors and return as though the task was created successfully (since it has been, by another process).

#### Including task payloads in the URL

The `query` array will be added to the handler URL for the task type as a query string. We recommend using this to carry
the main details (e.g. the ID of the object to operate on). This makes it easy to see and trace each queued and failed
task through Cloud Tasks / your own logging etc.

It also allows the default task handler chain to protect against concurrent deliveries by taking a mutex based on the
task URL / querystring values (see below).

```php
// With the default config above, this task will be delivered to
// https://my.app/_task/recalculate-deletion-date?person_id=1532
$creator->create('recalculate-deletion-date', new CreateTaskOptions(['query' => ['person_id' => 1532]]));
```

#### Including task payloads in the body

Of course, sensitive or large values should not be included in `query`. Instead, they can be sent as body content.

```php
// Send a raw string body - note also setting headers
$img = file_get_contents('some-image.jpg');
$creator->create(
    'extract-image-text',
    new CreateTaskOptions(['body' => $img, 'headers' => ['Content-Type' => 'image/jpeg']])
);

// Or send structured data:
$payload = ['remote_path' => 'https://signed.asset.storage.url', 'callback_uri' => 'https://my.app/img-done?token=foo'];

// As JSON (adds default Content-Type: application/json but can be overridden)
$creator->create('extract-image-text', new CreateTaskOptions(['body' => ['json' => $payload]]));

// As HTTP form parameters (adds default Content-Type: application/x-www-form-urlencoded but can be overridden)
$creator->create('extract-image-text', new CreateTaskOptions(['body' => ['form' => $payload]]));
```

#### Deferring execution to the future

Provide e.g. `['schedule_send_after' => new \DateTimeImmutable('tomorrow 00:00:00');` to have Cloud Tasks hold the task
and execute it later on. By default tasks are queued for immediate execution.

#### Throttling / batching execution of a specific task

You may often want to trigger a task at relatively high frequency, but defer / batch the execution until data has
settled. For example:

* Changing any record related to a person should trigger recalculation of their deletion date. This is a fairly
  expensive operation, and often an administrator will make multiple changes to a single person in short succession. You
  can afford to wait a bit to see if there will be any more changes before the task runs.

* You want to email customers a delivery note when parcels are despatched. Outbound parcels are scanned individually,
  and collected multiple times a day. The system needs to group parcels that went together into a single email.

For this we provide the `throttle_interval` and `throttle_delay_secs` (default 60 seconds) options. These work as a
trailing-edge throttle / debounce:

```php

function onRecordUpdated(int $related_person_id)
{
    $creator->createTask(
        'recalculate-deletion-date',
        new CreateTaskOptions(
            [
                // This *must* be provided to identify different instances of the same task
                'task_id_from'        => 'recalc-delete-'.$related_person_id,
                'throttle_interval'   => new \DateInterval('PT30M'),
                'throttle_delay_secs' => 45,
                // Recommended - ALREADY_EXISTS errors are expected and not important
                'throw_on_duplicate'  => FALSE,
                'query'               => ['person_id' => $related_person_id],
            ]
        )
    );
}

// The task creator will:
//  - round current time up to the end of the current 30 minute window
//  - then add throttle_delay_secs (45 seconds here)
//  - add the execution time to the task_id_from when calculating the unique task name

timeIsNow('11:15:30.0000');
onRecordUpdated(15);
onRecordUpdated(25);
// - there are now two tasks queued, both for 11:30:45.000000, as they have different task_id_from values

waitTillTime('11:29:59.9999');
onRecordUpdated(15);
// - This will still attempt to create a task for 11:30:45.000000 and the ALREADY_EXISTS error will be silently
//   ignored. This demonstrates the need for the throttle_delay_seconds to ensure that the task does not run until a
//   reasonable period after the time window ends - if this task had already run it would not be run again.
//
// - Note also that a task may run with very litle delay if it is triggered at the end of a time window. If your app
//   logic needs a specific period of "quiet time" before running the code - e.g. in the parcel delivery example - then
//   your task handler would need to:
//   * Check if the quiet time has elapsed
//   * Re-queue the task with the same throttle_interval etc settings, this will defer it to the end of the current
//     period while still deduping it with any triggers as subsequent parcels are scanned.

waitTillTime('11:30:00.0000');
onRecordUpdated(15);
// - This will create a new task scheduled for 12:00:45.000000

```

#### Retrying task creation

The Google Cloud Tasks client does not by default retry creating tasks (as this is not an idempotent operation against
the API). By default, we do - task handlers should already be written to cope with at-least-once-delivery and that
should therefore also cope with at-least-once-creation.

This can be customised (for a single task type or globally) in the task type configuration e.g.

```php
$task_cfg = [
    // Irrelevant values omitted, see above
    '_default'                  => [
        'create_retry_settings' => [
            // See TaskTypeConfigProvider::$defaultCreateRetry for all available options
            'retriesEnabled' => FALSE
        ],               
    ],
    'some-safe-task' => [
        'create_retry_settings' => [
            'retriesEnabled' => TRUE
        ],               
    ],
];
```

#### Tasks client factory / options

The `CloudTasksClientFactory` provides a simple way to get a Cloud Tasks API client with suitable options. In
particular, it can configure the GRPC connection to use an insecure local emulator such as
`https://github.com/aertje/cloud-tasks-emulator`

## Handling tasks

The server components provide a framework-independent handler stack for validating, routing and processing incoming
tasks requests.

The default task controller (combined with the default middlewares):

* Receives an HTTP request and converts it to a `TaskRequest` (a thin wrapper that provides some helpers for common
  access patterns e.g. requiring a particular querystring value / providing access to the Cloud Tasks HTTP headers and
  signing user email).
* Uses a `TaskHandlerFactory` to locate the appropriate `TaskHandler` (implemented by your app) for the task type.
* Authenticates the request using the OIDC token provided in the HTTP request.
* Takes a mutex lock based on the request URL to guard against concurrent execution of the same task.
* Executes your task handler.
* Logs the task execution / result.
* Returns an HTTP response for your app to send to the client.

A very simple setup might look like:

```php
// index.php
// Most commonly in real-world applications you would integrate this with your app/framework's config, dependency
// management and HTTP processing layer.
$task_types    = new TaskTypeConfigProvider(
    [
        // This is the same as the client config above, but most options are not relevant for server-side handling
        '_default'    => [
            // OIDC tokens will be verified as coming from this service account email address
            'signer_email' => 'app-task-publisher@my-app.iam.gserviceaccount.com',
        ],
        'make-a-drink' => [
            // Uses defaults above (can override if required)
        ],
    ]
);
$cache         = new CacheItemPoolInterface; // Use your preferred psr-6 cache here
$log           = new NullLogger;
$result_mapper = new TaskResultCodeMapper(
    [
        // This configuration defines how app-level `result` codes should map to HTTP status and log levels. It provides
        // sane defaults for the CoreTaskResult values that ship with the wrapper
        CustomTaskResult::IM_A_TEAPOT => [
            // Note that you will often want to return "successy" codes for expected failures, where you don't want
            // Cloud Tasks to bother retrying. Using custom high-numbered 2xx results still lets these show up in logs.
            // If you think this is messy, vote on https://issuetracker.google.com/issues/162255862
            'http_status' => 285,
            // But you can log the operation as e.g. a warning / emergency to report internally that a task failed.
            'loglevel'    => LogLevel::WARNING,

        ]
    ]
);
$controller    = new TaskController(
    TaskHandlerChain::makeDefault(
        new TaskLoggingMiddleware(new RealtimeClock, $log, $result_mapper),
        new TaskRequestAuthenticatingMiddleware(
            $task_types,
            // See our oidc-token-verifier package for details, including configuring to allow http:// issuers in
            // developer environments.
            new OIDCTokenVerifier(new OpenIDDiscoveryCertificateProvider(new GuzzleHttp\Client, $cache, $log))
        ),
        new TaskMutexLockingMiddleware(new DbBackedMutexWrapper(new PDO('mysql:whatever')))
    ),
    new ArrayTaskHandlerFactory(
        // IRL you would probaly use a dependency container for this e.g. defining your handlers as services named
        // like `task_handler.make-a-drink` and implementing a TaskHandlerFactory class that can get the appropriate 
        // service from your DI container
        ['make-a-drink' => new MakeADrinkTaskHandler]
    ),
    $result_mapper,
    // The URL pattern should be a regex that can extract the task_type value from the URL. It must capture the
    // a `task_type` named parameter as below. Note that the TaskController will throw an \InvalidArgumentException
    // if the URL does not match this pattern.
    '#^/_do_task/(?P<task_type>.+)$#'
);

$response = $controller->handle(ServerRequest::fromGlobals());
header('HTTP/1.1 '.$response->getStatusCode().' '.$response->getStatusCodeName());
echo $response->getStatusCodeName();
```

```php
class MakeADrinkTaskHandler implements TaskHandler
{
    public function handle(TaskRequest $request): TaskHandlerResult
    {
        // If `?beverage=xxx` is not present or empty this will throw a CloudTaskCannotBeValidException
        // This will be caught in the wrapper and mapped to the equivalent result status
        // By default this then produces an HTTP 299 status code, signaling to Cloud Tasks not to retry (it's not going
        // to get any more valid) but allowing it to be identified separately to successful requests
        $beverage = $request->requireQueryParam('beverage');

        switch ($beverage) {
            case 'Tea':
                // There are various common result types available in the shipped CoreTaskResult class
                return CoreTaskResult::success('with 2 sugars');
            default:
                // You can add custom results either by just doing `new TaskHandlerResult($code, $msg, $log_context)` or
                // (preferred) by defining a class with your own constants, constructors etc.
                // The log_context arg will be merged with any other log context into your PSR logger
                // NB that you usually wouldn't couple the application-layer result codes wth HTTP status codes...
                return CustomTaskResult::imATeapot('You cannot be serious', ['asked_for' => $beverage]);
        }
    }

}
```

```php
class CustomTaskResult extends TaskHandlerResult
{

    const IM_A_TEAPOT = 'imATeapot';

    public static function imATeapot(string $msg, array $log_context): CustomTaskResult
    {
        return new static(static::IM_A_TEAPOT, 'Teapot: '.$msg, $log_context);
    }

}
```

Once you have bootstrapped the controller / middleware chain, adding new task types just involves adding a new type to
the `TaskTypeConfigProvider` and implementing a `TaskHandler`.

# Contributing

Contributions are welcome but please contact us (e.g. by filing an issue) before you start work on anything substantial
: we may have particular requirements / opinions that differ from yours.

# Contributors

This package has been sponsored by [inGenerator Ltd](http://www.ingenerator.com)

* Andrew Coulton [acoulton](https://github.com/acoulton) - Lead developer

# Licence

Licensed under the [BSD-3-Clause Licence](LICENSE)
