## Unreleased

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
