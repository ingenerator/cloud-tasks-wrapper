## Unreleased

* Additionally, support ingenerator/oidc-token-verifier:^1.0

## v0.5.2 (2023-08-15)

* Use InsecureCredentialsWrapper when authenticating with the emulator in dev 

## v0.5.1 (2023-03-30)

* Allow clients to specify a custom_handler_url when creating a task, to override the default handler_url provided
  by the configuration for the task type. Useful when the handler url is dependent on something that can only be 
  determined at runtime (e.g. routing to a subdomain).

* Support a `custom_token_audience` in the TaskType options, to allow specifying a non-default `audience` in the JWT
  that Cloud Tasks issues to authorise the request.

* Add support for PHP 8.2

* Drop support for PHP 8.0

## v0.5.0 (2022-10-17)

* Support PHP 8.1

## v0.4.2 (2022-03-14)

* Advertise support for bc-math:^8.0

## v0.4.1 (2021-11-18)

* Helper method TransactionMarkerMiddlewareHelper::addTransactionMarkerHeaders to add correct headers for transaction
  marker middleware

## v0.4.0 (2021-11-07)

* Allow multiple additional middlewares to be registered on the end of the default chain using the bundled factory method.

* TransactionMarker Middleware - Blocks a task from execution until the transaction marker exists in the repository. Allows Cloud Tasks to retry the execution of the task up until the specified expiry.

* Middleware to parse JSON body in request.

## v0.3.1 (2021-11-03)

* Fix inadvertently moved OIDC token verifier dependency

## v0.3.0 (2021-11-01)

* Support PHP 8.0

## v0.2.3 (2021-06-03)

* Provide assertQueuedExactlyOne() on MockCloudTaskCreator to assert creation of a single task with
  expected type. Useful when other tests cover the task details and you just want
  to be sure it fired / didn't fire.

* Improve support for values sent in the POST body of a task request. These can now be included in
  TaskRequestStub instances (provide the `parsed_body` option to ::with()). The TaskRequest also
  includes a sugar method to grab a body (POST) value and throw if not present or empty.

## v0.2.2 (2021-04-09)

* Fix MockTaskCreator so that it can compare the DateTimeImmutable for schedule_send_after and DateInterval for
  throttle_interval. These were previously compared by strict equality, which doesn't work.
 
## v0.2.1 (2021-01-18)

* Relax authentication rules for verifying JWT audience to ignore mismatched protocol or hostname.
  In a loadbalanced environment, the `ServerRequestInterface` may not always have accurate information on whether
  the request was http/s, or the external hostname that was routed to the appserver. Therefore only validate the
  path and querystring (which identifies the operation to be performed). This still prevents reusing a token to perform
  a different operation. You should anyway be using different service accounts for different environments, which in
  itself protects against using e.g. a QA token to authorise the same operation in production. Therefore the hostname
  etc is not relevant to the authentication/authorisation.

## v0.2.0 (2020-12-11)

* Capture more task metadata and memory usage in the log_context when logging task execution.
* Add an `ExceptionCatchingMiddleware` to catch exceptions (which I forgot to include in 0.1)
* Provide `task_id_hash_options` flag when creating tasks to tell the wrapper to use the querystring, headers and
  schedule time as part of the automatic task ID hash.

## v0.1.0 (2020-12-08)

* Initial version
