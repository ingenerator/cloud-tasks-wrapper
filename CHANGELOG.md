## Unreleased

* Capture more task metadata in the log_context when logging task execution.
* Add an `ExceptionCatchingMiddleware` to catch exceptions (which I forgot to include in 0.1)
* Provide `task_id_hash_options` flag when creating tasks to tell the wrapper to use the querystring, headers and
  schedule time as part of the automatic task ID hash.

## v0.1.0 (2020-12-08)

* Initial version
