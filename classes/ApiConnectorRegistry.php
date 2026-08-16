<?php
namespace MSM;

use InvalidArgumentException;

final class ApiConnectorRegistry
{
    /** @var array<string, ApiConnectorInterface> */
    private array $connectors = [];

    public function __construct(?array $connectors = null)
    {
        foreach ($connectors ?? [new ReolinkConnector()] as $connector) {
            if ($connector instanceof ApiConnectorInterface) {
                $this->connectors[$connector->key()] = $connector;
            }
        }
    }

    public function get(string $key): ApiConnectorInterface
    {
        if (!isset($this->connectors[$key])) {
            throw new InvalidArgumentException('Connecteur API inconnu : ' . $key);
        }

        return $this->connectors[$key];
    }

    public function all(): array
    {
        return $this->connectors;
    }
}
