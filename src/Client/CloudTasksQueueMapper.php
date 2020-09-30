<?php


namespace Ingenerator\CloudTasksWrapper\Client;


use Google\Cloud\Tasks\V2\CloudTasksClient;
use Ingenerator\PHPUtils\StringEncoding\JSON;

class CloudTasksQueueMapper
{

    /**
     * @var array
     */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Maps our internal queue names to fully qualified cloud tasks names (which are actually paths)
     *
     * Allows a future extension point for striping across multiple queues (for higher throughput if we need more than
     * 500 TPS), and/or mapping differently dev/prod.
     *
     * @param string $internal_name
     *
     * @return string
     */
    public function pathFor(string $internal_name)
    {
        $mapping = $this->getConfigMapping($internal_name);

        return CloudTasksClient::queueName(
            $mapping['project'] ?? $this->config['default_project'],
            $mapping['location'] ?? $this->config['default_location'],
            $mapping['name'] ?? $internal_name
        );
    }

    /**
     * Get the (service account) email address to attach as the signer for messages to this queue
     *
     * @param string $internal_name
     *
     * @return string
     */
    public function getOidcSignerEmail(string $internal_name): string
    {
        $mapping = $this->getConfigMapping($internal_name);
        $signer  = $mapping['signer'] ?? $this->config['default_signer'];
        if ( ! $signer) {
            throw new \InvalidArgumentException(
                "No `signer` configured for `$internal_name` and no default_signer in config"
            );
        }

        return $signer;
    }

    /**
     * @param string $internal_name
     *
     * @return array
     */
    protected function getConfigMapping(string $internal_name): array
    {
        $mapping = $this->config['queues'][$internal_name] ?? NULL;
        if ($mapping === NULL) {
            throw new \InvalidArgumentException(
                sprintf(
                    'No config mapping for queue %s (in %s)',
                    $internal_name,
                    JSON::encode(array_keys($this->config['queues']), FALSE)
                )
            );
        }

        return $mapping;
    }

}
