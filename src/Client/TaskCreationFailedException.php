<?php


namespace Ingenerator\CloudTasksWrapper\Client;


/**
 * Wrapper for anything runtime that prevented us creating a task
 *
 * Used to indicate that the error has already been caught / logged etc by the TaskCreator
 * so does not necessarily need to be reported elsewhere
 */
class TaskCreationFailedException extends \RuntimeException
{

}
